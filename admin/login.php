<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}
$rememberName = htmlspecialchars(\Typecho\Cookie::get('__typecho_remember_name', ''));
\Typecho\Cookie::delete('__typecho_remember_name');

$bodyClass = 'body-100';

include 'header.php';
?>
<div class="typecho-login-wrap">
    <div class="typecho-login">
        <h1><a href="https://typecho.org" class="i-logo">Typecho</a></h1>
        <form action="<?php $options->loginAction(); ?>" method="post" name="login" role="form">
            <p>
                <label for="name" class="sr-only"><?php _e('利用者ID'); ?></label>
                <input type="text" id="name" name="name" value="<?php echo $rememberName; ?>" placeholder="<?php _e('利用者ID'); ?>" class="text-l w-100" autofocus />
            </p>
            <p>
                <label for="password" class="sr-only"><?php _e('暗号化'); ?></label>
                <input type="password" id="password" name="password" class="text-l w-100" placeholder="<?php _e('暗号化'); ?>" />
            </p>
            <p class="submit">
                <button type="submit" class="btn btn-l w-100 primary"><?php _e('サインイン'); ?></button>
                <input type="hidden" name="referer" value="<?php echo $request->filter('html')->get('referer'); ?>" />
            </p>
            <p>
                <label for="remember">
                    <input<?php if(\Typecho\Cookie::get('__typecho_remember_remember')): ?> checked<?php endif; ?> type="checkbox" name="remember" class="checkbox" value="1" id="remember" /> <?php _e('下次自动サインイン'); ?>
                </label>
            </p>
        </form>
        
        <p class="more-link">
            <a href="<?php $options->siteUrl(); ?>"><?php _e('ホームページに戻る'); ?></a>
            <?php if($options->allowRegister): ?>
            &bull;
            <a href="<?php $options->registerUrl(); ?>"><?php _e('ユーザー登録'); ?></a>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php 
include 'common-js.php';
?>
<script>
$(document).ready(function () {
    $('#name').focus();
});
</script>
<?php
include 'footer.php';
?>
