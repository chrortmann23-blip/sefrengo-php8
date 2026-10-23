<?php
// File: fnc.editor.php
// +----------------------------------------------------------------------+
// | Sefrengo CMS - editor integration                                     |
// |   rich text : CKEditor 5 (projekt01/cms/editor/ckeditor5)             |
// |   code      : CodeMirror (projekt01/cms/editor/codemirror)            |
// +----------------------------------------------------------------------+

if (!defined('SF_EDITOR_VERSION')) {
    define('SF_EDITOR_VERSION', '49.0.0');
}

/**
 * Base url of the editor assets of the current client.
 */
function sf_editor_base_url()
{
    global $cfg_client;

    return $cfg_client['htmlpath'] . 'cms/editor/';
}

/**
 * Language used for the editor ui (translations exist for de, en, fr).
 */
function sf_editor_language()
{
    global $cfg_cms;

    $l = isset($cfg_cms['backend_lang']) ? strtolower($cfg_cms['backend_lang']) : 'en';

    return sf_in_array($l, array('de', 'en', 'fr'), true) ? $l : 'en';
}

/**
 * Script and style includes. Every group is only emitted once per request.
 *
 * @param string $what 'rich' or 'code'
 */
function sf_editor_assets($what)
{
    static $done = array();

    if (isset($done[$what])) {
        return '';
    }
    $done[$what] = true;
    $base = sf_editor_base_url();
    $v = '?v=' . SF_EDITOR_VERSION;
    $out = '';

    if (empty($done['core'])) {
        $done['core'] = true;
        $out .= '<link rel="stylesheet" href="' . $base . 'sefrengo-editor.css' . $v . '" />' . "\n";
        $out .= '<script src="' . $base . 'sefrengo-editor.js' . $v . '"></script>' . "\n";
    }

    if ($what === 'rich') {
        $lang = sf_editor_language();
        $out .= '<link rel="stylesheet" href="' . $base . 'ckeditor5/ckeditor5.css' . $v . '" />' . "\n";
        $out .= '<script src="' . $base . 'ckeditor5/ckeditor5.umd.js' . $v . '"></script>' . "\n";
        $out .= '<script src="' . $base . 'ckeditor5/translations/' . $lang . '.umd.js' . $v . '"></script>' . "\n";
        // register the translation (the UMD bundle of the language adds itself to CKEDITOR_TRANSLATIONS)
    } else {
        $out .= '<link rel="stylesheet" href="' . $base . 'codemirror/codemirror.css' . $v . '" />' . "\n";
        foreach (array(
            'codemirror/codemirror.js',
            'codemirror/mode/xml/xml.js',
            'codemirror/mode/javascript/javascript.js',
            'codemirror/mode/css/css.js',
            'codemirror/mode/htmlmixed/htmlmixed.js',
            'codemirror/mode/clike/clike.js',
            'codemirror/mode/php/php.js',
            'codemirror/addon/matchbrackets.js',
            'codemirror/addon/closebrackets.js',
            'codemirror/addon/closetag.js',
            'codemirror/addon/active-line.js',
        ) as $file) {
            $out .= '<script src="' . $base . $file . $v . '"></script>' . "\n";
        }
    }

    return $out;
}

/**
 * Maps the feature list of a module (comma separated) to the toolbar of the editor.
 * Empty or "true" enables everything.
 *
 * @param string $feature_string
 * @param bool   $has_styles
 * @return array toolbar items
 */
