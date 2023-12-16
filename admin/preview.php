<?php

include 'common.php';

/** コンテンツへのアクセス Widget */
\Widget\Archive::alloc('type=single&checkPermalink=0&preview=1')->to($content);

/** の存在を検出する。 */
if (!$content->have()) {
    $response->redirect($options->adminUrl);
}

/** パーミッションの検出 */
if (!$user->pass('editor', true) && $content->authorId != $user->uid) {
    $response->redirect($options->adminUrl);
}

/** 出力内容 */
$content->render();
?>
<script>
    window.onbeforeunload = function () {
        if (!!window.parent) {
            window.parent.postMessage('cancelPreview', '<?php $options->rootUrl(); ?>');
        }
    }
</script>
