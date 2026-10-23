<?php
// File: inc.compat8.php
// +----------------------------------------------------------------------+
// | Sefrengo CMS - PHP 8 compatibility wrappers                           |
// |                                                                       |
// | PHP 8 throws a TypeError when an internal array function receives     |
// | null or a scalar (PHP 7 only raised a warning and carried on).        |
// | The legacy code regularly passes values that may be unset, so these   |
// | wrappers keep the PHP 7 behaviour for the affected functions.         |
// | Calls in the CMS and the plugins are rewritten to sf_<function>().    |
// +----------------------------------------------------------------------+

if (!function_exists('sf_count')) {

    function sf_count($value, $mode = COUNT_NORMAL)
    {
        if (is_array($value) || $value instanceof Countable) {
            return count($value, $mode);
        }

        return ($value === null) ? 0 : 1;
    }

    function sf_sizeof($value, $mode = COUNT_NORMAL)
    {
        return sf_count($value, $mode);
    }

    function sf_in_array($needle, $haystack, $strict = false)
    {
        return is_array($haystack) ? in_array($needle, $haystack, $strict) : false;
    }

    function sf_array_key_exists($key, $array)
    {
        if (is_array($array)) {
            return array_key_exists($key, $array);
        }
        if (is_object($array)) {
            return property_exists($array, (string) $key);
        }

        return false;
    }

    function sf_key_exists($key, $array)
    {
        return sf_array_key_exists($key, $array);
    }

    function sf_implode($separator, $array = null)
    {
        if ($array === null) {
            // implode(array) form
            return is_array($separator) ? implode($separator) : '';
        }
        if (!is_array($array)) {
            return is_array($separator) ? implode((string) $array, $separator) : '';
        }

        return implode($separator, $array);
    }

    function sf_join($separator, $array = null)
    {
        return sf_implode($separator, $array);
    }

    function sf_array_merge(...$arrays)
    {
        foreach ($arrays as $k => $a) {
            if (!is_array($a)) {
                $arrays[$k] = ($a === null) ? array() : array($a);
            }
        }

        return array_merge(...$arrays);
    }

    function sf_array_keys($array, ...$rest)
    {
        return is_array($array) ? array_keys($array, ...$rest) : array();
    }

    function sf_array_values($array)
    {
        return is_array($array) ? array_values($array) : array();
    }

    function sf_array_search($needle, $haystack, $strict = false)
    {
        return is_array($haystack) ? array_search($needle, $haystack, $strict) : false;
    }

    function sf_array_unique($array, $flags = SORT_STRING)
    {
        return is_array($array) ? array_unique($array, $flags) : array();
    }

    function sf_array_flip($array)
    {
        return is_array($array) ? array_flip($array) : array();
    }

    function sf_array_slice($array, $offset, $length = null, $preserve_keys = false)
    {
        return is_array($array) ? array_slice($array, $offset, $length, $preserve_keys) : array();
    }

    // by reference wrappers (the argument may be undefined / null)

    function sf_array_push(&$array, ...$values)
    {
        if (!is_array($array)) {
            $array = array();
        }

        return array_push($array, ...$values);
    }

    function sf_array_unshift(&$array, ...$values)
    {
        if (!is_array($array)) {
            $array = array();
        }

        return array_unshift($array, ...$values);
    }

    function sf_array_pop(&$array)
    {
        return is_array($array) ? array_pop($array) : null;
    }

    function sf_array_shift(&$array)
    {
        return is_array($array) ? array_shift($array) : null;
    }

    function sf_reset(&$array)
    {
        return is_array($array) ? reset($array) : false;
    }

    function sf_end(&$array)
    {
        return is_array($array) ? end($array) : false;
    }

    function sf_current(&$array)
    {
        return is_array($array) ? current($array) : false;
    }

    function sf_next(&$array)
    {
        return is_array($array) ? next($array) : false;
    }

    function sf_key(&$array)
    {
        return is_array($array) ? key($array) : null;
    }

    function sf_sort(&$array, $flags = SORT_REGULAR)
    {
        return is_array($array) ? sort($array, $flags) : false;
    }

    function sf_rsort(&$array, $flags = SORT_REGULAR)
    {
        return is_array($array) ? rsort($array, $flags) : false;
    }

    function sf_asort(&$array, $flags = SORT_REGULAR)
    {
        return is_array($array) ? asort($array, $flags) : false;
    }

    function sf_arsort(&$array, $flags = SORT_REGULAR)
    {
        return is_array($array) ? arsort($array, $flags) : false;
    }

    function sf_ksort(&$array, $flags = SORT_REGULAR)
    {
        return is_array($array) ? ksort($array, $flags) : false;
    }

    function sf_krsort(&$array, $flags = SORT_REGULAR)
    {
        return is_array($array) ? krsort($array, $flags) : false;
    }

    function sf_usort(&$array, $callback)
    {
        return is_array($array) ? usort($array, $callback) : false;
    }

    function sf_uasort(&$array, $callback)
    {
        return is_array($array) ? uasort($array, $callback) : false;
    }

    function sf_uksort(&$array, $callback)
    {
        return is_array($array) ? uksort($array, $callback) : false;
    }

    // ---------------------------------------------------------------- strings / dates

    /**
     * utf8_encode() without the double encoding: text that already is valid UTF-8 is returned unchanged
     * (utf8_encode/utf8_decode are deprecated since PHP 8.2).
     */
    function sf_utf8_encode($string)
    {
        $string = (string) $string;
        if ($string === '' || mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }

        return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
    }

    function sf_utf8_decode($string)
    {
        return mb_convert_encoding((string) $string, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * strftime() replacement (deprecated since PHP 8.1). Returns UTF-8, day and month names follow
     * the language of the current LC_TIME locale (de, fr, es, it, nl, en).
     */
    function sf_strftime($format, $timestamp = null, $gmt = false)
    {
        static $names = array(
            'de' => array(
                'days' => array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'),
                'days_s' => array('So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'),
                'months' => array('Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'),
                'months_s' => array('Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'),
            ),
            'fr' => array(
                'days' => array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'),
                'days_s' => array('dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'),
                'months' => array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'),
                'months_s' => array('janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'),
            ),
            'es' => array(
                'days' => array('domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'),
                'days_s' => array('dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'),
                'months' => array('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'),
                'months_s' => array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'),
            ),
            'it' => array(
                'days' => array('domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'),
                'days_s' => array('dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'),
                'months' => array('gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'),
                'months_s' => array('gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'),
            ),
            'nl' => array(
                'days' => array('zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'),
                'days_s' => array('zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'),
                'months' => array('januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'),
                'months_s' => array('jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'),
            ),
            'en' => array(
                'days' => array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                'days_s' => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
                'months' => array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                'months_s' => array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'),
            ),
        );

        $ts = ($timestamp === null) ? time() : (int) $timestamp;
        $loc = strtolower((string) setlocale(LC_TIME, 0));
        if (preg_match('/^(de|german|deutsch)/', $loc)) {
            $lang = 'de';
        } elseif (preg_match('/^(fr|french)/', $loc)) {
            $lang = 'fr';
        } elseif (preg_match('/^(es|spanish)/', $loc)) {
            $lang = 'es';
        } elseif (preg_match('/^(it|italian)/', $loc)) {
            $lang = 'it';
        } elseif (preg_match('/^(nl|dutch)/', $loc)) {
            $lang = 'nl';
        } else {
            $lang = 'en';
        }
        $n = $names[$lang];
        $d = function ($f) use ($ts, $gmt) {
            return $gmt ? gmdate($f, $ts) : date($f, $ts);
        };

        $map = array(
            'a' => function () use ($d, $n) { return $n['days_s'][(int) $d('w')]; },
            'A' => function () use ($d, $n) { return $n['days'][(int) $d('w')]; },
            'd' => function () use ($d) { return $d('d'); },
            'e' => function () use ($d) { return sprintf('%2d', (int) $d('j')); },
            'j' => function () use ($d) { return sprintf('%03d', (int) $d('z') + 1); },
            'u' => function () use ($d) { return $d('N'); },
            'w' => function () use ($d) { return $d('w'); },
            'V' => function () use ($d) { return $d('W'); },
            'b' => function () use ($d, $n) { return $n['months_s'][(int) $d('n') - 1]; },
            'h' => function () use ($d, $n) { return $n['months_s'][(int) $d('n') - 1]; },
            'B' => function () use ($d, $n) { return $n['months'][(int) $d('n') - 1]; },
            'm' => function () use ($d) { return $d('m'); },
            'y' => function () use ($d) { return $d('y'); },
            'Y' => function () use ($d) { return $d('Y'); },
            'G' => function () use ($d) { return $d('o'); },
            'H' => function () use ($d) { return $d('H'); },
            'k' => function () use ($d) { return sprintf('%2d', (int) $d('G')); },
            'I' => function () use ($d) { return $d('h'); },
            'l' => function () use ($d) { return sprintf('%2d', (int) $d('g')); },
            'M' => function () use ($d) { return $d('i'); },
            'p' => function () use ($d) { return $d('A'); },
            'P' => function () use ($d) { return $d('a'); },
            'S' => function () use ($d) { return $d('s'); },
            's' => function () use ($ts) { return (string) $ts; },
            'Z' => function () use ($d) { return $d('T'); },
            'z' => function () use ($d) { return $d('O'); },
            'D' => function () use ($d) { return $d('m/d/y'); },
            'F' => function () use ($d) { return $d('Y-m-d'); },
            'T' => function () use ($d) { return $d('H:i:s'); },
            'R' => function () use ($d) { return $d('H:i'); },
            'r' => function () use ($d) { return $d('h:i:s A'); },
            'c' => function () use ($d) { return $d('D M j H:i:s Y'); },
            'x' => function () use ($d) { return $d('d.m.Y'); },
            'X' => function () use ($d) { return $d('H:i:s'); },
            'C' => function () use ($d) { return sprintf('%02d', (int) floor((int) $d('Y') / 100)); },
            'n' => function () { return "\n"; },
            't' => function () { return "\t"; },
            '%' => function () { return '%'; },
        );

        return preg_replace_callback('/%([a-zA-Z%])/', function ($m) use ($map) {
            return isset($map[$m[1]]) ? $map[$m[1]]() : $m[0];
        }, (string) $format);
    }

    function sf_gmstrftime($format, $timestamp = null)
    {
        return sf_strftime($format, $timestamp, true);
    }

    /**
     * PHP 7 semantics of "$value == 0": an empty string / null / false counts as zero
     * (PHP 8 compares "" == 0 as false).
     */
    function sf_is_zero($value)
    {
        return $value === '' || $value === null || $value === false || (is_numeric($value) && $value == 0);
    }
}