function sf_editor_toolbar($feature_string, $has_styles = false)
{
    $feature_string = str_replace(' ', '', strtolower((string) $feature_string));
    $all = ($feature_string === '' || $feature_string === 'true');
    $f = explode(',', $feature_string);
    $has = function ($name) use ($all, $f) {
        return $all || sf_in_array($name, $f, true);
    };

    $groups = array();
    $g = array();
    if ($has('undo')) { $g[] = 'undo'; $g[] = 'redo'; }
    $groups[] = $g; $g = array();
    if ($has('search')) { $g[] = 'findAndReplace'; }
    $groups[] = $g; $g = array();
    if ($has('font')) { $g[] = 'heading'; $g[] = 'fontFamily'; }
    if ($has('styles') && $has_styles) { $g[] = 'style'; }
    if ($has('fontsize')) { $g[] = 'fontSize'; }
    $groups[] = $g; $g = array();
    if ($has('bold')) { $g[] = 'bold'; }
    if ($has('italic')) { $g[] = 'italic'; }
    if ($has('underline')) { $g[] = 'underline'; }
    if ($has('strikethrough')) { $g[] = 'strikethrough'; }
    if ($has('subscript')) { $g[] = 'subscript'; }
    if ($has('superscript')) { $g[] = 'superscript'; }
    if ($has('striptag')) { $g[] = 'removeFormat'; }
    $groups[] = $g; $g = array();
    if ($has('fontcolor')) { $g[] = 'fontColor'; }
    if ($has('backgroundcolor')) { $g[] = 'fontBackgroundColor'; }
    $groups[] = $g; $g = array();
    if ($has('align')) { $g[] = 'alignment'; }
    $groups[] = $g; $g = array();
    if ($has('list')) { $g[] = 'bulletedList'; $g[] = 'numberedList'; }
    if ($has('indent')) { $g[] = 'outdent'; $g[] = 'indent'; $g[] = 'blockQuote'; }
    $groups[] = $g; $g = array();
    if ($has('link') || $has('file')) { $g[] = 'sfLink'; }
    if ($has('link')) { $g[] = 'link'; }
    if ($has('image')) { $g[] = 'sfImage'; }
    if ($has('table')) { $g[] = 'insertTable'; }
    if ($has('hr')) { $g[] = 'horizontalLine'; }
    if ($has('specialchars')) { $g[] = 'specialCharacters'; }
    $groups[] = $g; $g = array();
    if ($has('changemode')) { $g[] = 'sourceEditing'; }
    $groups[] = $g;

    $items = array();
    foreach ($groups as $group) {
        if (sf_count($group) > 0) {
            if (sf_count($items) > 0) {
                $items[] = '|';
            }
            foreach ($group as $item) {
                $items[] = $item;
            }
        }
    }

    return $items;
}

/**
 * Builds the url of the file / link browser popup for the editor.
 *
 * @param string $kind 'link' (files + internal pages) or 'image'
 * @param array  $type_config values of the cms tag
 * @return string url (empty if the browser classes are not available)
 */
function sf_editor_browser_url($kind, $type_config)
{
    global $sf_factory;

    if (!is_object($sf_factory)) {
        return '';
    }
    $conf_string = function ($key) use ($type_config) {
        return isset($type_config[$key]) ? $type_config[$key] : '';
    };
    $to_array = function ($string) {
        if (empty($string) || $string == 'true' || $string == 'false') {
            return array();
        }
        if (substr($string, 0, 1) == ',') {
            $string = substr($string, 1);
        }
        $arr = explode(',', str_replace(' ', '', $string));

        return sf_array_values(array_filter($arr, function ($v) { return $v !== ''; }));
    };

    $rb = $sf_factory->getObjectForced('GUI', 'RessourceBrowser');
    $res_file = $sf_factory->getObjectForced('GUI/RESSOURCES', 'FileManager');

    $return_mode = $conf_string('returnmode');
    if ($kind === 'image') {
        $types = $conf_string('imagefiletypes');
        $res_file->setFiletypes($to_array(($types == 'true' || empty($types)) ? 'jpg,jpeg,gif,png,webp,svg' : $types));
        $res_file->setFolderIds($to_array($conf_string('imagefolders')));
        $res_file->setWithSubfoders($conf_string('imagesubfolders') != 'false');
        if ($return_mode !== '') { $res_file->setReturnValueMode($return_mode); }
        $rb->addRessource($res_file);
    } else {
        $res_file->setFiletypes($to_array($conf_string('filefiletypes')));
        $res_file->setFolderIds($to_array($conf_string('filefolders')));
        $res_file->setWithSubfoders($conf_string('filesubfolders') != 'false');
        if ($return_mode !== '') { $res_file->setReturnValueMode($return_mode); }
        $rb->addRessource($res_file);
        $rb->addRessource($sf_factory->getObjectForced('GUI/RESSOURCES', 'InternalLink'));
    }
    // the popup calls window.opener.SFEditor.pick(funcNum, picked_value)
    $rb->setJSCallbackFunction('SFEditor.pick', array('funcNum', 'picked_value'));

    return $rb->exportConfigURL();
}

/**
 * Options for SFEditor.rich() built from the values of a cms tag / plugin settings.
 *
 * @param array $type_config features, selectablestyles, height, returnmode, imagefolders/filefolders ... browser settings
 * @return array
 */
