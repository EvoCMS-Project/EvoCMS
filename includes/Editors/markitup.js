(function (element_id) {
    var editor = $('#' + element_id), that = this;
    var EDITOR_ROOT = site_url + '/includes/Editors/markitup';

    this.getContent = function () {
        return editor.val();
    };

    this.insert = function (content) {
        $.markItUp({ target: editor, replaceWith: content });
    };

    this.insertFiles = function (files) {
        let html = '';
        for (let i = 0; i < files.length; i++) {
            let f = files[i];
            if (f.thumb || 0) {
                html += '[file ' + f.thumb + ']' + f.id + '/' + f.name + '[/file]\n';
            } else {
                html += '[file]' + f.id + '/' + f.name + '[/file]\n';
            }
        }
        if (files.length > 1) {
            html = '<div class="flex-gallery">\n' + html + '</div>';
        }
        $.markItUp({ target: editor, replaceWith: html });
    };

    this.destroy = function () {
        editor.markItUpRemove();
    };

    this.display = function () {
        $('<link rel="stylesheet" type="text/css" href="' + EDITOR_ROOT + '/markitup/skins/evo/style.css">').appendTo('body');
        $('<link rel="stylesheet" type="text/css" href="' + EDITOR_ROOT + '/markitup/sets/markdown/style.css">').appendTo('body');

        $.getScript(EDITOR_ROOT + '/markitup/sets/markdown/set.js', function () {
            $.getScript(EDITOR_ROOT + '/markitup/jquery.markitup.js', function () {
                mySettings.previewAutoRefresh = false;
                mySettings.previewHandler = function () {
                    $.post(site_url + '/ajax.php', { csrf: csrf, action: 'preview', text: editor.val() }, function (data) {
                        $.fancybox.open({ type: 'html', content: data, minWidth: 700 });
                    });
                }

                if (!(mySettings._embedBtnAdded || 0)) {
                    if (max_upload_size > 0) {
                        mySettings.markupSet.splice(-2, 0, { separator: '---------------' });
                        mySettings.markupSet.splice(-2, 0, {
                            name: 'Insert File', className: "file", openWith: function (editor) {
                                window._editor = that;
                                $.fancybox.open({ type: 'ajax', href: '?p=gallery&view=grid&embed=1' });
                            }
                        });
                    }
                    mySettings.markupSet.push({ separator: '---------------' });
                    mySettings.markupSet.push({
                        name: 'Syntax Guide', className: "help", call: function (editor) {
                            window.open('https://michelf.ca/projects/php-markdown/extra/', '_blank');
                        }
                    });
                    mySettings._embedBtnAdded = 1;
                }

                editor.markItUp(mySettings);
            });
        });
    }
})