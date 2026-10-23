/**
 * Sefrengo editor integration
 *
 *  - SFEditor.rich(id, options): rich text editing with CKEditor 5 (replaces the textarea)
 *  - SFEditor.code(id, options): code editing with CodeMirror (layouts, javascript files)
 *  - SFEditor.pick(funcNum, value): callback used by the Sefrengo file / link browser popup
 *
 * CKEditor 5:  GPL-2.0-or-later, https://ckeditor.com
 * CodeMirror 5: MIT, https://codemirror.net
 */
(function (window, document) {
    'use strict';

    var pickCallbacks = {};
    var pickCounter = 0;
    var editors = {};

    function openBrowser(url, onPick) {
        var num = ++pickCounter;
        pickCallbacks[num] = onPick;
        var w = 800, h = 600;
        var left = Math.max(0, Math.round((window.screen.width - w) / 2));
        var top = Math.max(0, Math.round((window.screen.height - h) / 2));
        var sep = url.indexOf('?') === -1 ? '?' : '&';
        var win = window.open(url + sep + 'CKEditorFuncNum=' + num, 'sf_rb_' + num,
            'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
        if (win) {
            win.focus();
        }
    }

    /** called from the browser popup: window.opener.SFEditor.pick(funcNum, picked_value) */
    function pick(funcNum, value) {
        var cb = pickCallbacks[funcNum];
        delete pickCallbacks[funcNum];
        if (typeof cb === 'function') {
            cb(value);
        }
    }

    function cleanUrl(value) {
        return String(value || '').replace(/^\s+|\s+$/g, '');
    }

    /* ---------------------------------------------------------------- CKEditor 5 */

    function sfBrowsePlugin(CK, opts) {
        var Plugin = CK.Plugin;
        var ButtonView = CK.ButtonView;

        return class SefrengoBrowse extends Plugin {
            static get pluginName() {
                return 'SefrengoBrowse';
            }

            init() {
                var editor = this.editor;
                var t = editor.t;

                if (opts.browse && opts.browse.link) {
                    editor.ui.componentFactory.add('sfLink', function (locale) {
                        var view = new ButtonView(locale);
                        view.set({
                            label: opts.labels.link,
                            icon: CK.IconBrowseFiles || CK.IconLink,
                            tooltip: true
                        });
                        view.on('execute', function () {
                            openBrowser(opts.browse.link, function (value) {
                                value = cleanUrl(value);
                                if (!value) {
                                    return;
                                }
                                var sel = editor.model.document.selection;
                                if (sel.isCollapsed) {
                                    editor.model.change(function (writer) {
                                        var text = writer.createText(value, { linkHref: value });
                                        editor.model.insertContent(text);
                                    });
                                } else {
                                    editor.execute('link', value);
                                }
                                editor.editing.view.focus();
                            });
                        });
                        return view;
                    });
                }

                if (opts.browse && opts.browse.image) {
                    editor.ui.componentFactory.add('sfImage', function (locale) {
                        var view = new ButtonView(locale);
                        view.set({
                            label: opts.labels.image,
                            icon: CK.IconImage || CK.IconImageUpload,
                            tooltip: true
                        });
                        view.on('execute', function () {
                            openBrowser(opts.browse.image, function (value) {
                                value = cleanUrl(value);
                                if (!value) {
                                    return;
                                }
                                editor.execute('insertImage', { source: value });
                                editor.editing.view.focus();
                            });
                        });
                        return view;
                    });
                }
            }
        };
    }

    function rich(id, opts) {
        var CK = window.CKEDITOR;
        var el = document.getElementById(id);
        if (!el || !CK || !CK.ClassicEditor) {
            return null;
        }
        opts = opts || {};
        opts.labels = opts.labels || { link: 'Link', image: 'Image' };

        var plugins = [
            CK.Essentials, CK.Paragraph, CK.Heading, CK.Bold, CK.Italic, CK.Underline, CK.Strikethrough,
            CK.Subscript, CK.Superscript, CK.RemoveFormat, CK.Alignment, CK.Link, CK.AutoLink, CK.List,
            CK.Indent, CK.BlockQuote, CK.Table, CK.TableToolbar, CK.TableProperties, CK.TableCellProperties,
            CK.TableColumnResize, CK.HorizontalLine, CK.SpecialCharacters, CK.SpecialCharactersEssentials,
            CK.Font, CK.FindAndReplace, CK.SourceEditing, CK.PasteFromOffice,
            CK.Image, CK.ImageInsert, CK.ImageResize, CK.ImageStyle, CK.ImageToolbar, CK.ImageCaption,
            CK.LinkImage, CK.GeneralHtmlSupport, CK.HtmlComment, CK.ShowBlocks,
            sfBrowsePlugin(CK, opts)
        ];
        if (opts.styles && opts.styles.length && CK.Style) {
            plugins.push(CK.Style);
        }

        var config = {
            licenseKey: 'GPL',
            plugins: plugins,
            toolbar: { items: opts.toolbar || [], shouldNotGroupWhenFull: true },
            language: opts.lang || 'en',
            heading: {
                options: [
                    { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                    { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                    { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                    { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
                ]
            },
            fontFamily: {
                supportAllValues: true,
                options: [
                    'default',
                    'Arial, Helvetica, sans-serif',
                    'Courier New, Courier, monospace',
                    'Georgia, serif',
                    'Tahoma, Geneva, sans-serif',
                    'Times New Roman, Times, serif',
                    'Verdana, Geneva, sans-serif'
                ]
            },
            fontSize: {
                options: ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large'].map(function (size) {
                    return { title: size, model: size, view: { name: 'span', styles: { 'font-size': size } } };
                })
            },
            link: {
                addTargetToExternalLinks: false,
                decorators: {
                    openInNewTab: {
                        mode: 'manual',
                        label: opts.labels.newtab || 'Open in new tab',
                        attributes: { target: '_blank', rel: 'noopener noreferrer' }
                    }
                }
            },
            image: {
                toolbar: ['imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'toggleImageCaption', 'imageTextAlternative', '|', 'linkImage']
            },
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties']
            },
            // The CMS stores arbitrary HTML: do not strip anything the editor does not know
            htmlSupport: {
                allow: [{ name: /.*/, attributes: true, classes: true, styles: true }]
            }
        };
        if (opts.styles && opts.styles.length) {
            config.style = { definitions: opts.styles };
        }
        var height = opts.height || '400px';

        return CK.ClassicEditor.create(el, config).then(function (editor) {
            editors[id] = editor;
            // keep the textarea in sync, so forms can submit at any time (also via form.submit())
            var sync = function () {
                editor.updateSourceElement();
            };
            editor.model.document.on('change:data', sync);
            if (el.form) {
                el.form.addEventListener('submit', sync, true);
            }
            editor.editing.view.change(function (writer) {
                var root = editor.editing.view.document.getRoot();
                writer.setStyle('min-height', height, root);
            });
            return editor;
        }).catch(function (err) {
            if (window.console) {
                window.console.error('Sefrengo editor failed to start', err);
            }
        });
    }

    /* ------------------------------------------------------------------ CodeMirror */

    function code(id, opts) {
        var el = document.getElementById(id);
        if (!el || !window.CodeMirror) {
            return null;
        }
        opts = opts || {};
        var cm = window.CodeMirror.fromTextArea(el, {
            mode: opts.mode || 'application/x-httpd-php',
            lineNumbers: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            autoCloseTags: opts.mode === 'javascript' || opts.mode === 'css' ? false : true,
            styleActiveLine: true,
            indentUnit: 4,
            indentWithTabs: true,
            lineWrapping: false,
            viewportMargin: Infinity
        });
        cm.setSize(opts.width || '100%', opts.height || '450px');
        var sync = function () {
            cm.save();
        };
        cm.on('change', sync);
        if (el.form) {
            el.form.addEventListener('submit', sync, true);
        }
        editors[id] = cm;
        return cm;
    }

    window.SFEditor = {
        rich: rich,
        code: code,
        pick: pick,
        get: function (id) { return editors[id]; }
    };
})(window, document);
