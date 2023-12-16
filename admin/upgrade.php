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
                <div id="typecho-welcome">
                    <form action="<?php echo $security->getTokenUrl(
                        \Typecho\Router::url('do', ['action' => 'upgrade', 'widget' => 'Upgrade'],
                            \Typecho\Common::url('index.php', $options->rootUrl))); ?>" method="post">
                        <h3><?php _e('新バージョン検出!'); ?></h3>
                        <ul>
                            <li><?php _e('システム・プログラムを更新しました。, また、アップグレードを完了するには、次のステップを実行する必要があります。'); ?></li>
                            <li><?php _e('この手順により、システムは <strong>%s</strong> にアップグレードする。 <strong>%s</strong>', $options->version, \Typecho\Common::VERSION); ?></li>
                            <li><strong
                                    class="warning"><?php _e('アップグレードする前に、次のことを行うことを強くお勧めします。<a href="%s">データのバックアップ</a>', \Typecho\Common::url('backup.php', $options->adminUrl)); ?></strong>
                            </li>
                        </ul>
                        <p>
                            <button class="btn primary" type="submit"><?php _e('アップグレードの完了 &raquo;'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
?>
<script>
    (function () {
        if (window.sessionStorage) {
            sessionStorage.removeItem('update');
        }
    })();
</script>
<?php include 'footer.php'; ?>