function sf_editor_options($type_config)
{
    $has_styles = false;
    $style_defs = array();
    if (!empty($type_config['selectablestyles']) && function_exists('_type_get_stylelist')) {
        $styles = _type_get_stylelist($type_config['selectablestyles']);
        if (is_array($styles)) {
            foreach ($styles as $v) {
                $style_defs[] = array(
                    'name'    => $v['autodesc'],
                    'element' => 'span',
                    'classes' => array($v['name']),
                );
            }
        }
        $has_styles = count($style_defs) > 0;
    }

    $features = isset($type_config['features']) ? $type_config['features'] : '';
    $lang = sf_editor_language();
    $labels = array(
        'de' => array('link' => 'Link auf Seite oder Datei w&auml;hlen', 'image' => 'Bild aus Dateimanager einf&uuml;gen', 'newtab' => 'In neuem Fenster &ouml;ffnen'),
        'en' => array('link' => 'Choose page or file link', 'image' => 'Insert image from file manager', 'newtab' => 'Open in new tab'),
        'fr' => array('link' => 'Choisir une page ou un fichier', 'image' => 'Ins&eacute;rer une image du gestionnaire', 'newtab' => 'Ouvrir dans un nouvel onglet'),
    );

    return array(
        'toolbar' => sf_editor_toolbar($features, $has_styles),
        'lang'    => $lang,
        'height'  => !empty($type_config['height']) ? $type_config['height'] : '400px',
        'styles'  => $style_defs,
        'labels'  => array_map(function ($s) { return html_entity_decode($s, ENT_QUOTES, 'UTF-8'); }, $labels[$lang]),
        'browse'  => array(
            'link'  => sf_editor_browser_url('link', $type_config),
            'image' => sf_editor_browser_url('image', $type_config),
        ),
    );
}

/**
 * Script that starts the rich text editor for existing textareas.
 *
 * @param array|string $ids      id of a textarea or a list of ids
 * @param array        $opts     result of sf_editor_options()
 * @param string       $selector optional css selector for further textareas (e.g. "textarea.mceEditor")
 * @return string assets + script
 */
function sf_editor_rich_init($ids, $opts, $selector = '')
{
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $out = sf_editor_assets('rich');
    $out .= '<script>' . "
";
    $out .= '(function () {' . "
";
    $out .= '  var opts = ' . json_encode($opts, $flags) . ';' . "
";
    $out .= '  var ids = ' . json_encode(array_values((array) $ids), $flags) . ';' . "
";
    $out .= '  var selector = ' . json_encode((string) $selector, $flags) . ';' . "
";
    $out .= '  var start = function () {' . "
";
    $out .= '    var done = {};' . "
";
    $out .= '    ids.forEach(function (id) { if (id && !done[id]) { done[id] = 1; SFEditor.rich(id, opts); } });' . "
";
    $out .= '    if (selector) { Array.prototype.forEach.call(document.querySelectorAll(selector), function (el) { if (el.id && !done[el.id]) { done[el.id] = 1; SFEditor.rich(el.id, opts); } }); }' . "
";
    $out .= '  };' . "
";
    $out .= '  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", start); } else { start(); }' . "
";
    $out .= '})();' . "
";
    $out .= '</script>' . "
";

    return $out;
}

/**
 * Rich text editor (textarea + CKEditor 5 init script).
 *
 * @param string $formname name/id of the textarea
 * @param string $content  html content, already html-escaped by the caller (inc.con_edit.php) for use inside a textarea
 * @param array  $type_config values of the cms tag
 * @return string html (table cell)
 */
function sf_editor_rich($formname, $content, $type_config)
{
    $name = htmlspecialchars($formname, ENT_QUOTES, 'UTF-8');
    $out = '    <td class="sf-editor-cell"><textarea name="' . $name . '" id="' . $name . '" rows="30" cols="52">' . $content . '</textarea></td>' . "
";
    $out .= sf_editor_rich_init($formname, sf_editor_options($type_config));

    return $out;
}

/**
 * Code editor (textarea + CodeMirror).
 *
 * @param string $formname id of the existing textarea
 * @param string $mode     CodeMirror mode: 'application/x-httpd-php', 'javascript', 'css', 'htmlmixed'
 * @param string $height
 * @return string html (assets and init script only, the textarea has to exist)
 */
function sf_editor_code($formname, $mode = 'application/x-httpd-php', $height = '450px')
{
    $out = sf_editor_assets('code');
    $out .= '<script>' . "\n";
    $out .= '(function () {' . "\n";
    $out .= '  var start = function () { SFEditor.code(' . json_encode($formname) . ', ' . json_encode(array('mode' => $mode, 'height' => $height)) . '); };' . "\n";
    $out .= '  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", start); } else { start(); }' . "\n";
    $out .= '})();' . "\n";
    $out .= '</script>' . "\n";

    return $out;
}
