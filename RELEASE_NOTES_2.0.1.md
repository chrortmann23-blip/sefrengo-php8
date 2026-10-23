# Sefrengo 2.0.1 – PHP-8-Portierung und Sicherheits-Release

Sefrengo 2.0.1 bringt das CMS (Basis: 1.6.9) auf **PHP 8.2 und neuer**, ersetzt die veraltete Authentifizierung und den veralteten Editor und schließt mehrere Sicherheitslücken. Interne Versionsnummer: `02.00.01`.

> **Hinweis zum Teststand:** Entwickelt und getestet wurde mit PHP 8.2.12, MariaDB 10.4 und Google Chrome (automatisiert per Playwright, zusätzlich Lint und Laufzeitprüfungen). Getestet wurde auf einer Neuinstallation und auf einer simulierten 1.6.9-Datenbank. **Eine echte Produktivdatenbank wurde noch nicht migriert.** PHP 8.3 wurde noch nicht ausdrücklich getestet. Bitte vor dem Einsatz ein vollständiges Backup (Dateien und Datenbank) anlegen.

---

## Voraussetzungen

| | |
|---|---|
| PHP | **8.2 oder höher** (PHP 7.x wird nicht mehr unterstützt) |
| PHP-Erweiterungen | mysqli, gd, zlib, mbstring, zip |
| Datenbank | MySQL 5.7+ oder MariaDB 10.4+ |
| Browser | aktuelle Versionen von Chrome, Edge, Firefox, Safari (Cookies müssen aktiviert sein) |

---

## Sicherheit

### Passwörter und Anmeldung
- **Passwort-Hashing:** Passwörter wurden bisher als ungesalzenes `md5()` gespeichert. Sie werden jetzt mit **Argon2id** (Fallback bcrypt) per `password_hash()` abgelegt. Die Spalte `cms_users.password` wurde auf `varchar(255)` erweitert.
- **Automatische Migration:** Bestehende md5-Passwörter funktionieren weiter und werden beim nächsten erfolgreichen Login transparent durch einen modernen Hash ersetzt. Niemand muss sein Passwort ändern.
- **Passwortrichtlinie:** Neue Passwörter benötigen mindestens 10 Zeichen. Die bisherige Beschränkung auf bestimmte Zeichen im Setup entfällt (Sonderzeichen sind erlaubt).
- **Schutz vor Brute-Force:** Nach 5 fehlgeschlagenen Anmeldungen wird das Konto mit wachsender Wartezeit gesperrt (30 Sekunden, verdoppelt sich je weiterem Fehlversuch, maximal 1 Stunde). Die Sperre gilt pro Benutzer, nicht pro IP-Adresse.
- **Zeitgleiche Antwortzeit:** Unbekannte Benutzernamen kosten dieselbe Rechenzeit wie falsche Passwörter (kein Rückschluss, ob ein Benutzer existiert).
- **Passwörter in Logs/Events:** Klartextpasswörter werden nicht mehr in `login_fail`-Events weitergereicht.
- **Frontend-Login-Modul:** Das mitgelieferte Login-Modul verglich das Passwort selbst per SQL gegen `md5()` und hätte mit den neuen Hashes nicht mehr funktioniert. Es nutzt jetzt die gemeinsame, geprüfte Passwortprüfung (inklusive Sperre) und vergibt beim Backend-Login eine neue Session-ID.

### Sessions
- **Zufällige Session-IDs:** Statt `md5(uniqid())` werden IDs mit `random_bytes()` erzeugt, ebenso Tokens und Challenges.
- **Cookie-Härtung:** `HttpOnly`, `SameSite` (Backend: `Strict`, Frontend: `Lax`) und `Secure` bei HTTPS.
- **Session-Fixation:** Nach erfolgreichem Login wird eine neue Session-ID vergeben, die alte gelöscht.
- **Keine Session-ID in URLs:** Das Backend arbeitet ausschließlich mit Cookies (URL-Sessions konnten über Referer, Logs und Browserverlauf abfließen). Auch im Datei-/Link-Browser und im Editor wird die Session-ID nicht mehr in URLs übergeben.
- **Kein `eval()` bei Sessiondaten:** Die Session wurde bisher als PHP-Code in der Datenbank gespeichert und beim Laden per `eval()` ausgeführt. Wer die Tabelle `cms_sessions` manipulieren konnte, konnte so Code ausführen. Gespeichert wird jetzt per `serialize()` und gelesen per `unserialize()` ohne Klassen (kein Object-Injection-Risiko).

