# Sefrengo 2.0.2 – Korrekturen nach den ersten Produktiv-Updates

Fehlerkorrekturen auf Basis der ersten echten Updates von Sefrengo 1.6.9 auf 2.0.x. Interne Versionsnummer: `02.00.02`. Die Voraussetzungen (PHP 8.2 oder höher, mysqli, gd, zlib, mbstring, zip) und alle Neuerungen aus [2.0.1](RELEASE_NOTES_2.0.1.md) gelten unverändert.

> **Hinweis zum Teststand:** Getestet mit PHP 8.2.12, MariaDB 10.4 und Google Chrome auf einer Testinstanz. Die Korrekturen sind auf dem Echtsystem noch nicht bestätigt. Bitte vor dem Update ein vollständiges Backup (Dateien und Datenbank) anlegen.

---

## Neu

- **Einstellbarer Zeichensatz der Datenbankverbindung (`db_charset`).** In `backend/inc/config.php` lässt sich jetzt `$cfg_cms['db_charset'] = 'latin1';` (oder `utf8`, `utf8mb4`) setzen. Das behebt falsch dargestellte Umlaute (`Ã¤`, `Ã¼`) bei Installationen, deren Datenbankinhalt noch latin1 ist, während PHP 8 / mysqli standardmäßig utf8mb4 verwendet. Die alte Option `db_utf8 = true` funktioniert weiter. Neue Installationen schreiben beide Werte in die `config.php`.
- **Diagnose-Skript `tools/diagnose.php`.** Es prüft nur lesend: Umgebung, Zeichensatz-Empfehlung, veraltete Client-Dateien (`index.php`, `cms/inc/frontend.php`, `cms/inc/backend.php`, `cms/editor/`), Programmcode in der Datenbank (Module, Layouts) und das Fehlerlog. Das Skript nach der Benutzung unbedingt wieder löschen.

## Korrigiert

- **Download von CSS- und JS-Dateien im Backend lieferte leere Dateien.** Der Pfad wurde aus einer URL (`http://…`) statt aus dem Dateisystempfad gebaut und per HTTP gelesen. Das schlägt fehl, wenn `allow_url_fopen` ausgeschaltet ist oder der Server sich nicht selbst erreicht. Es wird jetzt der Dateisystempfad verwendet.
- **Der Download sucht die Datei an mehreren möglichen Orten** (übergebener Pfad, Upload-Pfad, Mandantenpfad, Pfad über `DOCUMENT_ROOT`). Findet er sie nicht, erscheint eine Meldung mit den geprüften Pfaden, die auch im Fehlerlog steht. Vorher kam eine leere Datei oder gar keine Rückmeldung.

- **Fataler Fehler beim Konfigurieren eines Ordners** (`inc.con_configcat.php`, Zeile 208, `Cannot access offset of type string on string`): Das Rechte-Panel gibt einen leeren String zurück, wenn es keine Gruppen gibt; darauf wurde unter PHP 8 wie auf ein Array zugegriffen. Wird jetzt geprüft.

## Hinweise für das Update

- Nach dem Update müssen sich **alle Backend-Nutzer einmal aus- und wieder einloggen.** Sitzungen aus Version 1.6.9 sind in 2.x nicht gültig. Das äußert sich zum Beispiel darin, dass im Frontend-Edit-Modus die Stifte sichtbar sind, das Menü aber nicht aufgeht.
- Backend und Seite immer über dieselbe Adresse aufrufen (gleicher Host, gleiches `www`, gleiches Protokoll). Die Session-Cookies gelten nur für genau diesen Host.
- Dateien vollständig ersetzen, außer `backend/inc/config.php` und den eigenen Inhalten (`cms/css`, `cms/js`, `media`). Danach die PHP-Version der Domain auf 8.2 oder 8.3 stellen und PHP neu starten, damit ein alter OPcache nicht den alten Code ausliefert.
- Fehlen im Ordner `cms/css/` oder `cms/js/` auf dem Server die Dateien, die in der Datenbank eingetragen sind, dann wurden sie nicht mitkopiert. Das ist kein Fehler im Download. Die Dateien aus dem Altsystem per FTP übertragen.
