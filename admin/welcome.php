<?php
include 'common.php';
include 'header.php';
include 'menu.php';
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <div id="typecho-welcome" class="message">
                    <form action="<?php $options->adminUrl(); ?>" method="get">
                    <h3><?php _e('ご自由にお使いください。 "%s" マネージメント・バックオフィス: ', $options->title); ?></h3>
                    <ol>
                        <li><a class="operate-delete" href="<?php $options->adminUrl('profile.php#change-password'); ?>"><?php _e('デフォルトのパスワードを変更することを強くお勧めします。'); ?></a></li>
                        <?php if($user->pass('contributor', true)): ?>
                        <li><a href="<?php $options->adminUrl('write-post.php'); ?>"><?php _e('最初のログを書く'); ?></a></li>
                        <li><a href="<?php $options->siteUrl(); ?>"><?php $user->pass('administrator', true) ? _e('私のサイトを見る') : _e('ウェブサイトを見る'); ?></a></li>
                        <?php else: ?>
                        <li><a href="<?php $options->siteUrl(); ?>"><?php _e('ウェブサイトを見る'); ?></a></li>
                        <?php endif; ?>
                    </ol>
                    <p><button type="submit" class="btn primary"><?php _e('さっそく本題に入ろう。 &raquo;'); ?></button></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
