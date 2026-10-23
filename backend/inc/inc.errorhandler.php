<?php
// File: inc.errorhandler.php
// +----------------------------------------------------------------------+
// | Sefrengo CMS - error handling for PHP 8.x                             |
// |                                                                       |
// | PHP 8 turned many former E_NOTICE messages into E_WARNING             |
// | ("Undefined variable", "Undefined array key", ...). The legacy code   |
// | base relies on the PHP 7 behaviour (undefined == null, not shown).    |
// | This handler restores that behaviour for exactly these messages,      |
// | everything else (real warnings, errors, exceptions) is untouched.     |
// |                                                                       |
// | - never display errors to visitors: display_errors is switched off    |
// |   unless the file backend/logs/.display_errors exists (development)   |
// | - all errors are written to backend/logs/errorlog.txt                 |
// | - legacy notices are only logged when backend/logs/.debug exists      |
// +----------------------------------------------------------------------+

if (!function_exists('sf_error_handler')) {

    $sf_log_dir = str_replace('\\', '/', dirname(__FILE__)) . '/../logs/';
    define('SF_LOG_DIR', $sf_log_dir);
    define('SF_DEBUG_NOTICES', is_file($sf_log_dir . '.debug'));
    define('SF_DISPLAY_ERRORS', is_file($sf_log_dir . '.display_errors'));
    unset($sf_log_dir);

    ini_set('display_errors', SF_DISPLAY_ERRORS ? '1' : '0');
    ini_set('log_errors', '1');
    if (is_dir(SF_LOG_DIR) && is_writable(SF_LOG_DIR)) {
        ini_set('error_log', SF_LOG_DIR . 'errorlog.txt');
    }

    /**
     * @return bool true = handled, false = let PHP's standard handler continue
     */
    function sf_error_handler($errno, $errstr, $errfile, $errline)
    {
        // honour the @ operator and the configured error_reporting
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $legacy = false;
        if ($errno === E_WARNING || $errno === E_NOTICE) {
            $legacy = (bool) preg_match(
                '/^(Undefined (variable|array key|property|offset|index)|Trying to access array offset on|Attempt to read property|Array to string conversion|Uninitialized string offset)/',
                $errstr
            );
        } elseif ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            $legacy = true;
        }

        if ($legacy) {
            if (SF_DEBUG_NOTICES) {
                @error_log('[legacy] ' . $errstr . ' in ' . $errfile . ':' . $errline);
            }

            return true;
        }

        return false;
    }

    set_error_handler('sf_error_handler');
}
