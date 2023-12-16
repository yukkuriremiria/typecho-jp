<?php
include 'common.php';
include 'header.php';
include 'menu.php';

\Widget\Themes\Files::alloc()->to($files);
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12">
                <ul class="typecho-option-tabs fix-tabs clearfix">
                    <li><a href="<?php $options->adminUrl('themes.php'); ?>"><?php _e('使用可能な外観'); ?></a></li>
                    <li class="current"><a href="<?php $options->adminUrl('theme-editor.php'); ?>">
                            <?php if ($options->theme == $files->theme): ?>
                                <?php _e('現在の外観を編集する'); ?>
                            <?php else: ?>
                                <?php _e('コンパイラ%s外装状態', ' <cite>' . $files->theme . '</cite> '); ?>
                            <?php endif; ?>
                        </a></li>
                    <?php if (\Widget\Themes\Config::isExists()): ?>
                        <li><a href="<?php $options->adminUrl('options-theme.php'); ?>"><?php _e('设置外装状態'); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="typecho-edit-theme">
                <div class="col-mb-12 col-tb-8 col-9 content">
                    <form method="post" name="theme" id="theme"
                          action="<?php $security->index('/action/themes-edit'); ?>">
                        <label for="content" class="sr-only"><?php _e('コンパイラ源码'); ?></label>
                        <textarea name="content" id="content" class="w-100 mono"
                                  <?php if (!$files->currentIsWriteable()): ?>readonly<?php endif; ?>><?php echo $files->currentContent(); ?></textarea>
                        <p class="typecho-option typecho-option-submit">
                            <?php if ($files->currentIsWriteable()): ?>
                                <input type="hidden" name="theme" value="<?php echo $files->currentTheme(); ?>"/>
                                <input type="hidden" name="edit" value="<?php echo $files->currentFile(); ?>"/>
                                <button type="submit" class="btn primary"><?php _e('ファイルの保存'); ?></button>
                            <?php else: ?>
                                <em><?php _e('このファイルは書き込めません'); ?></em>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
                <ul class="col-mb-12 col-tb-4 col-3">
                    <li><strong>テンプレートファイル</strong></li>
                    <?php while ($files->next()): ?>
                        <li<?php if ($files->current): ?> class="current"<?php endif; ?>>
                            <a href="<?php $options->adminUrl('theme-editor.php?theme=' . $files->currentTheme() . '&file=' . $files->file); ?>"><?php $files->file(); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
\Typecho\Plugin::factory('admin/theme-editor.php')->bottom($files);
include 'footer.php';
?>
