<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script>
$(document).ready(function () {
    // カスタムフィールド
    $('#custom-field-expand').click(function() {
        var btn = $('i', this);
        if (btn.hasClass('i-caret-right')) {
            btn.removeClass('i-caret-right').addClass('i-caret-down');
        } else {
            btn.removeClass('i-caret-down').addClass('i-caret-right');
        }
        $(this).parent().toggleClass('fold');
        return false;
    });

    function attachDeleteEvent (el) {
        $('button.btn-xs', el).click(function () {
            if (confirm('<?php _e('本当にこのフィールドを削除しますか？?'); ?>')) {
                $(this).parents('tr').fadeOut(function () {
                    $(this).remove();
                });

                $(this).parents('form').trigger('field');
            }
        });
    }

    $('#custom-field table tbody tr').each(function () {
        attachDeleteEvent(this);
    });

    $('#custom-field button.operate-add').click(function () {
        var html = '<tr><td><input type="text" name="fieldNames[]" placeholder="<?php _e('フィールド名'); ?>" class="text-s w-100"></td>'
                + '<td><select name="fieldTypes[]" id="">'
                + '<option value="str"><?php _e('性格'); ?></option>'
                + '<option value="int"><?php _e('整数'); ?></option>'
                + '<option value="float"><?php _e('端数'); ?></option>'
                + '</select></td>'
                + '<td><textarea name="fieldValues[]" placeholder="<?php _e('フィールド値'); ?>" class="text-s w-100" rows="2"></textarea></td>'
                + '<td><button type="button" class="btn btn-xs"><?php _e('除去'); ?></button></td></tr>',
            el = $(html).hide().appendTo('#custom-field table tbody').fadeIn();

            $(':input', el).bind('input change', function () {
                $(this).parents('form').trigger('field');
            });

        attachDeleteEvent(el);
    });
});
</script>
