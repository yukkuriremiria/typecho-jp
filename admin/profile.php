<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-3">
                <p><a href="https://gravatar.com/emails/"
                      title="<?php _e('ある Gravatar アバターの変更'); ?>"><?php echo '<img class="profile-avatar" src="' . \Typecho\Common::gravatarUrl($user->mail, 220, 'X', 'mm', $request->isSecure()) . '" alt="' . $user->screenName . '" />'; ?></a>
                </p>
                <h2><?php $user->screenName(); ?></h2>
                <p><?php $user->name(); ?></p>
                <p><?php _e('現在 <em>%s</em> 文章, 持つ <em>%s</em> 条关于你的评论ある <em>%s</em> 以下のうち.',
                        $stat->myPublishedPostsNum, $stat->myPublishedCommentsNum, $stat->categoriesNum); ?></p>
                <p><?php
                    if ($user->logged > 0) {
                        $logged = new \Typecho\Date($user->logged);
                        _e('最終ログイン: %s', $logged->word());
                    }
                    ?></p>
            </div>

            <div class="col-mb-12 col-tb-6 col-tb-offset-1 typecho-content-panel" role="form">
                <section>
                    <h3><?php _e('自己紹介'); ?></h3>
                    <?php \Widget\Users\Profile::alloc()->profileForm()->render(); ?>
                </section>

                <?php if ($user->pass('contributor', true)): ?>
                    <br>
                    <section id="writing-option">
                        <h3><?php _e('ライティング設定'); ?></h3>
                        <?php \Widget\Users\Profile::alloc()->optionsForm()->render(); ?>
                    </section>
                <?php endif; ?>

                <br>

                <section id="change-password">
                    <h3><?php _e('パスワード変更'); ?></h3>
                    <?php \Widget\Users\Profile::alloc()->passwordForm()->render(); ?>
                </section>

                <?php \Widget\Users\Profile::alloc()->personalFormList(); ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
\Typecho\Plugin::factory('admin/profile.php')->bottom();
include 'footer.php';
?>
