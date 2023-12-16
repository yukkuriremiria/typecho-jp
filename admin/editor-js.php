<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php $content = !empty($post) ? $post : $page; if ($options->markdown): ?>
<script src="<?php $options->adminStaticUrl('js', 'hyperdown.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'pagedown.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'paste.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'purify.js'); ?>"></script>
<script>
$(document).ready(function () {
    var textarea = $('#text'),
        isFullScreen = false,
        toolbar = $('<div class="editor" id="wmd-button-bar" />').insertBefore(textarea.parent()),
        preview = $('<div id="wmd-preview" class="wmd-hidetab" />').insertAfter('.editor');

    var options = {}, isMarkdown = <?php echo intval($content->isMarkdown || !$content->have()); ?>;

    options.strings = {
        bold: '<?php _e('濃化'); ?> <strong> Ctrl+B',
        boldexample: '<?php _e('濃化文字'); ?>',
            
        italic: '<?php _e('斜体'); ?> <em> Ctrl+I',
        italicexample: '<?php _e('斜体'); ?>',

        link: '<?php _e('リンク'); ?> <a> Ctrl+L',
        linkdescription: '<?php _e('请输入リンク描述'); ?>',

        quote:  '<?php _e('引用'); ?> <blockquote> Ctrl+Q',
        quoteexample: '<?php _e('引用'); ?>',

        code: '<?php _e('コーディング'); ?> <pre><code> Ctrl+K',
        codeexample: '<?php _e('请输入コーディング'); ?>',

        image: '<?php _e('写真'); ?> <img> Ctrl+G',
        imagedescription: '<?php _e('请输入写真描述'); ?>',

        olist: '<?php _e('番号付きリスト'); ?> <ol> Ctrl+O',
        ulist: '<?php _e('一般リスト'); ?> <ul> Ctrl+U',
        litem: '<?php _e('リスト項目'); ?>',

        heading: '<?php _e('キャプション'); ?> <h1>/<h2> Ctrl+H',
        headingexample: '<?php _e('キャプション文字'); ?>',

        hr: '<?php _e('わけめ'); ?> <hr> Ctrl+R',
        more: '<?php _e('摘要わけめ'); ?> <!--more--> Ctrl+M',

        undo: '<?php _e('アンドゥ'); ?> - Ctrl+Z',
        redo: '<?php _e('やり直す'); ?> - Ctrl+Y',
        redomac: '<?php _e('やり直す'); ?> - Ctrl+Shift+Z',

        fullscreen: '<?php _e('フルスクリーン'); ?> - Ctrl+J',
        exitFullscreen: '<?php _e('退出フルスクリーン'); ?> - Ctrl+E',
        fullscreenUnsupport: '<?php _e('此浏览器不支持フルスクリーン操作'); ?>',

        imagedialog: '<p><b><?php _e('插入写真'); ?></b></p><p><?php _e('请在下方的输入框内输入要插入的远程写真地址'); ?></p><p><?php _e('您也可以使用添付ファイル功能插入上传的本地写真'); ?></p>',
        linkdialog: '<p><b><?php _e('插入リンク'); ?></b></p><p><?php _e('请在下方的输入框内输入要插入的リンク地址'); ?></p>',

        ok: '<?php _e('定義する'); ?>',
        cancel: '<?php _e('キャンセル'); ?>',

        help: '<?php _e('Markdown文法ヘルプ'); ?>'
    };

    var converter = new HyperDown(),
        editor = new Markdown.Editor(converter, '', options);

    // 自動フォロー
    converter.enableHtml(true);
    converter.enableLine(true);
    reloadScroll = scrollableEditor(textarea, preview);

    // ホワイトリストの修正
    converter.hook('makeHtml', function (html) {
        html = html.replace('<p><!--more--></p>', '<!--more-->');
        
        if (html.indexOf('<!--more-->') > 0) {
            var parts = html.split(/\s*<\!\-\-more\-\->\s*/),
                summary = parts.shift(),
                details = parts.join('');

            html = '<div class="summary">' + summary + '</div>'
                + '<div class="details">' + details + '</div>';
        }

        // 相互互換性block
        html = html.replace(/<(iframe|embed)\s+([^>]*)>/ig, function (all, tag, src) {
            if (src[src.length - 1] == '/') {
                src = src.substring(0, src.length - 1);
            }

            return '<div class="embed"><strong>'
                + tag + '</strong> : ' + $.trim(src) + '</div>';
        });

        return DOMPurify.sanitize(html, {USE_PROFILES: {html: true}});
    });

    editor.hooks.chain('onPreviewRefresh', function () {
        var images = $('img', preview), count = images.length;

        if (count == 0) {
            reloadScroll(true);
        } else {
            images.bind('load error', function () {
                count --;

                if (count == 0) {
                    reloadScroll(true);
                }
            });
        }
    });

    <?php \Typecho\Plugin::factory('admin/editor-js.php')->markdownEditor($content); ?>

    var th = textarea.height(), ph = preview.height(),
        uploadBtn = $('<button type="button" id="btn-fullscreen-upload" class="btn btn-link">'
            + '<i class="i-upload"><?php _e('添付ファイル'); ?></i></button>')
            .prependTo('.submit .right')
            .click(function() {
                $('a', $('.typecho-option-tabs li').not('.active')).trigger('click');
                return false;
            });

    $('.typecho-option-tabs li').click(function () {
        uploadBtn.find('i').toggleClass('i-upload-active',
            $('#tab-files-btn', this).length > 0);
    });

    editor.hooks.chain('enterFakeFullScreen', function () {
        th = textarea.height();
        ph = preview.height();
        $(document.body).addClass('fullscreen');
        var h = $(window).height() - toolbar.outerHeight();
        
        textarea.css('height', h);
        preview.css('height', h);
        isFullScreen = true;
    });

    editor.hooks.chain('enterFullScreen', function () {
        $(document.body).addClass('fullscreen');
        
        var h = window.screen.height - toolbar.outerHeight();
        textarea.css('height', h);
        preview.css('height', h);
        isFullScreen = true;
    });

    editor.hooks.chain('exitFullScreen', function () {
        $(document.body).removeClass('fullscreen');
        textarea.height(th);
        preview.height(ph);
        isFullScreen = false;
    });

    editor.hooks.chain('commandExecuted', function () {
        textarea.trigger('input');
    });

    function initMarkdown() {
        editor.run();

        var imageButton = $('#wmd-image-button'),
            linkButton = $('#wmd-link-button');

        Typecho.insertFileToEditor = function (file, url, isImage) {
            var button = isImage ? imageButton : linkButton;

            options.strings[isImage ? 'imagename' : 'linkname'] = file;
            button.trigger('click');

            var checkDialog = setInterval(function () {
                if ($('.wmd-prompt-dialog').length > 0) {
                    $('.wmd-prompt-dialog input').val(url).select();
                    clearInterval(checkDialog);
                    checkDialog = null;
                }
            }, 10);
        };

        Typecho.uploadComplete = function (file) {
            Typecho.insertFileToEditor(file.title, file.url, file.isImage);
        };

        // 編集プレビューのトグル
        var edittab = $('.editor').prepend('<div class="wmd-edittab"><a href="#wmd-editarea" class="active"><?php _e('書く'); ?></a><a href="#wmd-preview"><?php _e('プレビュー'); ?></a></div>'),
            editarea = $(textarea.parent()).attr("id", "wmd-editarea");

        $(".wmd-edittab a").click(function() {
            $(".wmd-edittab a").removeClass('active');
            $(this).addClass("active");
            $("#wmd-editarea, #wmd-preview").addClass("wmd-hidetab");
        
            var selected_tab = $(this).attr("href"),
                selected_el = $(selected_tab).removeClass("wmd-hidetab");

            // プレビュー时隐藏编辑器按钮
            if (selected_tab == "#wmd-preview") {
                $("#wmd-button-row").addClass("wmd-visualhide");
            } else {
                $("#wmd-button-row").removeClass("wmd-visualhide");
            }

            // プレビュー和编辑窗口高度一致
            $("#wmd-preview").outerHeight($("#wmd-editarea").innerHeight());

            return false;
        });

        // 剪贴板复制写真
        textarea.pastableTextarea().on('pasteImage', function (e, data) {
            var name = data.name ? data.name.replace(/[\(\)\[\]\*#!]/g, '') : (new Date()).toISOString().replace(/\..+$/, '');
            if (!name.match(/\.[a-z0-9]{2,}$/i)) {
                var ext = data.blob.type.split('/').pop();
                name += '.' + ext;
            }

            Typecho.uploadFile(new File([data.blob], name), name);
        });
    }

    if (isMarkdown) {
        initMarkdown();
    } else {
        var notice = $('<div class="message notice"><?php _e('この記事を書いたのはMarkdownによって作成される構文である。, 継続使用Markdown編集する？?'); ?> '
            + '<button class="btn btn-xs primary yes"><?php _e('であります'); ?></button> ' 
            + '<button class="btn btn-xs no"><?php _e('詰り'); ?></button></div>')
            .hide().insertBefore(textarea).slideDown();

        $('.yes', notice).click(function () {
            notice.remove();
            $('<input type="hidden" name="markdown" value="1" />').appendTo('.submit');
            initMarkdown();
        });

        $('.no', notice).click(function () {
            notice.remove();
        });
    }
});
</script>
<?php endif; ?>