### Schutz vor Angriffen auf Anfragen
- **CSRF:** Schreibende Anfragen (POST) von fremden Seiten werden abgelehnt (Same-Origin-Prüfung über `Origin`/`Referer`/`Sec-Fetch-Site`). Zusätzlich verhindert das `SameSite=Strict`-Cookie des Backends, dass das Backend-Cookie bei Fremdseiten-Aufrufen mitgeschickt wird.
- **Sicherheits-Header:** `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN` (Backend) und HSTS bei HTTPS.
- **Fehlerausgaben:** Fehlermeldungen werden Besuchern nicht mehr angezeigt (`display_errors` aus), sondern in `backend/logs/errorlog.txt` protokolliert.

### Behobene Schwachstellen im Code
- **Code-Ausführung über Request-Parameter (kritisch):** Im Dateimanager, in den Bereichen Stylesheet und JavaScript sowie im Verzeichnisabgleich wurde der Parameter `action` per `eval('$errno = upl_'.$action.'();')` als PHP-Code ausgeführt. Jeder angemeldete Benutzer mit Zugriff auf einen dieser Bereiche konnte beliebigen Code einschleusen. Die Aufrufe erfolgen jetzt nur noch für vorhandene Funktionen mit geprüftem Namen.
- **Code-Ausführung über Dateierweiterung:** Die Dateiformat-Erweiterung (`fileaddon`) wurde per `eval()` instanziiert. Ersetzt durch geprüfte, direkte Instanziierung.
- **Unsichere Deserialisierung im Datei-/Link-Browser:** Die Konfiguration des Browsers wurde unsigniert per URL übergeben und mit `unserialize()` gelesen (Object-Injection). Sie ist jetzt **HMAC-signiert** (an die Session gebunden), wird ohne Klassen gelesen, die Klassennamen werden eingeschränkt.
- **Cross-Site-Scripting im Datei-/Link-Browser:** Der Name der JavaScript-Rückruffunktion aus der URL wurde ungeprüft in JavaScript ausgegeben. Er wird jetzt gegen ein festes Muster validiert.
- **Unsichere Deserialisierung und reflektiertes XSS in der alten Editor-Anbindung:** `ckconfig.php` las eine per URL übergebene, serialisierte Konfiguration und gab den Editornamen ungefiltert in JavaScript aus. Die gesamte Anbindung entfällt mit dem Wechsel auf CKEditor 5.
- **Unsichere Deserialisierung im Artikelsystem:** Gespeicherte Moduleinstellungen werden nur noch ohne Klassen deserialisiert.
- **Shell-Ausführung im Navigationsmodul:** Das Modul verwendete Backticks (`` `start` ``), die einen Systembefehl ausführen. Gemeint war eine Zeichenkette.
- **Zufallswerte:** Schlüssel und Hashes (`lib_make_key`, Artikel-/Kategorie-Hashes) verwenden `random_bytes()` statt `md5(time())`.
- **SQL-Escaping:** An einigen zentralen Stellen (Login-Abfrage, Modul-Verwaltung, Frontend-Einstieg, Benutzerdaten-Laden) wird jetzt `mysqli_real_escape_string()` über die aktive Verbindung statt `addslashes()` verwendet. Das ist keine Umstellung auf Prepared Statements (siehe verbleibende Risiken).

