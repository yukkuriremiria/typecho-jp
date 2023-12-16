<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$users = \Widget\Users\Admin::alloc();
?>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('全員一致'); ?></i><input type="checkbox"
                                                                                   class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i
                                        class="sr-only"><?php _e('リグ'); ?></i><?php _e('選択項目'); ?> <i
                                        class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('これらのユーザーを削除してもよろしいですか？?'); ?>"
                                           href="<?php $security->index('/action/users-edit?do=delete'); ?>"><?php _e('除去'); ?></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <?php if ('' != $request->keywords): ?>
                                <a href="<?php $options->adminUrl('manage-users.php'); ?>"><?php _e('&laquo; 上映中止'); ?></a>
                            <?php endif; ?>
                            <input type="text" class="text-s" placeholder="<?php _e('キーワードを入力してください'); ?>"
                                   value="<?php echo $request->filter('html')->keywords; ?>" name="keywords"/>
                            <button type="submit" class="btn btn-s"><?php _e('スクリーニング'); ?></button>
                        </div>
                    </form>
                </div><!-- end .typecho-list-operate -->

                <form method="post" name="manage_users" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="20" class="kit-hidden-mb"/>
                                <col width="6%" class="kit-hidden-mb"/>
                                <col width="30%"/>
                                <col width="" class="kit-hidden-mb"/>
                                <col width="25%" class="kit-hidden-mb"/>
                                <col width="15%"/>
                            </colgroup>
                            <thead>
                            <tr>
                                <th class="kit-hidden-mb"></th>
                                <th class="kit-hidden-mb"></th>
                                <th><?php _e('利用者ID'); ?></th>
                                <th class="kit-hidden-mb"><?php _e('愛称'); ?></th>
                                <th class="kit-hidden-mb"><?php _e('電子メール'); ?></th>
                                <th><?php _e('ユーザーグループ'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while ($users->next()): ?>
                                <tr id="user-<?php $users->uid(); ?>">
                                    <td class="kit-hidden-mb"><input type="checkbox" value="<?php $users->uid(); ?>"
                                                                     name="uid[]"/></td>
                                    <td class="kit-hidden-mb"><a
                                            href="<?php $options->adminUrl('manage-posts.php?__typecho_all_posts=off&uid=' . $users->uid); ?>"
                                            class="balloon-button left size-<?php echo \Typecho\Common::splitByCount($users->postsNum, 1, 10, 20, 50, 100); ?>"><?php $users->postsNum(); ?></a>
                                    </td>
                                    <td>
                                        <a href="<?php $options->adminUrl('user.php?uid=' . $users->uid); ?>"><?php $users->name(); ?></a>
                                        <a href="<?php $users->permalink(); ?>"
                                           title="<?php _e('目を通す %s', $users->screenName); ?>"><i
                                                class="i-exlink"></i></a>
                                    </td>
                                    <td class="kit-hidden-mb"><?php $users->screenName(); ?></td>
                                    <td class="kit-hidden-mb"><?php if ($users->mail): ?><a
                                            href="mailto:<?php $users->mail(); ?>"><?php $users->mail(); ?></a><?php else: _e('当分の間'); endif; ?>
                                    </td>
                                    <td><?php switch ($users->group) {
                                            case 'administrator':
                                                _e('親方');
                                                break;
                                            case 'editor':
                                                _e('コンパイラ');
                                                break;
                                            case 'contributor':
                                                _e('恩人');
                                                break;
                                            case 'subscriber':
                                                _e('ウオッチャー');
                                                break;
                                            case 'visitor':
                                                _e('インタビュアー');
                                                break;
                                            default:
                                                break;
                                        } ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table><!-- end .typecho-list-table -->
                    </div><!-- end .typecho-table-wrap -->
                </form><!-- end .operate-form -->

                <div class="typecho-list-operate clearfix">
                    <form method="get">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('全員一致'); ?></i><input type="checkbox"
                                                                                   class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i
                                        class="sr-only"><?php _e('リグ'); ?></i><?php _e('選択項目'); ?> <i
                                        class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('これらのユーザーを削除してもよろしいですか？?'); ?>"
                                           href="<?php $security->index('/action/users-edit?do=delete'); ?>"><?php _e('除去'); ?></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <?php if ($users->have()): ?>
                            <ul class="typecho-pager">
                                <?php $users->pageNav(); ?>
                            </ul>
                        <?php endif; ?>
                    </form>
                </div><!-- end .typecho-list-operate -->
            </div><!-- end .typecho-list -->
        </div><!-- end .typecho-page-main -->
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
include 'footer.php';
?>
