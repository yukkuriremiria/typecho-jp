<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();
$attachments = \Widget\Contents\Attachment\Admin::alloc();
?>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">

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
                                    <li><a lang="<?php _e('本当にこれらのファイルを削除しますか？?'); ?>"
                                           href="<?php $security->index('/action/contents-attachment-edit?do=delete'); ?>"><?php _e('除去'); ?></a>
                                    </li>
                                </ul>
                                <button class="btn btn-s btn-warn btn-operate"
                                        href="<?php $security->index('/action/contents-attachment-edit?do=clear'); ?>"
                                        lang="<?php _e('本当に未提出の書類を整理したいのですか？?'); ?>"><?php _e('未提出書類の整理'); ?></button>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <?php if ('' != $request->keywords): ?>
                                <a href="<?php $options->adminUrl('manage-medias.php'); ?>"><?php _e('&laquo; 上映中止'); ?></a>
                            <?php endif; ?>
                            <input type="text" class="text-s" placeholder="<?php _e('キーワードを入力してください'); ?>"
                                   value="<?php echo $request->filter('html')->keywords; ?>"<?php if ('' == $request->keywords): ?> onclick="value='';name='keywords';" <?php else: ?> name="keywords"<?php endif; ?>/>
                            <button type="submit" class="btn btn-s"><?php _e('スクリーニング'); ?></button>
                        </div>
                    </form>
                </div><!-- end .typecho-list-operate -->

                <form method="post" name="manage_medias" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table draggable">
                            <colgroup>
                                <col width="20" class="kit-hidden-mb"/>
                                <col width="6%" class="kit-hidden-mb"/>
                                <col width="30%"/>
                                <col width="" class="kit-hidden-mb"/>
                                <col width="30%" class="kit-hidden-mb"/>
                                <col width="16%"/>
                            </colgroup>
                            <thead>
                            <tr>
                                <th class="kit-hidden-mb"></th>
                                <th class="kit-hidden-mb"></th>
                                <th><?php _e('ファイル名'); ?></th>
                                <th class="kit-hidden-mb"><?php _e('アップローダ'); ?></th>
                                <th class="kit-hidden-mb"><?php _e('関連記事'); ?></th>
                                <th><?php _e('発売日'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($attachments->have()): ?>
                                <?php while ($attachments->next()): ?>
                                    <?php $mime = \Typecho\Common::mimeIconType($attachments->attachment->mime); ?>
                                    <tr id="<?php $attachments->theId(); ?>">
                                        <td class="kit-hidden-mb"><input type="checkbox"
                                                                         value="<?php $attachments->cid(); ?>"
                                                                         name="cid[]"/></td>
                                        <td class="kit-hidden-mb"><a
                                                href="<?php $options->adminUrl('manage-comments.php?cid=' . $attachments->cid); ?>"
                                                class="balloon-button size-<?php echo \Typecho\Common::splitByCount($attachments->commentsNum, 1, 10, 20, 50, 100); ?>"><?php $attachments->commentsNum(); ?></a>
                                        </td>
                                        <td>
                                            <i class="mime-<?php echo $mime; ?>"></i>
                                            <a href="<?php $options->adminUrl('media.php?cid=' . $attachments->cid); ?>"><?php $attachments->title(); ?></a>
                                            <a href="<?php $attachments->permalink(); ?>"
                                               title="<?php _e('目を通す %s', $attachments->title); ?>"><i
                                                    class="i-exlink"></i></a>
                                        </td>
                                        <td class="kit-hidden-mb"><?php $attachments->author(); ?></td>
                                        <td class="kit-hidden-mb">
                                            <?php if ($attachments->parentPost->cid): ?>
                                                <a href="<?php $options->adminUrl('write-' . (0 === strpos($attachments->parentPost->type, 'post') ? 'post' : 'page') . '.php?cid=' . $attachments->parentPost->cid); ?>"><?php $attachments->parentPost->title(); ?></a>
                                            <?php else: ?>
                                                <span class="description"><?php _e('未記入'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php $attachments->dateWord(); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><h6 class="typecho-list-table-title"><?php _e('文書はない。'); ?></h6>
                                    </td>
                                </tr>
                            <?php endif; ?>
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
                                    <li><a lang="<?php _e('本当にこれらのファイルを削除しますか？?'); ?>"
                                           href="<?php $security->index('/action/contents-attachment-edit?do=delete'); ?>"><?php _e('除去'); ?></a>
                                    </li>
                                </ul>
                            </div>
                            <button class="btn btn-s btn-warn btn-operate"
                                    href="<?php $security->index('/action/contents-attachment-edit?do=clear'); ?>"
                                    lang="<?php _e('本当に未提出の書類を整理したいのですか？?'); ?>"><?php _e('未提出書類の整理'); ?></button>
                        </div>
                        <?php if ($attachments->have()): ?>
                            <ul class="typecho-pager">
                                <?php $attachments->pageNav(); ?>
                            </ul>
                        <?php endif; ?>
                    </form>
                </div><!-- end .typecho-list-operate -->

            </div>
        </div><!-- end .typecho-page-main -->
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
include 'footer.php';
?>
