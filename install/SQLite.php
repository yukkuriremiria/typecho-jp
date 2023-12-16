<?php if(!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $defaultDir = __TYPECHO_ROOT_DIR__ . '/usr/' . uniqid() . '.db'; ?>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbFile"><?php _e('データベースファイルのパス'); ?></label>
        <input type="text" class="text" name="dbFile" id="dbFile" value="<?php echo $defaultDir; ?>"/>
        <p class="description"><?php _e('"%s" 私たちが自動的に生成するアドレスです。', $defaultDir); ?></p>
    </li>
</ul>
