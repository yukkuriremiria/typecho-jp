<?php
include 'common.php';
include 'header.php';
include 'menu.php';
\Widget\Contents\Post\Edit::alloc()->to($post);
?>
<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main typecho-post-area" role="form">
            <form action="<?php $security->index('/action/contents-post-edit'); ?>" method="post" name="write_post">
                <div class="col-mb-12 col-tb-9" role="main">
                    <?php if ($post->draft): ?>
                        <?php if ($post->draft['cid'] != $post->cid): ?>
                            <?php $postModifyDate = new \Typecho\Date($post->draft['modified']); ?>
                            <cite
                                class="edit-draft-notice"><?php _e('に保存されたファイルを編集しています。 %s 決議案, あなたにもできる。 <a href="%s">削除してください。</a>', $postModifyDate->word(),
                                    $security->getIndex('/action/contents-post-edit?do=deleteDraft&cid=' . $post->cid)); ?></cite>
                        <?php else: ?>
                            <cite class="edit-draft-notice"><?php _e('当前正在编辑的是未发布決議案'); ?></cite>
                        <?php endif; ?>
                        <input name="draft" type="hidden" value="<?php echo $post->draft['cid'] ?>"/>
                    <?php endif; ?>

                    <p class="title">
                        <label for="title" class="sr-only"><?php _e('キャプション'); ?></label>
                        <input type="text" id="title" name="title" autocomplete="off" value="<?php $post->title(); ?>"
                               placeholder="<?php _e('キャプション'); ?>" class="w-100 text title"/>
                    </p>
                    <?php $permalink = \Typecho\Common::url($options->routingTable['post']['url'], $options->index);
                    [$scheme, $permalink] = explode(':', $permalink, 2);
                    $permalink = ltrim($permalink, '/');
                    $permalink = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $permalink);
                    if ($post->have()) {
                        $permalink = str_replace([
                            '{cid}', '{category}', '{year}', '{month}', '{day}'
                        ], [
                            $post->cid, $post->category, $post->year, $post->month, $post->day
                        ], $permalink);
                    }
                    $input = '<input type="text" id="slug" name="slug" autocomplete="off" value="' . htmlspecialchars($post->slug ?? '') . '" class="mono" />';
                    ?>
                    <p class="mono url-slug">
                        <label for="slug" class="sr-only"><?php _e('ウェブアドレスの略称'); ?></label>
                        <?php echo preg_replace("/\{slug\}/i", $input, $permalink); ?>
                    </p>
                    <p>
                        <label for="text" class="sr-only"><?php _e('記事'); ?></label>
                        <textarea style="height: <?php $options->editorSize(); ?>px" autocomplete="off" id="text"
                                  name="text" class="w-100 mono"><?php echo htmlspecialchars($post->text ?? ''); ?></textarea>
                    </p>

                    <?php include 'custom-fields.php'; ?>

                    <p class="submit clearfix">
                        <span class="left">
                            <button type="button" id="btn-cancel-preview" class="btn"><i
                                    class="i-caret-left"></i> <?php _e('プレビューのキャンセル'); ?></button>
                        </span>
                        <span class="right">
                            <input type="hidden" name="cid" value="<?php $post->cid(); ?>"/>
                            <button type="button" id="btn-preview" class="btn"><i
                                    class="i-exlink"></i> <?php _e('プレビュー記事'); ?></button>
                            <button type="submit" name="do" value="save" id="btn-save"
                                    class="btn"><?php _e('下書きの保存'); ?></button>
                            <button type="submit" name="do" value="publish" class="btn primary"
                                    id="btn-submit"><?php _e('出版記事'); ?></button>
                            <?php if ($options->markdown && (!$post->have() || $post->isMarkdown)): ?>
                                <input type="hidden" name="markdown" value="1"/>
                            <?php endif; ?>
                        </span>
                    </p>

                    <?php \Typecho\Plugin::factory('admin/write-post.php')->content($post); ?>
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
                                      value="<?php $post->have() && $post->created > 0 ? $post->date('Y-m-d H:i') : ''; ?>"/>
                            </p>
                        </section>

                        <section class="typecho-post-option category-option">
                            <label class="typecho-label"><?php _e('分類'); ?></label>
                            <?php \Widget\Metas\Category\Rows::alloc()->to($category); ?>
                            <ul>
                                <?php
                                if ($post->have()) {
                                    $categories = array_column($post->categories, 'mid');
                                } else {
                                    $categories = [];
                                }
                                ?>
                                <?php while ($category->next()): ?>
                                    <li><?php echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $category->levels); ?><input
                                            type="checkbox" id="category-<?php $category->mid(); ?>"
                                            value="<?php $category->mid(); ?>" name="category[]"
                                            <?php if (in_array($category->mid, $categories)): ?>checked="true"<?php endif; ?>/>
                                        <label
                                            for="category-<?php $category->mid(); ?>"><?php $category->name(); ?></label>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </section>

                        <section class="typecho-post-option">
                            <label for="token-input-tags" class="typecho-label"><?php _e('タブ'); ?></label>
                            <p><input id="tags" name="tags" type="text" value="<?php $post->tags(',', false); ?>"
                                      class="w-100 text"/></p>
                        </section>

                        <?php \Typecho\Plugin::factory('admin/write-post.php')->option($post); ?>

                        <button type="button" id="advance-panel-btn" class="btn btn-xs"><?php _e('高级オプション'); ?> <i
                                class="i-caret-down"></i></button>
                        <div id="advance-panel">
                            <?php if ($user->pass('editor', true)): ?>
                                <section class="typecho-post-option visibility-option">
                                    <label for="visibility" class="typecho-label"><?php _e('開放性'); ?></label>
                                    <p>
                                        <select id="visibility" name="visibility">
                                            <?php if ($user->pass('editor', true)): ?>
                                                <option
                                                    value="publish"<?php if (($post->status == 'publish' && !$post->password) || !$post->status): ?> selected<?php endif; ?>><?php _e('公然と'); ?></option>
                                                <option
                                                    value="hidden"<?php if ($post->status == 'hidden'): ?> selected<?php endif; ?>><?php _e('ネッスル'); ?></option>
                                                <option
                                                    value="password"<?php if (strlen($post->password ?? '') > 0): ?> selected<?php endif; ?>><?php _e('パスワード保護'); ?></option>
                                                <option
                                                    value="private"<?php if ($post->status == 'private'): ?> selected<?php endif; ?>><?php _e('親しい'); ?></option>
                                            <?php endif; ?>
                                            <option
                                                value="waiting"<?php if (!$user->pass('editor', true) || $post->status == 'waiting'): ?> selected<?php endif; ?>><?php _e('承認待ち'); ?></option>
                                        </select>
                                    </p>
                                    <p id="post-password"<?php if (strlen($post->password ?? '') == 0): ?> class="hidden"<?php endif; ?>>
                                        <label for="protect-pwd" class="sr-only">コンテンツパスワード</label>
                                        <input type="text" name="password" id="protect-pwd" class="text-s"
                                               value="<?php $post->password(); ?>" size="16"
                                               placeholder="<?php _e('コンテンツパスワード'); ?>" autocomplete="off"/>
                                    </p>
                                </section>
                            <?php endif; ?>

                            <section class="typecho-post-option allow-option">
                                <label class="typecho-label"><?php _e('特権制御'); ?></label>
                                <ul>
                                    <li><input id="allowComment" name="allowComment" type="checkbox" value="1"
                                               <?php if ($post->allow('comment')): ?>checked="true"<?php endif; ?> />
                                        <label for="allowComment"><?php _e('コメント可'); ?></label></li>
                                    <li><input id="allowPing" name="allowPing" type="checkbox" value="1"
                                               <?php if ($post->allow('ping')): ?>checked="true"<?php endif; ?> />
                                        <label for="allowPing"><?php _e('引用許可'); ?></label></li>
                                    <li><input id="allowFeed" name="allowFeed" type="checkbox" value="1"
                                               <?php if ($post->allow('feed')): ?>checked="true"<?php endif; ?> />
                                        <label for="allowFeed"><?php _e('アグリゲーションで許可'); ?></label></li>
                                </ul>
                            </section>

                            <section class="typecho-post-option">
                                <label for="trackback" class="typecho-label"><?php _e('引用'); ?></label>
                                <p><textarea id="trackback" class="w-100 mono" name="trackback" rows="2"></textarea></p>
                                <p class="description"><?php _e('1行に1つの参照アドレス, キャリッジリターン'); ?></p>
                            </section>

                            <?php \Typecho\Plugin::factory('admin/write-post.php')->advanceOption($post); ?>
                        </div><!-- end #advance-panel -->

                        <?php if ($post->have()): ?>
                            <?php $modified = new \Typecho\Date($post->modified); ?>
                            <section class="typecho-post-option">
                                <p class="description">
                                    <br>&mdash;<br>
                                    <?php _e('本稿の執筆者 <a href="%s">%s</a> 書く',
                                        \Typecho\Common::url('manage-posts.php?uid=' . $post->author->uid, $options->adminUrl), $post->author->screenName); ?>
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

\Typecho\Plugin::factory('admin/write-post.php')->trigger($plugged)->richEditor($post);
if (!$plugged) {
    include 'editor-js.php';
}

include 'file-upload-js.php';
include 'custom-fields-js.php';
\Typecho\Plugin::factory('admin/write-post.php')->bottom($post);
include 'footer.php';
?>
