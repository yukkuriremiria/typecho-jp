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
        <label class="typecho-label" for="dbUser"><?php _e('データベースユーザー名'); ?></label>
        <input type="text" class="text" name="dbUser" id="dbUser" value="" />
        <p class="description"><?php _e('を使用することができる。 "%s"', 'root'); ?></p>
    </li>
</ul>

<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbPassword"><?php _e('データベースパスワード'); ?></label>
        <input type="password" class="text" name="dbPassword" id="dbPassword" value="" />
    </li>
</ul>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbDatabase"><?php _e('データベース名'); ?></label>
        <input type="text" class="text" name="dbDatabase" id="dbDatabase" value="" />
        <p class="description"><?php _e('请您指定データベース名称'); ?></p>
    </li>

</ul>

<details>
    <summary>
        <strong><?php _e('高度なオプション'); ?></strong>
    </summary>
    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbPort"><?php _e('データベースポート'); ?></label>
            <input type="text" class="text" name="dbPort" id="dbPort" value="3306"/>
            <p class="description"><?php _e('このオプションの意味がわからない場合, デフォルト設定のままにしてください。'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbCharset"><?php _e('データベース符号化'); ?></label>
            <select name="dbCharset" id="dbCharset">
                <option value="utf8mb4">utf8mb4</option>
                <option value="utf8">utf8</option>
            </select>
            <p class="description"><?php _e('オプション utf8mb4 コーディングには少なくとも以下のことが必要である。 MySQL 5.5.3 リリース'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbEngine"><?php _e('データベースエンジン'); ?></label>
            <select name="dbEngine" id="dbEngine">
                <option value="InnoDB">InnoDB</option>
                <option value="MyISAM">MyISAM</option>
            </select>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbSslCa"><?php _e('総合データベース SSL 証券'); ?></label>
            <input type="text" class="text" name="dbSslCa" id="dbSslCa"/>
            <p class="description"><?php _e('如果您的総合データベースコミッション了 SSL，ご記入ください CA 証券路径，それ以外の場合は空欄にしてください。'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbSslVerify"><?php _e('コミッション総合データベース SSL 服务端証券验证'); ?></label>
            <select name="dbSslVerify" id="dbSslVerify">
                <option value="on"><?php _e('コミッション'); ?></option>
                <option value="off"><?php _e('不コミッション'); ?></option>
            </select>
        </li>
    </ul>
</details>
