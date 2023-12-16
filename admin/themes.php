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
                <ul class="typecho-option-tabs fix-tabs clearfix">
                    <li class="current"><a href="<?php $options->adminUrl('themes.php'); ?>"><?php _e('使用可能な外観'); ?></a>
                    </li>
                    <?php if (\Widget\Themes\Files::isWriteable()): ?>
                        <li><a href="<?php $options->adminUrl('theme-editor.php'); ?>"><?php _e('現在の外観を編集する'); ?></a></li>
                    <?php endif; ?>
                    <?php if (\Widget\Themes\Config::isExists()): ?>
                        <li><a href="<?php $options->adminUrl('options-theme.php'); ?>"><?php _e('外観の設定'); ?></a></li>
                    <?php endif; ?>
                </ul>

                <div class="typecho-table-wrap">
                    <table class="typecho-list-table typecho-theme-list">
                        <colgroup>
                            <col width="35%"/>
                            <col/>
                        </colgroup>

                        <thead>
                        <th><?php _e('スクリーンショット'); ?></th>
                        <th><?php _e('しょうさい'); ?></th>
                        </thead>

                        <tbody>
                        <?php if ($options->missingTheme): ?>
                            <tr id="theme-<?php $options->missingTheme; ?>" class="current">
                                <td colspan="2" class="warning">
                                    <p><strong><?php _e('を使用していることを検出します。 "%s" 外観ファイルが存在しない，このビューを再アップロードするか、別の外観を有効にすることができます。。', $options->missingTheme); ?></strong></p>
                                    <ul>
                                        <li><?php _e('このビューを再アップロードした後、現在のページを更新する。，この警告は消えます。。'); ?></li>
                                        <li><?php _e('新しい外観，現在の外観の設定データが削除される。'); ?></li>
                                    </ul>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php \Widget\Themes\Rows::alloc()->to($themes); ?>
                        <?php while ($themes->next()): ?>
                            <tr id="theme-<?php $themes->name(); ?>"
                                class="<?php if ($themes->activated && !$options->missingTheme): ?>current<?php endif; ?>">
                                <td valign="top"><img src="<?php $themes->screen(); ?>"
                                                      alt="<?php $themes->name(); ?>"/></td>
                                <td valign="top">
                                    <h3><?php '' != $themes->title ? $themes->title() : $themes->name(); ?></h3>
                                    <cite>
                                        <?php if ($themes->author): ?><?php _e('著者'); ?>: <?php if ($themes->homepage): ?><a href="<?php $themes->homepage() ?>"><?php endif; ?><?php $themes->author(); ?><?php if ($themes->homepage): ?></a><?php endif; ?> &nbsp;&nbsp;<?php endif; ?>
                                        <?php if ($themes->version): ?><?php _e('リリース'); ?>: <?php $themes->version() ?><?php endif; ?>
                                    </cite>
                                    <p><?php echo nl2br($themes->description); ?></p>
                                    <?php if ($options->theme != $themes->name || $options->missingTheme): ?>
                                        <p>
                                            <?php if (\Widget\Themes\Files::isWriteable()): ?>
                                                <a class="edit"
                                                   href="<?php $options->adminUrl('theme-editor.php?theme=' . $themes->name); ?>"><?php _e('コンパイラ'); ?></a> &nbsp;
                                            <?php endif; ?>
                                            <a class="activate"
                                               href="<?php $security->index('/action/themes-edit?change=' . $themes->name); ?>"><?php _e('使い始める'); ?></a>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
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