### Veraltete Komponenten mit bekannten Sicherheitslücken ersetzt oder entfernt
| Komponente | Vorher | Jetzt |
|---|---|---|
| WYSIWYG-Editor | CKEditor 4.11.4 (2018, nicht mehr gepflegt, bekannte XSS-Lücken) | **CKEditor 5 v49** (GPL) |
| JavaScript-Bibliothek | jQuery 1.10.2 (2013, bekannte XSS-Lücken) | **jQuery 3.7.1** (+ jquery-migrate 3.5.2) |
| Artikelsystem (Plugin) | eigenes jQuery 1.2.1 (2007) | entfernt, nutzt das Backend-jQuery |
| Tooltips | qTip2 2.1.1 | qTip2 3.0.3 |
| Bildladen | imagesLoaded 2.1.1 | imagesLoaded 5.0.0 |
| Mailversand im Artikelsystem | PHPMailer 2.0 rc3 (ungenutzt, bekannte Schwachstellen) | entfernt |
| Editor im Artikelsystem | TinyMCE 3 (mitgeliefert) | entfernt, CKEditor 5 |

### Bekannte verbleibende Risiken (bewusst nicht Teil dieses Releases)
- Das Backend übernimmt `GET`/`POST`-Parameter weiterhin per `extract()` als Variablen (historische „register_globals“-Emulation).
- Ein großer Teil der SQL-Abfragen wird weiterhin aus Zeichenketten zusammengesetzt (Escaping, aber keine durchgängigen Prepared Statements). Das betrifft auch einige mitgelieferte Module (z. B. Suchmodul, Navigation).
- Module, Layouts und Templates werden systembedingt per `eval()` ausgeführt (Designentscheidung von Sefrengo).
- Kein Anmelde-Limit pro IP-Adresse, keine Content-Security-Policy, kein CSRF-Token pro Formular (Schutz erfolgt über `SameSite=Strict` und Same-Origin-Prüfung).
- Facebook-/Twitter-Export des Artikelsystems nutzt abgeschaltete API-Versionen.

---

## Portierung auf PHP 8

### Kern
- Entfernte Sprachfunktionen ersetzt: `each()`, `create_function()`, `split()`, Magic Quotes, String-Offsets mit geschweiften Klammern (`$s{0}`), `utf8_encode`/`utf8_decode` und `strftime` (veraltet).
- **Undefinierte Konstanten** (in PHP 7 nur eine Warnung, in PHP 8 ein Fehler) beseitigt, z. B. `$cms_db[sessions]`, `$cms_db[content]`, `function_exists(name)`, `class_exists(name)`, `date(n)`, Property-Defaults wie `var $x = string;`.
- Nicht kompatible Methoden-Signaturen und statische Aufrufe in den mitgelieferten PEAR-Paketen korrigiert (PEAR, HTML_QuickForm, HTML_Template_IT, Image_Transform, Pager, Mail, Archive_Tar).
- **mysqli:** Die Ausnahmen (`MYSQLI_REPORT_ERROR`) von PHP 8.1 sind abgeschaltet, da der Code Fehler selbst auswertet. Jede Verbindung setzt `sql_mode=''` (das Schema nutzt `0000-00-00`-Datumswerte). Fehler bei noch nicht geöffneter Verbindung (`mysqli_escape_string(null, …)`) behoben, ebenso die Fehlerbehandlung in `next_record()`.
- **Kompatibilitätsschicht** `backend/inc/inc.compat8.php`: PHP 8 wirft bei `null` oder Skalaren in Array-Funktionen einen TypeError (PHP 7 nur eine Warnung). Rund 800 Aufrufe in Kern und Plugin (`count`, `in_array`, `implode`, `array_merge`, `array_push`, `reset`, `sort`, …) nutzen jetzt gleichwertige Wrapper (`sf_count()` usw.), ebenso Ersatzfunktionen für `strftime` (de, fr, es, it, nl, en) und `utf8_encode` (ohne doppelte Kodierung).
- Stille Verhaltensänderungen abgefangen (z. B. `'' == 0` ist in PHP 8 `false`).
- Optionale Parameter vor Pflichtparametern, `=& new`, `continue` in `switch`, `${var}`-Strings und weitere Deprecations bereinigt.
- **Zentrale Fehlerbehandlung** (`backend/inc/inc.errorhandler.php`): Frühere Notices („Undefined variable/array key/property“), die PHP 8 zu Warnungen aufgewertet hat, werden wie unter PHP 7 nicht angezeigt. Echte Fehler bleiben sichtbar. Entwicklungsmodus: Datei `backend/logs/.display_errors` zeigt Fehler an, `backend/logs/.debug` protokolliert zusätzlich die Legacy-Notices.
- Backend-Login-Seite: Fehlermeldungen beseitigt, `autocomplete`-Attribute, moderne Zeichenkodierung, `viewport`.
- Die Short-Open-Tag-Abhängigkeit (`<?`) des Artikelsystems wurde beseitigt (läuft jetzt auch bei `short_open_tag=Off`).

