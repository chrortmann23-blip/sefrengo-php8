<?php
/**
 * Sefrengo 2.0.2 - Diagnose nach dem Update auf PHP 8 (nur lesend, aendert nichts)
 *
 * Prueft: PHP-Umgebung, Datenbank-Zeichensatz (Umlaute!), veraltete Dateien im Projektordner,
 * PHP-8-Probleme im Code der Datenbank (Module, Layouts), letzte Eintraege des Fehlerlogs.
 * Zugangsdaten werden aus backend/inc/config.php gelesen und NICHT ausgegeben.
 *
 * Aufruf (empfohlen, per SSH):   php diagnose.php [/pfad/zu/backend]
 * Ohne SSH (Browser):            1. Datei in den Ordner backend/ legen
 *                                2. leere Datei backend/logs/.diagnose anlegen
 *                                3. https://IHRE-DOMAIN/backend/diagnose.php aufrufen
 *                                4. danach BEIDE Dateien wieder loeschen!
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
$cli = (PHP_SAPI === 'cli');
$backend = $cli ? (isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__FILE__) . '/../backend') : dirname(__FILE__);
if (!is_file($backend . '/inc/config.php') && is_file(dirname(__FILE__) . '/inc/config.php')) {
    $backend = dirname(__FILE__);
}
if (!$cli) {
    if (!is_file($backend . '/logs/.diagnose')) {
        header('HTTP/1.1 403 Forbidden');
        exit('Gesperrt. Zum Freischalten die leere Datei backend/logs/.diagnose anlegen.');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}
$backend = str_replace('\\', '/', $backend);

function out($s = '') { echo $s, "\n"; }
function h($s) { return strtoupper(bin2hex($s)); }
function title($s) { out(); out('=== ' . $s . ' ==='); }

out('Sefrengo Diagnose - ' . date('Y-m-d H:i:s'));

/* ------------------------------------------------------------------ 1. Umgebung */
title('1. PHP / Server');
out('PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
foreach (array('mysqli', 'gd', 'zlib', 'mbstring', 'zip') as $e) {
    out(str_pad('Erweiterung ' . $e, 24) . (extension_loaded($e) ? 'ok' : 'FEHLT'));
}
out('short_open_tag        ' . var_export((bool) ini_get('short_open_tag'), true) . '  (Sefrengo 2.0.1 braucht es nicht)');
out('display_errors        ' . var_export(ini_get('display_errors'), true));
out('error_log (php.ini)   ' . var_export(ini_get('error_log'), true));
out('default_charset       ' . var_export(ini_get('default_charset'), true));
out('Argon2id verfuegbar   ' . (defined('PASSWORD_ARGON2ID') ? 'ja' : 'nein (bcrypt wird genutzt)'));

/* ------------------------------------------------------------------ 2. Konfiguration */
title('2. Konfiguration (backend/inc/config.php)');
$cfg_file = $backend . '/inc/config.php';
if (!is_file($cfg_file)) {
    out('config.php nicht gefunden: ' . $cfg_file);
    exit(1);
}
define('CMS_CONFIGFILE_INCLUDED', true);
$cfg_cms = array();
$cms_db = array();
ob_start();
include $cfg_file;
ob_end_clean();
out('db_host        ' . $cfg_cms['db_host']);
out('db_database    ' . $cfg_cms['db_database']);
out('db_table_prefix ' . $cfg_cms['db_table_prefix']);
out('db_utf8        ' . var_export($cfg_cms['db_utf8'], true));
out('db_charset     ' . (isset($cfg_cms['db_charset']) ? var_export($cfg_cms['db_charset'], true) : '(nicht gesetzt)'));

/* ------------------------------------------------------------------ 3. Datenbank */
title('3. Datenbank und Zeichensatz');
$db = @mysqli_connect($cfg_cms['db_host'], $cfg_cms['db_user'], $cfg_cms['db_password'], $cfg_cms['db_database']);
if (!$db) {
    out('Verbindung fehlgeschlagen: ' . mysqli_connect_error());
    exit(1);
}
$pre = $cfg_cms['db_table_prefix'];
out('Server: ' . mysqli_get_server_info($db));
out('Standard-Verbindungszeichensatz von PHP: ' . mysqli_character_set_name($db));
$r = mysqli_query($db, "SHOW VARIABLES LIKE 'character_set_%'");
while ($row = mysqli_fetch_row($r)) {
    if (in_array($row[0], array('character_set_server', 'character_set_database', 'character_set_client', 'character_set_connection', 'character_set_results'))) {
        out('  ' . str_pad($row[0], 26) . $row[1]);
    }
}
$r = mysqli_query($db, "SELECT default_character_set_name, default_collation_name FROM information_schema.schemata WHERE schema_name = '" . mysqli_real_escape_string($db, $cfg_cms['db_database']) . "'");
$row = mysqli_fetch_row($r);
out('Datenbank-Standard: ' . $row[0] . ' / ' . $row[1]);

out();
out('Zeichensatz der Textspalten:');
$cols = array(array('side_lang', 'title'), array('cat_lang', 'name'), array('content', 'value'), array('mod', 'description'), array('lay', 'code'));
foreach ($cols as $c) {
    $r = mysqli_query($db, "SELECT character_set_name, collation_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . $pre . $c[0] . "' AND column_name = '" . $c[1] . "'");
    $row = $r ? mysqli_fetch_row($r) : null;
    out('  ' . str_pad($pre . $c[0] . '.' . $c[1], 28) . ($row ? $row[0] . ' / ' . $row[1] : '(nicht gefunden)'));
}
$r = mysqli_query($db, "SELECT table_name, table_collation, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $pre . "users'");
$row = mysqli_fetch_row($r);
out('  Tabelle ' . $pre . 'users: ' . $row[1] . ' / ' . $row[2]);
$r = mysqli_query($db, "SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '" . $pre . "users' AND column_name = 'password'");
$row = mysqli_fetch_row($r);
out('  ' . $pre . 'users.password: ' . $row[0] . (strpos($row[0], '255') === false ? '   <-- MUSS varchar(255) sein (Setup im Modus Update ausfuehren)' : '  ok'));
$r = mysqli_query($db, "SELECT value FROM " . $pre . "values WHERE group_name = 'cfg' AND key1 = 'version'");
$row = $r ? mysqli_fetch_row($r) : null;
out('  Datenbank-Version (cfg/version): ' . ($row ? $row[0] : '?') . '   (erwartet: 02.00.02)');

/* Wie sehen Texte unter den moeglichen Verbindungszeichensaetzen aus? */
title('4. Umlaut-Test: welche Verbindungskodierung passt zu den Daten?');
$samples = array(
    array('side_lang', 'title', 400), array('cat_lang', 'name', 200), array('mod', 'description', 100), array('content', 'value', 400),
);
$score = array('latin1' => 0, 'utf8' => 0, 'utf8mb4' => 0, 'binary' => 0);
$total = 0;
foreach (array('latin1', 'utf8', 'utf8mb4', 'binary') as $cs) {
    mysqli_set_charset($db, $cs);
    $good = 0; $bad = 0; $n = 0;
    foreach ($samples as $s) {
        $r = @mysqli_query($db, "SELECT " . $s[1] . " FROM " . $pre . $s[0] . " WHERE " . $s[1] . " REGEXP '[^ -~]' LIMIT " . (int) $s[2]);
        if (!$r) continue;
        while ($row = mysqli_fetch_row($r)) {
            $v = (string) $row[0];
            $v = substr($v, 0, 4000);
            $n++;
            // ok: gueltiges UTF-8 und keine typische Doppelkodierung (Ã¤ Ã¶ Ã¼ ...)
            $valid = (bool) preg_match('//u', $v);
            $double = (bool) preg_match('/\xC3[\x82\x83][\xC2\xC3]?[\x80-\xBF]/', $v);
            if ($valid && !$double) $good++; else $bad++;
        }
    }
    $score[$cs] = $good;
    if ($cs === 'latin1') $total = $n;
    out(str_pad($cs, 9) . ': ' . $good . ' von ' . $n . ' Texten mit Sonderzeichen sehen korrekt aus (UTF-8, nicht doppelt kodiert), ' . $bad . ' nicht');
}
mysqli_set_charset($db, 'binary');
$best = 'latin1'; $bm = -1;
foreach (array('latin1', 'utf8', 'utf8mb4') as $cs) { if ($score[$cs] > $bm) { $bm = $score[$cs]; $best = $cs; } }
out();
if ($total === 0) {
    out('Keine Texte mit Sonderzeichen gefunden - keine Empfehlung moeglich.');
} else {
    out('Empfehlung: in backend/inc/config.php setzen:  $cfg_cms[\'db_charset\'] = \'' . $best . '\';');
    out('(Treffer sind nur ein Hinweis. Danach das Backend und eine Seite mit Umlauten ansehen.)');
}
// Beispiele (Roh-Bytes)
mysqli_set_charset($db, 'binary');
$r = mysqli_query($db, "SELECT title FROM " . $pre . "side_lang WHERE title REGEXP '[^ -~]' LIMIT 3");
if ($r) { while ($row = mysqli_fetch_row($r)) out('  Beispiel Seitentitel (Hex): ' . substr(h($row[0]), 0, 80)); }

/* ------------------------------------------------------------------ 5. Dateien */
title('5. Projektordner: veraltete Dateien (Ursache fuer HTTP 500)');
$root = dirname($backend);
$found = false;
foreach (glob($root . '/*', GLOB_ONLYDIR) as $dir) {
    if (!is_file($dir . '/cms/inc/frontend.php') && !is_file($dir . '/index.php')) continue;
    if (!is_dir($dir . '/cms/inc')) continue;
    $found = true;
    out('Projektordner: ' . basename($dir));
    $idx = @file_get_contents($dir . '/index.php');
    $fe = @file_get_contents($dir . '/cms/inc/frontend.php');
    $be = @file_get_contents($dir . '/cms/inc/backend.php');
    out('  index.php             ' . (strpos($idx, '@count($arr)') !== false ? 'ALT (1.6.9) - ersetzen!  <-- verursacht HTTP 500' : (strpos($idx, 'sf_security') !== false || strpos($idx, 'inc.errorhandler') !== false ? 'neu (2.0.1)' : 'unbekannter Stand')));
    out('  cms/inc/frontend.php  ' . (strpos($fe, "'rewriteHandle'") !== false ? 'neu (2.0.1)' : 'ALT (1.6.9) - ersetzen!'));
    out('  cms/inc/backend.php   ' . (strpos($be, 'jquery-migrate') !== false || strpos($be, 'preg_replace_callback("\'cms://idfile') !== false ? 'neu (2.0.1)' : 'pruefen / ALT?'));
    out('  cms/editor/ (CKEditor 5) ' . (is_dir($dir . '/cms/editor/ckeditor5') ? 'vorhanden' : 'FEHLT - Ordner aus dem Release kopieren'));
    out('  cms/ckeditor/ (alt)      ' . (is_dir($dir . '/cms/ckeditor') ? 'noch vorhanden (kann geloescht werden)' : 'weg'));
}
if (!$found) {
    out('Kein Projektordner neben backend/ gefunden. Liegt das Projekt (index.php, cms/) woanders? Bitte angeben.');
    out('Hinweis: Auch das Wurzelverzeichnis selbst pruefen: ' . $root . '/index.php');
    $idx = @file_get_contents($root . '/index.php');
    if ($idx !== false) out('  ' . $root . '/index.php: ' . (strpos($idx, '@count($arr)') !== false ? 'ALT (1.6.9) - ersetzen!' : 'ok/unbekannt'));
}

/* ------------------------------------------------------------------ 6. Code in der Datenbank */
title('6. Code in der Datenbank: bekannte PHP-8-Probleme');
$patterns = array(
    'each()'                       => '/\beach\s*\(/',
    'create_function()'            => '/\bcreate_function\s*\(/',
    'split()'                      => '/(?<![\w.>])split\s*\(\s*[\'"]/',
    'mysql_*()'                    => '/\bmysql_[a-z_]+\s*\(/',
    'ereg*()'                      => '/\bereg(i)?(_replace)?\s*\(/',
    'get_magic_quotes_gpc()'       => '/\bget_magic_quotes_(gpc|runtime)\s*\(/',
    '$HTTP_*_VARS'                 => '/\$HTTP_(GET|POST|SERVER|COOKIE|SESSION|ENV)_VARS/',
    'function_exists(name) ohne ""'=> '/\b(function_exists|class_exists)\(\s*[A-Za-z_]\w*\s*\)/',
    '=& new'                       => '/=\s*&\s*new\b/',
    'Short-Tag <?'                 => '/<\?(?=[ \t\r\n])/',
    'Backticks (Shell)'            => '/`[a-z_]+`/',
    'utf8_encode/decode'           => '/\butf8_(en|de)code\s*\(/',
    'session_register()'           => '/\bsession_(register|unregister|is_registered)\s*\(/',
    'mcrypt_*'                     => '/\bmcrypt_/',
    'money_format()'               => '/\bmoney_format\s*\(/',
);
$fields = array(
    array('mod', 'idmod', 'name', array('input', 'output', 'config')),
    array('lay', 'idlay', 'name', array('code')),
);
$issues = 0;
foreach ($fields as $f) {
    $cols = implode(', ', $f[3]);
    $r = mysqli_query($db, "SELECT " . $f[1] . ", " . $f[2] . ($f[0] === 'mod' ? ", idclient, version" : "") . ", " . $cols . " FROM " . $pre . $f[0]);
    if (!$r) { out('  Tabelle ' . $pre . $f[0] . ' nicht lesbar'); continue; }
    while ($row = mysqli_fetch_assoc($r)) {
        $hits = array();
        foreach ($f[3] as $col) {
            $code = (string) $row[$col];
            foreach ($patterns as $label => $rx) {
                if (preg_match_all($rx, $code, $m)) $hits[$label] = (isset($hits[$label]) ? $hits[$label] : 0) + count($m[0]);
            }
        }
        if ($hits) {
            $issues++;
            $label = ($f[0] === 'mod') ? 'Modul #' . $row['idmod'] . ' "' . $row['name'] . '" v' . $row['version'] . ' (Mandant ' . $row['idclient'] . ')' : 'Layout #' . $row['idlay'] . ' "' . $row['name'] . '"';
            $parts = array();
            foreach ($hits as $k => $v) $parts[] = $k . ' x' . $v;
            out('  ' . $label . ': ' . implode(', ', $parts));
        }
    }
}
out($issues ? "\n  -> $issues Eintraege mit Auffaelligkeiten. Standardmodule: Updates aus dem Ordner module/ einspielen." : '  keine Auffaelligkeiten gefunden.');

/* ------------------------------------------------------------------ 7. Fehlerlog */
title('7. Fehlerlog (letzte Eintraege ohne Legacy-Hinweise)');
$logs = array($backend . '/logs/errorlog.txt');
if (ini_get('error_log')) $logs[] = ini_get('error_log');
out('backend/logs beschreibbar: ' . (is_writable($backend . '/logs') ? 'ja' : 'NEIN - der Webserver muss dort schreiben duerfen'));
foreach ($logs as $lf) {
    if (!is_file($lf) || !is_readable($lf)) { out($lf . ': nicht vorhanden/lesbar'); continue; }
    $lines = array_filter(array_slice(file($lf), -400), function ($l) { return strpos($l, '[legacy]') === false && preg_match('/(Fatal|Parse|Warning|Error)/', $l); });
    out($lf . ' (' . count($lines) . ' relevante Zeilen, die letzten 12):');
    foreach (array_slice($lines, -12) as $l) out('  ' . rtrim(substr($l, 0, 400)));
}
out();
out('Fertig. Bitte diese Ausgabe ohne Passwoerter weitergeben. Im Browser-Modus: diagnose.php und logs/.diagnose jetzt loeschen.');
