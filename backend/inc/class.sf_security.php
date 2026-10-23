<?php
// File: class.sf_security.php
// +----------------------------------------------------------------------+
// | Sefrengo CMS - central security helpers (PHP 8.x)                     |
// | password hashing, secure random ids, session cookie handling,         |
// | login throttling and request origin checks                            |
// +----------------------------------------------------------------------+

class SF_Security
{
    /** minimal length for new passwords */
    const MIN_PASSWORD_LENGTH = 10;

    /** failed logins before the account is temporarily locked */
    const LOCK_AFTER_FAILURES = 5;

    /** max. lock time in seconds */
    const LOCK_MAX_SECONDS = 3600;

    /**
     * Hashes a password with the strongest available algorithm (Argon2id, else bcrypt).
     *
     * @param string $password
     * @return string hash (max. 255 chars)
     */
    public static function hashPassword($password)
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash((string) $password, PASSWORD_ARGON2ID);
        }

        return password_hash((string) $password, PASSWORD_BCRYPT, array('cost' => 12));
    }

    /**
     * Verifies a password against a stored hash. Legacy unsalted md5 hashes
     * (32 hex chars) are still accepted so existing users can log in once and
     * get their hash upgraded transparently (see $needs_rehash).
     *
     * @param string $password
     * @param string $stored_hash
     * @param bool   $needs_rehash set to true when the stored hash should be replaced
     * @return bool
     */
    public static function verifyPassword($password, $stored_hash, &$needs_rehash = false)
    {
        $needs_rehash = false;
        $password = (string) $password;
        $stored_hash = (string) $stored_hash;

        if ($stored_hash === '') {
            return false;
        }

        if (self::isLegacyHash($stored_hash)) {
            if (hash_equals(strtolower($stored_hash), md5($password))) {
                $needs_rehash = true;

                return true;
            }

            return false;
        }

        if (!password_verify($password, $stored_hash)) {
            return false;
        }
        $needs_rehash = self::needsRehash($stored_hash);

        return true;
    }

    public static function isLegacyHash($hash)
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', (string) $hash);
    }

    public static function needsRehash($hash)
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID);
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT, array('cost' => 12));
    }

    /**
     * Burns roughly the time of a real verification, so the response time
     * does not reveal whether a username exists.
     */
    public static function dummyVerify($password)
    {
        static $dummy = null;
        if ($dummy === null) {
            $dummy = self::hashPassword('sefrengo-dummy-password');
        }
        password_verify((string) $password, $dummy);
    }

    /**
     * @param string $password
     * @return string|true true if ok, else an error text key
     */
    public static function checkPasswordPolicy($password)
    {
        if (self::strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'password_too_short';
        }

        return true;
    }

    private static function strlen($s)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $s, 'UTF-8') : strlen((string) $s);
    }

    /**
     * Seconds an account stays locked after $failed_count failures, measured from the last failure.
     */
    public static function lockSeconds($failed_count)
    {
        if ($failed_count < self::LOCK_AFTER_FAILURES) {
            return 0;
        }
        $exp = min(10, $failed_count - self::LOCK_AFTER_FAILURES);

        return (int) min(self::LOCK_MAX_SECONDS, 30 * (2 ** $exp));
    }

    /**
     * @return bool true if the account is currently locked
     */
    public static function isLocked($failed_count, $last_failed_timestamp)
    {
        $lock = self::lockSeconds((int) $failed_count);

        return $lock > 0 && (time() - (int) $last_failed_timestamp) < $lock;
    }

    /** cryptographically secure random hex string */
    public static function randomHex($bytes = 16)
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Sets a cookie with secure defaults (HttpOnly, SameSite, Secure on https).
     *
     * @param string $name
     * @param string $value
     * @param int    $expires unix timestamp, 0 = session cookie
     * @param string $domain
     * @param string $samesite 'Strict', 'Lax' or 'None'
     */
    public static function setCookie($name, $value, $expires = 0, $domain = '', $samesite = 'Lax')
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie($name, (string) $value, array(
            'expires'  => (int) $expires,
            'path'     => '/',
            'domain'   => (string) $domain,
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => $samesite,
        ));
    }

    /**
     * Basic hardening headers.
     *
     * @param bool $allow_same_origin_frames backend uses framesets
     */
    public static function sendSecurityHeaders($allow_same_origin_frames = true)
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if ($allow_same_origin_frames) {
            header('X-Frame-Options: SAMEORIGIN');
        }
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }

    /**
     * Rejects state changing requests that do not originate from this host
     * (CSRF defence in depth on top of the SameSite session cookie).
     */
    public static function enforceSameOrigin()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if (in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            return;
        }
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        $source = '';
        if (!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'null') {
            $source = $_SERVER['HTTP_ORIGIN'];
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $source = $_SERVER['HTTP_REFERER'];
        } elseif (!empty($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'cross-site') {
            $source = 'cross-site';
        }
        if ($source === '') {
            return; // old/privacy clients without any hint: SameSite cookie still applies
        }
        $source_host = ($source === 'cross-site') ? '' : strtolower((string) parse_url($source, PHP_URL_HOST));
        $port = parse_url($source, PHP_URL_PORT);
        if ($port) {
            $source_host .= ':' . $port;
        }
        if ($source_host !== $host) {
            header('HTTP/1.1 403 Forbidden');
            exit('Forbidden: cross-site request blocked.');
        }
    }
}