### Setup und Datenbank
- **Fehlende Datei ergänzt:** `setup/sql/config.sql` fehlte im Paket (Neuinstallation war unmöglich). Sie wurde aus dem Upstream-Repository `sefrengo-cms/sefrengo-1.x` übernommen und auf ISO-8859-1 umgestellt. Bitte bei Bedarf mit Ihrer Version abgleichen.
- Neue Version `02.00.01`, neue Update-Datei `setup/sql/updates_from.02.00.01.sql` (Spalte `password` auf 255 Zeichen, Versionsnummer).
- Update-Logik des Setups vergleicht jetzt die Gesamtversion statt jede Teilnummer einzeln.
- Voraussetzungs-Prüfung im Setup auf PHP 8.2 angepasst, „Safe Mode“-Prüfung entfernt.
- Setup behandelt das Admin-Passwort sicher (Hash statt md5, Ausgabe in Formularen HTML-escaped).
- Debug-Ausgabe im Setup entfernt.
- Neue Installationen setzen die Datenbankverbindung explizit auf UTF-8 (`db_utf8 = true`).
- Die Medienverzeichnisse `img/`, `pdf/`, `swf/` und `zip/` sind bei Neuinstallationen bereits im Dateimanager registriert (vorher: „Das Zielverzeichnis wurde nicht gefunden“, bis der Verzeichnisabgleich einmal manuell gestartet wurde).

### Editor und Oberfläche
- **CKEditor 5** (GPL-2.0-or-later) mit deutscher, englischer und französischer Oberfläche. Erhalten bleiben: Formatierung, Tabellen, Bilder, Quelltextmodus, Schriftarten/-größen/-farben, Ausrichtung, Suchen/Ersetzen, Sonderzeichen. Die Sefrengo-Anbindung wurde neu geschrieben: Der **Sefrengo-Datei- und Seitenlink-Browser** wird über eigene Schaltflächen angesprochen, interne Links bleiben `cms://idcatside=…`, ausgewählte Styles werden unterstützt, beliebiges HTML wird nicht verworfen (General HTML Support).
- **CodeMirror 5** ersetzt die zweckentfremdete CKEditor-4-Quelltextansicht im Layout- und JavaScript-Editor (Syntaxhervorhebung, Zeilennummern, Klammer- und Tag-Vervollständigung).
- Inhalte werden in den Editor- und Code-Feldern korrekt HTML-escaped (vorher gingen Entitäten und `</textarea>` im Inhalt verloren bzw. konnten das Formular stören).
- Datei-/Link-Browser und Backend funktionieren ohne JavaScript-Fehler in aktuellen Browsern.

---

## Plugins und Module

### Artikelsystem 1.7.7 (Plugin)
Datei: `plugins/articlesystem177_php8.cmsplugin` (Basis: 1.7.6)
- CKEditor 5 statt TinyMCE/CKEditor 4, auch für WYSIWYG-Zusatzfelder; TinyMCE-Dateien entfernt (Paket von 1,3 MB auf 0,67 MB).
- Eigenes jQuery 1.2.1 entfernt (hatte das jQuery des Backends überschrieben und dessen Skripte zerstört). Namenskonflikt mit dem Backend-Namensraum `SF` beseitigt.
- PHP-8-Korrekturen: Short-Tags, `setlocale()` mit Kategorienamen, `date(n)`, `=& new`, `strftime`/`utf8_encode`, Vergleiche mit leeren Werten (z. B. Gültigkeitsdatum), `unserialize()` abgesichert, ungenutztes PHPMailer entfernt, Snoopy ohne `each()`.
- Bilder und Dateien werden mit echten Pfaden eingefügt (das öffentliche Frontend löst `cms://idfile=` nicht auf).
- Neue Installation und Frontend-Ausgabe getestet. Facebook-/Twitter-Export sind weiterhin auf den alten API-Versionen aufgebaut.

