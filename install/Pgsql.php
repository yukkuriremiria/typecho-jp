<?php if(!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbHost"><?php _e('データベースアドレス'); ?></label>
        <input type="text" class="text" name="dbHost" id="dbHost" value="localhost"/>
        <p class="description"><?php _e('を使用することができる。 "%s"', 'localhost'); ?></p>
    </li>
</ul>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbPort"><?php _e('データベースポート'); ?></label>
        <input type="text" class="text" name="dbPort" id="dbPort" value="5432"/>
        <p class="description"><?php _e('このオプションの意味がわからない場合, デフォルト設定のままにしてください。'); ?></p>
    </li>
</ul>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbUser"><?php _e('データベースユーザー名'); ?></label>
        <input type="text" class="text" name="dbUser" id="dbUser" value="postgres" />
        <p class="description"><?php _e('を使用することができる。 "%s"', 'postgres'); ?></p>
    </li>
</ul>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbPassword"><?php _e('データベースパスワード'); ?></label>
        <input type="password" class="text" name="dbPassword" id="dbPassword" value="" />
    </li
</ul>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbDatabase"><?php _e('データベース名'); ?></label>
        <input type="text" class="text" name="dbDatabase" id="dbDatabase" value="" />
        <p class="description"><?php _e('请您指定データベース名称'); ?></p>
    </li
</ul>

<input type="hidden" name="dbCharset" value="utf8" />
