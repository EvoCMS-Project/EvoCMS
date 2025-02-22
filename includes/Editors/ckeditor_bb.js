(function (element_id) {
    var editor = $('#' + element_id), that = this;
    var EDITOR_ROOT = site_url + '/includes/Editors/ckeditor/';

    var plugins_enabled = ['entities', 'bbcode', 'codesnippet', 'youtube2', 'preview2', 'emojione'];
    var plugins_disabled = ['divarea', 'contextmenu', 'liststyle', 'tabletools', 'tableselection', 'tableresize', 'showblocks', 'youtube'];

    CKEDITOR_BASEPATH = EDITOR_ROOT;

    this.getContent = function () {
        if (typeof CKEDITOR != 'undefined') {
            CKEDITOR.instances[element_id].updateElement();
            return CKEDITOR.instances[element_id].getData();
        }
        return '';
    };

    this.insert = function (content) {
        if (typeof CKEDITOR != 'undefined') {
            CKEDITOR.instances[element_id].insertHtml(content);
        }
    };

    this.insertFiles = function (files) {
        let html = '';
        for(let i = 0; i < files.length; i++) {
            let f = files[i];
            if (f.thumb||0) {
                html += '[file=' + f.thumb + ']' + f.id + '/' + f.name + '[/file]\n';
            } else {
                html += '[file]' + f.id + '/' + f.name + '[/file]\n';
            }
        }
        this.insert(html);
    };

    this.destroy = function () {
        CKEDITOR.instances[element_id].destroy();
    };

    this.display = function () {
        $.getScript(EDITOR_ROOT + '/ckeditor.js', function () {
            CKEDITOR.basePath = EDITOR_ROOT;

            if (max_upload_size > 0 && typeof CKEDITOR.plugins.registered.upload == 'undefined') {
                CKEDITOR.plugins.add('upload', {
                    init: function (editor) {
                        editor.ui.addButton("Upload", {
                            label: 'Insérer un fichier',
                            icon: this.path + '../../gallery.png',
                            command: 'Upload'
                        });
                        editor.addCommand('Upload', {
                            exec: function (editor) {
                                $.fancybox.open({ type: 'ajax', href: '?p=gallery&view=grid' });
                                //ajaxupload();
                            },
                            canUndo: false
                        });
                    }
                });
                plugins_enabled.push('upload');
            }

            if (typeof CKEDITOR.plugins.registered.youtube2 == 'undefined') {
                CKEDITOR.plugins.add('youtube2', {
                    init: function (editor) {
                        editor.ui.addButton("Youtube2", {
                            label: 'Insérer un vidéo',
                            icon: this.path + '../../youtube.png',
                            command: 'Youtube2'
                        });
                        editor.addCommand('Youtube2', {
                            exec: function (editor) {
                                let url = prompt("URL YouTube:", "https://");
                                if (url) {
                                    that.insert("[youtube]" + url + "[/youtube]");
                                }
                            },
                            canUndo: false
                        });
                    }
                });
            }

            if (typeof CKEDITOR.plugins.registered.preview2 == 'undefined') {
                CKEDITOR.plugins.add('preview2', {
                    init: function (editor) {
                        editor.ui.addButton("Preview2", {
                            label: 'Preview',
                            icon: this.path + '../../preview.png',
                            command: 'Preview2'
                        });
                        editor.addCommand('Preview2', {
                            exec: function (editor) {
                                $.post(site_url + '/ajax.php', {csrf: csrf, action: 'preview', format: 'bbcode', text: that.getContent()}, function(data) {
                                    $.fancybox.open({type: 'html', content: data, minWidth: 700});
                                });
                            },
                            canUndo: false
                        });
                    }
                });
            }

            CKEDITOR.replace(editor[0], {
                language: 'fr',
                customConfig: '',
                extraPlugins: plugins_enabled.join(','),
                removePlugins: plugins_disabled.join(','),
                autoGrow_maxHeight: 400,
                autoGrow_onStartup: true,
                disableNativeSpellChecker: false,
                allowedContent: true,
                enableTabKeyTools: true,
                baseHref: site_url,
                tabSpaces: 4,
                magicline_everywhere: true,
                magicline_color: '#ccc',
                contentsCss: css_url,
                toolbar: [
                    { name: 'style', items: ['Font', 'FontSize'] },
                    { name: 'styles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Superscript', '-', 'RemoveFormat'] }, // 'Subscript',
                    { name: 'alignment', items: ['JustifyLeft', 'JustifyCenter', 'JustifyRight'] }, // 'JustifyBlock'
                    { name: 'colors', items: ['TextColor', 'BGColor'] },
                    { name: 'list', items: ['NumberedList', 'BulletedList'] },
                    { name: 'insert', items: ['Image', 'Link', 'Unlink', 'Youtube2', 'Upload', 'Blockquote', 'CodeSnippet', 'Table', 'HorizontalRule', 'Emojione'] },
                    { name: 'editing1', items: ('ontouchstart' in document.documentElement) ? ['Undo', 'Redo'] : [] },
                    // '/',
                    // { name: 'tools', items: ['ShowBlocks', 'Maximize'] },
                    { name: 'preview', items: ['Preview2'] },
                    { name: 'source', items: ['Source'] },
                    // { name: 'indent', items: ['Outdent', 'Indent'] },
                    // { name: 'help', items: ['a11yHelp', 'Styles'] },
                ]
            });
            CKEDITOR.dtd.$removeEmpty['i'] = false;
        });

        setTimeout(function () {
            if ($('iframe').length == 0) return;
            $('iframe')[0].contentWindow.document.onkeydown = function (e) {
                if (e.ctrlKey && e.keyCode === 83) {
                    $('textarea').parents('form').submit();
                    return false;
                }
            };

            $('.cke_button__preview').click(function(e) {
                e.stopPropagation();
            });
        }, 1500);
    }
})