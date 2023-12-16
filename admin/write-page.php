<?php
include 'common.php';
include 'header.php';
include 'menu.php';
\Widget\Contents\Page\Edit::alloc()->to($page);
?>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main typecho-post-area" role="form">
            <form action="<?php $security->index('/action/contents-page-edit'); ?>" method="post" name="write_page">
                <div class="col-mb-12 col-tb-9" role="main">
                    <?php if ($page->draft): ?>
                        <?php if ($page->draft['cid'] != $page->cid): ?>
                            <?php $pageModifyDate = new \Typecho\Date($page->draft['modified']); ?>
                            <cite
                                class="edit-draft-notice"><?php _e('現在編集中のファイルは%s決議案, できる。<a href="%s">削除してください。</a>', $pageModifyDate->word(),
                                    $security->getIndex('/action/contents-page-edit?do=deleteDraft&cid=' . $page->cid)); ?></cite>
                        <?php else: ?>
                            <cite class="edit-draft-notice"><?php _e('当前正在编辑的是未发布決議案'); ?></cite>
                        <?php endif; ?>
                        <input name="draft" type="hidden" value="<?php echo $page->draft['cid'] ?>"/>
                    <?php endif; ?>

                    <p class="title">
                        <label for="title" class="sr-only"><?php _e('キャプション'); ?></label>
                        <input type="text" id="title" name="title" autocomplete="off" value="<?php $page->title(); ?>"
                               placeholder="<?php _e('キャプション'); ?>" class="w-100 text title"/>
                    </p>
                    <?php $permalink = \Typecho\Common::url($options->routingTable['page']['url'], $options->index);
                    [$scheme, $permalink] = explode(':', $permalink, 2);
                    $permalink = ltrim($permalink, '/');
                    $permalink = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $permalink);
                    if ($page->have()) {
                        $permalink = str_replace('{cid}', $page->cid, $permalink);
                    }
                    $input = '<input type="text" id="slug" name="slug" autocomplete="off" value="' . htmlspecialchars($page->slug ?? '') . '" class="mono" />';
                    ?>
                    <p class="mono url-slug">
                        <label for="slug" class="sr-only"><?php _e('ウェブアドレスの略称'); ?></label>
                        <?php echo preg_replace("/\{slug\}/i", $input, $permalink); ?>
                    </p>
                    <p>
                        <label for="text" class="sr-only"><?php _e('ページ内容'); ?></label>
                        <textarea style="height: <?php $options->editorSize(); ?>px" autocomplete="off" id="text"
                                  name="text" class="w-100 mono"><?php echo htmlspecialchars($page->text ?? ''); ?></textarea>
                    </p>

                    <?php include 'custom-fields.php'; ?>
                    <p class="submit clearfix">
                        <span class="left">
                            <button type="button" id="btn-cancel-preview" class="btn"><i
                                    class="i-caret-left"></i> <?php _e('プレビューのキャンセル'); ?></button>
                        </span>
                        <span class="right">
                            <input type="hidden" name="cid" value="<?php $page->cid(); ?>"/>
                            <button type="button" id="btn-preview" class="btn"><i
                                    class="i-exlink"></i> <?php _e('プレビューページ'); ?></button>
                            <button type="submit" name="do" value="save" id="btn-save"
                                    class="btn"><?php _e('下書きの保存'); ?></button>
                            <button type="submit" name="do" value="publish" class="btn primary"
                                    id="btn-submit"><?php _e('ローンチページ'); ?></button>
                            <?php if ($options->markdown && (!$page->have() || $page->isMarkdown)): ?>
                                <input type="hidden" name="markdown" value="1"/>
                            <?php endif; ?>
                        </span>
                    </p>

                    <?php \Typecho\Plugin::factory('admin/write-page.php')->content($page); ?>
                </div>
                <div id="edit-secondary" class="col-mb-12 col-tb-3" role="complementary">
                    <ul class="typecho-option-tabs clearfix">
                        <li class="active w-50"><a href="#tab-advance"><?php _e('オプション'); ?></a></li>
                        <li class="w-50"><a href="#tab-files" id="tab-files-btn"><?php _e('添付ファイル'); ?></a></li>
                    </ul>

                    <div id="tab-advance" class="tab-content">
                        <section class="typecho-post-option" role="application">
                            <label for="date" class="typecho-label"><?php _e('発売日'); ?></label>
                            <p><input class="typecho-date w-100" type="text" name="date" id="date" autocomplete="off"
                                      value="<?php $page->have() && $page->created > 0 ? $page->date('Y-m-d H:i') : ''; ?>"/>
                            </p>
                        </section>

                        <section class="typecho-post-option">
                            <label for="order" class="typecho-label"><?php _e('ページ順'); ?></label>
                            <p><input type="text" id="order" name="order" value="<?php $page->order(); ?>"
                                      class="w-100"/></p>
                            <p class="description"><?php _e('カスタムページにシーケンス値を設定した後, は、この値で小さいものから大きいものへと順番に並べることができる。'); ?></p>
                        </section>

                        <section class="typecho-post-option">
                            <label for="template" class="typecho-label"><?php _e('カスタマイズされたテンプレート'); ?></label>
                            <p>
                                <select name="template" id="template">
                                    <option value=""><?php _e('非選択性'); ?></option>
                                    <?php $templates = $page->getTemplates();
                                    foreach ($templates as $template => $name): ?>
                                        <option
                                            value="<?php echo $template; ?>"<?php if ($template == $page->template): ?> selected="true"<?php endif; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            <p class="description"><?php _e('如果你为此页面选择了一个カスタマイズされたテンプレート, システムは、選択したテンプレートファイルに従って表示します。'); ?></p>
                        </section>

                        <?php \Typecho\Plugin::factory('admin/write-page.php')->option($page); ?>

                        <button type="button" id="advance-panel-btn" class="btn btn-xs"><?php _e('高级オプション'); ?> <i
                                class="i-caret-down"></i></button>
                        <div id="advance-panel">
                            <section class="typecho-post-option visibility-option">
                                <label for="visibility" class="typecho-label"><?php _e('開放性'); ?></label>
                                <p>
                                    <select id="visibility" name="visibility">
                                        <option
                                            value="publish"<?php if ($page->status == 'publish' || !$page->status): ?> selected<?php endif; ?>><?php _e('公然と'); ?></option>
                                        <option
                                            value="hidden"<?php if ($page->status == 'hidden'): ?> selected<?php endif; ?>><?php _e('ネッスル'); ?></option>
                                    </select>
                                </p>
                            </section>

                            <section class="typecho-post-option allow-option">
                                <label class="typecho-label"><?php _e('特権制御'); ?></label>
                                <ul>
                                    <li><input id="allowComment" name="allowComment" type="checkbox" value="1"
                                               <?php if ($page->allow('comment')): ?>checked="true"<?php endif; ?> />
                                        <label for="allowComment"><?php _e('コメント可'); ?></label></li>
                                    <li><input id="allowPing" name="allowPing" type="checkbox" value="1"
                                               <?php if ($page->allow('ping')): ?>checked="true"<?php endif; ?> />
                                        <label for="allowPing"><?php _e('引用許可'); ?></label></li>
                                    <li><input id="allowFeed" name="allowFeed" type="checkbox" value="1"
                                               <?php if ($page->allow('feed')): ?>checked="true"<?php endif; ?> />
                                        <label for="allowFeed"><?php _e('アグリゲーションで許可'); ?></label></li>
                                </ul>
                            </section>

                            <?php \Typecho\Plugin::factory('admin/write-page.php')->advanceOption($page); ?>
                        </div>
                        <?php if ($page->have()): ?>
                            <?php $modified = new \Typecho\Date($page->modified); ?>
                            <section class="typecho-post-option">
                                <p class="description">
                                    <br>&mdash;<br>
                                    <?php _e('このページの作成者 <a href="%s">%s</a> 確立',
                                        \Typecho\Common::url('manage-pages.php?uid=' . $page->author->uid, $options->adminUrl), $page->author->screenName); ?>
                                    <br>
                                    <?php _e('最終更新日 %s', $modified->word()); ?>
                                </p>
                            </section>
                        <?php endif; ?>
                    </div><!-- end #tab-advance -->

                    <div id="tab-files" class="tab-content hidden">
                        <?php include 'file-upload.php'; ?>
                    </div><!-- end #tab-files -->
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
include 'write-js.php';

\Typecho\Plugin::factory('admin/write-page.php')->trigger($plugged)->richEditor($page);
if (!$plugged) {
    include 'editor-js.php';
}

include 'file-upload-js.php';
include 'custom-fields-js.php';
\Typecho\Plugin::factory('admin/write-page.php')->bottom($page);
include 'footer.php';
?>
