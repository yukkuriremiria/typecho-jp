<?php if (!defined('__TYPECHO_ADMIN__')) exit; ?>
<div class="typecho-head-nav clearfix" role="navigation">
    <button class="menu-bar"><?php _e('メニュー'); ?></button>
    <nav id="typecho-nav-list">
        <?php $menu->output(); ?>
    </nav>
    <div class="operate">
        <?php \Typecho\Plugin::factory('admin/menu.php')->navBar(); ?><a title="<?php
        if ($user->logged > 0) {
            $logged = new \Typecho\Date($user->logged);
            _e('最終ログイン: %s', $logged->word());
        }
        ?>" href="<?php $options->adminUrl('profile.php'); ?>" class="author"><?php $user->screenName(); ?></a><a
            class="exit" href="<?php $options->logoutUrl(); ?>"><?php _e('載る'); ?></a><a
            href="<?php $options->siteUrl(); ?>"><?php _e('ノード'); ?></a>
    </div>
</div>

