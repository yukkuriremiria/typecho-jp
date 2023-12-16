<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('ウェブサイト LOGO 住所'),
        _t('ここに画像を記入する URL 住所, をサイトタイトルの前に付ける。 LOGO')
    );

    $form->addInput($logoUrl);

    $sidebarBlock = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('最新の投稿を表示'),
            'ShowRecentComments' => _t('最近の返信を表示する'),
            'ShowCategory'       => _t('分類を表示'),
            'ShowArchive'        => _t('アーカイブを表示'),
            'ShowOther'          => _t('その他の雑貨を表示する')
        ],
        ['ShowRecentPosts', 'ShowRecentComments', 'ShowCategory', 'ShowArchive', 'ShowOther'],
        _t('サイドバー表示')
    );

    $form->addInput($sidebarBlock->multiMode());
}

/*
function themeFields($layout)
{
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('ウェブサイトLOGO住所'),
        _t('ここに画像を記入するURL住所, をサイトタイトルの前に付ける。LOGO')
    );
    $layout->addItem($logoUrl);
}
*/