### Mitgelieferte Standardmodule (26 Stück, in `setup/sql/standard.sql`)
Alle 26 Module wurden einzeln in Frontend und Bearbeitungsansicht geprüft. Korrigiert und in der Version erhöht wurden neun Module. Importpakete liegen in `module/`:

| Modul | Neue Version | Wesentliche Korrekturen |
|---|---|---|
| URHere | 1.2 | PHP-8-Funktionsaufrufe |
| Kontaktformular | 2.4 | `class_exists`, `continue` in `switch`, Magic Quotes, Signatur |
| Listen-Navigation | 1.2 | `function_exists`, Funktionsaufrufe |
| Navigation | 2.02.00 | Shell-Backticks, Funktionsaufrufe |
| Sprachauswahl | 2.1 | Funktionsaufrufe |
| Login | 2.3 | Passwortprüfung (Argon2id), Session-Fixation, keine Session-ID in URLs |
| Suchmodul | 1.4 | Funktionsaufrufe |
| ContentFlex | 1.9 | `function_exists`, Funktionsaufrufe |
| Pic-Galerie | 3.6 | Short-Tag, nackte Konstanten, `function_exists` |

Außerdem enthalten (`module/`): `Artikelsystem175_php8.cmsmod` (1.7.6), `Kontaktformular_php8.cmsmod` (2.4), `Kontaktformular_v02_03_0_php8.cmsmod` (2.3.1) und `doiMenu 1.5.3_php8.cmsmod` (1.1).
Hinweise: Das Modul **doiMenu** benötigt weiterhin seine eigene JavaScript-Bibliothek (`TMainMenu`), die im Layout eingebunden sein muss und nicht Teil des Pakets ist. `mailprotector.cmsplugin` ist ein leeres Archiv.

---

## Update-Hinweise

1. Vollständiges Backup (Dateien und Datenbank) anlegen.
2. Auf einem Testsystem mit **PHP 8.2+** beginnen. Eine parallele Testinstanz mit Kopie der Datenbank wird empfohlen.
3. Dateien austauschen, `backend/inc/config.php` behalten, das Setup im Modus **Update** ausführen (aktualisiert die Datenbank), danach den Ordner `setup/` löschen.
4. **Alle Benutzer müssen sich nach dem Update neu anmelden** (neues Session-Format).
5. **Cookies müssen aktiviert sein.** Wegen `SameSite=Strict` erscheint nach einem Klick von einer fremden Seite ins Backend zunächst die Login-Seite. Hinter einem Reverse-Proxy muss der `Host`-Header korrekt weitergegeben werden, sonst blockiert die Same-Origin-Prüfung Anmeldungen.
6. Die geänderten Standardmodule über die mitgelieferten Pakete aktualisieren (Dialog „Update gefunden“: „Modul aktualisieren“ und „Modulkonfiguration behalten“ ankreuzen), das Artikelsystem über `articlesystem177_php8.cmsplugin`.
7. **Zeichensatz prüfen:** Falls Umlaute im Backend als „Ã¶“/„Ã¼“ erscheinen, stimmen Zeichensatz der Verbindung und der gespeicherten Daten nicht überein. Bitte vor dem Livegang klären, wie die Datenbank tatsächlich kodiert ist.
8. Die Datei `backend/logs/errorlog.txt` ist nach dem Update die erste Anlaufstelle bei Problemen.

## Bekannte Einschränkungen
- Das CSS des Layouts wird im Bearbeitungsbereich von CKEditor 5 nicht mehr angewendet (der Editor läuft nicht mehr in einem iframe).
- Das fehlende Dekorbild `bg_search_btn.png` führt in der Benutzerverwaltung zu einem 404 (war schon vorher so).
- Der Update-Pfad wurde nur auf einer simulierten 1.6.9-Datenbank getestet.
- Frames bleiben die Grundlage des Backends.
