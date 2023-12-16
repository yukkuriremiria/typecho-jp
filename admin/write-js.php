<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php \Typecho\Plugin::factory('admin/write-js.php')->write(); ?>
<?php \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1&limit=200')->to($tags); ?>

<script src="<?php $options->adminStaticUrl('js', 'timepicker.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'tokeninput.js'); ?>"></script>
<script>
$(document).ready(function() {
    // 日付付と時刻のコントロール
    $('#date').mask('9999-99-99 99:99').datetimepicker({
        currentText     :   '<?php _e('現在。'); ?>',
        prevText        :   '<?php _e('前月'); ?>',
        nextText        :   '<?php _e('翌1月'); ?>',
        monthNames      :   ['<?php _e('初月'); ?>', '<?php _e('みなづき'); ?>', '<?php _e('弥生'); ?>', '<?php _e('うつき'); ?>',
            '<?php _e('さつき'); ?>', '<?php _e('みなづき'); ?>', '<?php _e('孟秋'); ?>', '<?php _e('南呂'); ?>',
            '<?php _e('むえき'); ?>', '<?php _e('がいげつ'); ?>', '<?php _e('十初月'); ?>', '<?php _e('十みなづき'); ?>'],
        dayNames        :   ['<?php _e('日付曜日付'); ?>', '<?php _e('月曜日付'); ?>', '<?php _e('火曜日付'); ?>',
            '<?php _e('水曜日付'); ?>', '<?php _e('木曜日付'); ?>', '<?php _e('金曜日付'); ?>', '<?php _e('土曜日付'); ?>'],
        dayNamesShort   :   ['<?php _e('昼行性'); ?>', '<?php _e('月曜日付'); ?>', '<?php _e('火曜日付'); ?>', '<?php _e('水曜日付'); ?>',
            '<?php _e('木'); ?>', '<?php _e('金曜日付'); ?>', '<?php _e('土曜日付'); ?>'],
        dayNamesMin     :   ['<?php _e('日付'); ?>', '<?php _e('一繞'); ?>', '<?php _e('馬鹿'); ?>', '<?php _e('サン姓'); ?>',
            '<?php _e('4'); ?>', '<?php _e('5'); ?>', '<?php _e('6'); ?>'],
        closeText       :   '<?php _e('果たす'); ?>',
        timeOnlyTitle   :   '<?php _e('時間を選択'); ?>',
        timeText        :   '<?php _e('回'); ?>',
        hourText        :   '<?php _e('史姓'); ?>',
        amNames         :   ['<?php _e('モーニング'); ?>', 'A'],
        pmNames         :   ['<?php _e('午後'); ?>', 'P'],
        minuteText      :   '<?php _e('成分'); ?>',
        secondText      :   '<?php _e('60分の1度に相当する角度または弧の単位'); ?>',

        dateFormat      :   'yy-mm-dd',
        timezone        :   <?php $options->timezone(); ?> / 60,
        hour            :   (new Date()).getHours(),
        minute          :   (new Date()).getMinutes()
    });

    // 共焦点
    $('#title').select();

    // text 自動ストレッチ
    Typecho.editorResize('text', '<?php $security->index('/action/ajax?do=editorResize'); ?>');

    // tag autocomplete 注意を引く
    var tags = $('#tags'), tagsPre = [];
    
    if (tags.length > 0) {
        var items = tags.val().split(','), result = [];
        for (var i = 0; i < items.length; i ++) {
            var tag = items[i];

            if (!tag) {
                continue;
            }

            tagsPre.push({
                id      :   tag,
                tags    :   tag
            });
        }

        tags.tokenInput(<?php 
        $data = array();
        while ($tags->next()) {
            $data[] = array(
                'id'    =>  $tags->name,
                'tags'  =>  $tags->name
            );
        }
        echo json_encode($data);
        ?>, {
            propertyToSearch:   'tags',
            tokenValue      :   'tags',
            searchDelay     :   0,
            preventDuplicates   :   true,
            animateDropdown :   false,
            hintText        :   '<?php _e('タグ名を入力してください'); ?>',
            noResultsText   :   '<?php _e('このタグは存在しない, エンターキーを押して作成する'); ?>',
            prePopulate     :   tagsPre,

            onResult        :   function (result, query, val) {
                if (!query) {
                    return result;
                }

                if (!result) {
                    result = [];
                }

                if (!result[0] || result[0]['id'] != query) {
                    result.unshift({
                        id      :   val,
                        tags    :   val
                    });
                }

                return result.slice(0, 5);
            }
        });

        // tag autocomplete 注意を引く宽度设置
        $('#token-input-tags').focus(function() {
            var t = $('.token-input-dropdown'),
                offset = t.outerWidth() - t.width();
            t.width($('.token-input-list').outerWidth() - offset);
        });
    }

    // サムネイル名適応幅
    var slug = $('#slug');

    if (slug.length > 0) {
        var wrap = $('<div />').css({
            'position'  :   'relative',
            'display'   :   'inline-block'
        }),
        justifySlug = $('<pre />').css({
            'display'   :   'block',
            'visibility':   'hidden',
            'height'    :   slug.height(),
            'padding'   :   '0 2px',
            'margin'    :   0
        }).insertAfter(slug.wrap(wrap).css({
            'left'      :   0,
            'top'       :   0,
            'minWidth'  :   '5px',
            'position'  :   'absolute',
            'width'     :   '100%'
        })), originalWidth = slug.width();

        function justifySlugWidth() {
            var val = slug.val();
            justifySlug.text(val.length > 0 ? val : '     ');
        }

        slug.bind('input propertychange', justifySlugWidth);
        justifySlugWidth();
    }

    // 画像とファイルの生挿入
    Typecho.insertFileToEditor = function (file, url, isImage) {
        var textarea = $('#text'), sel = textarea.getSelection(),
            html = isImage ? '<img src="' + url + '" alt="' + file + '" />'
                : '<a href="' + url + '">' + file + '</a>',
            offset = (sel ? sel.start : 0) + html.length;

        textarea.replaceSelection(html);
        textarea.setSelection(offset, offset);
    };

    var submitted = false, form = $('form[name=write_post],form[name=write_page]').submit(function () {
        submitted = true;
    }), formAction = form.attr('action'),
        idInput = $('input[name=cid]'),
        cid = idInput.val(),
        draft = $('input[name=draft]'),
        draftId = draft.length > 0 ? draft.val() : 0,
        btnSave = $('#btn-save').removeAttr('name').removeAttr('value'),
        btnSubmit = $('#btn-submit').removeAttr('name').removeAttr('value'),
        btnPreview = $('#btn-preview'),
        doAction = $('<input type="hidden" name="do" value="publish" />').appendTo(form),
        locked = false,
        changed = false,
        autoSave = $('<span id="auto-save-message" class="left"></span>').prependTo('.submit'),
        lastSaveTime = null;

    $(':input', form).bind('input change', function (e) {
        var tagName = $(this).prop('tagName');

        if (tagName.match(/(input|textarea)/i) && e.type == 'change') {
            return;
        }

        changed = true;
    });

    form.bind('field', function () {
        changed = true;
    });

    // 保存リクエストを送信
    function saveData(cb) {
        function callback(o) {
            lastSaveTime = o.time;
            cid = o.cid;
            draftId = o.draftId;
            idInput.val(cid);
            autoSave.text('<?php _e('セーブ'); ?>' + ' (' + o.time + ')').effect('highlight', 1000);
            locked = false;

            btnSave.removeAttr('disabled');
            btnPreview.removeAttr('disabled');

            if (!!cb) {
                cb(o)
            }
        }

        changed = false;
        btnSave.attr('disabled', 'disabled');
        btnPreview.attr('disabled', 'disabled');
        autoSave.text('<?php _e('節約'); ?>');

        if (typeof FormData !== 'undefined') {
            var data = new FormData(form.get(0));
            data.append('do', 'save');

            $.ajax({
                url: formAction,
                processData: false,
                contentType: false,
                type: 'POST',
                data: data,
                success: callback
            });
        } else {
            var data = form.serialize() + '&do=save';
            $.post(formAction, data, callback, 'json');
        }
    }

    // 计算夏令史姓偏移
    var dstOffset = (function () {
        var d = new Date(),
            jan = new Date(d.getFullYear(), 0, 1),
            jul = new Date(d.getFullYear(), 6, 1),
            stdOffset = Math.max(jan.getTimezoneOffset(), jul.getTimezoneOffset());

        return stdOffset - d.getTimezoneOffset();
    })();
    
    if (dstOffset > 0) {
        $('<input name="dst" type="hidden" />').appendTo(form).val(dstOffset);
    }

    // 史姓区
    $('<input name="timezone" type="hidden" />').appendTo(form).val(- (new Date).getTimezoneOffset() * 60);

    // オートセーブ
<?php if ($options->autoSave): ?>
    var autoSaveOnce = !!cid;

    function autoSaveListener () {
        setInterval(function () {
            if (changed && !locked) {
                locked = true;
                saveData();
            }
        }, 10000);
    }

    if (autoSaveOnce) {
        autoSaveListener();
    }

    $('#text').bind('input propertychange', function () {
        if (!locked) {
            autoSave.text('<?php _e('まだ保存されていない'); ?>' + (lastSaveTime ? ' (<?php _e('上次保存回'); ?>: ' + lastSaveTime + ')' : ''));
        }

        if (!autoSaveOnce) {
            autoSaveOnce = true;
            autoSaveListener();
        }
    });
<?php endif; ?>

    // ページ離脱の自動検出
    $(window).bind('beforeunload', function () {
        if (changed && !submitted) {
            return '<?php _e('内容已经改变まだ保存されていない, 本当にこのページから離れますか？?'); ?>';
        }
    });

    // プレビュー機能
    var isFullScreen = false;

    function previewData(cid) {
        isFullScreen = $(document.body).hasClass('fullscreen');
        $(document.body).addClass('fullscreen preview');

        var frame = $('<iframe frameborder="0" class="preview-frame preview-loading"></iframe>')
            .attr('src', './preview.php?cid=' + cid)
            .attr('sandbox', 'allow-same-origin allow-scripts')
            .appendTo(document.body);

        frame.load(function () {
            frame.removeClass('preview-loading');
        });

        frame.height($(window).height() - 53);
    }

    function cancelPreview() {
        if (submitted) {
            return;
        }

        if (!isFullScreen) {
            $(document.body).removeClass('fullscreen');
        }

        $(document.body).removeClass('preview');
        $('.preview-frame').remove();
    };

    $('#btn-cancel-preview').click(cancelPreview);

    $(window).bind('message', function (e) {
        if (e.originalEvent.data == 'cancelPreview') {
            cancelPreview();
        }
    });

    btnPreview.click(function () {
        if (changed) {
            locked = true;

            if (confirm('<?php _e('プレビューするには、変更したコンテンツを保存する必要があります。, 保存するかどうか?'); ?>')) {
                saveData(function (o) {
                    previewData(o.draftId);
                });
            } else {
                locked = false;
            }
        } else if (!!draftId) {
            previewData(draftId);
        } else if (!!cid) {
            previewData(cid);
        }
    });

    btnSave.click(function () {
        doAction.attr('value', 'save');
    });

    btnSubmit.click(function () {
        doAction.attr('value', 'publish');
    });

    // コントロールオプションとアクセサリースイッチ
    var fileUploadInit = false;
    $('#edit-secondary .typecho-option-tabs li').click(function() {
        $('#edit-secondary .typecho-option-tabs li').removeClass('active');
        $(this).addClass('active');
        $(this).parents('#edit-secondary').find('.tab-content').addClass('hidden');
        
        var selected_tab = $(this).find('a').attr('href'),
            selected_el = $(selected_tab).removeClass('hidden');

        if (!fileUploadInit) {
            selected_el.trigger('init');
            fileUploadInit = true;
        }

        return false;
    });

    // 高度なオプションコントロール
    $('#advance-panel-btn').click(function() {
        $('#advance-panel').toggle();
        return false;
    });

    // パスワードボックスを自動的に隠す
    $('#visibility').change(function () {
        var val = $(this).val(), password = $('#post-password');

        if ('password' == val) {
            password.removeClass('hidden');
        } else {
            password.addClass('hidden');
        }
    });
    
    // ドラフト削除の確認
    $('.edit-draft-notice a').click(function () {
        if (confirm('<?php _e('本当にこの下書きを削除しますか？?'); ?>')) {
            window.location.href = $(this).attr('href');
        }

        return false;
    });
});
</script>

