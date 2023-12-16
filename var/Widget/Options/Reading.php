<?php

namespace Widget\Options;

use Typecho\Db\Exception;
use Typecho\Plugin;
use Typecho\Widget\Helper\Form;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 記事閲覧設定コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Reading extends Permalink
{
    /**
     * 更新アクションの実行
     *
     * @throws Exception
     */
    public function updateReadingSettings()
    {
        /** バリデーション形式  */
        if ($this->form()->validate()) {
            $this->response->goBack();
        }

        $settings = $this->request->from(
            'postDateFormat',
            'frontPage',
            'frontArchive',
            'pageSize',
            'postsListSize',
            'feedFullText'
        );

        if (
            'page' == $settings['frontPage'] && isset($this->request->frontPagePage) &&
            $this->db->fetchRow($this->db->select('cid')
                ->from('table.contents')->where('type = ?', 'page')
                ->where('status = ?', 'publish')->where('created < ?', $this->options->time)
                ->where('cid = ?', $pageId = intval($this->request->frontPagePage)))
        ) {
            $settings['frontPage'] = 'page:' . $pageId;
        } elseif (
            'file' == $settings['frontPage'] && isset($this->request->frontPageFile) &&
            file_exists(__TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_THEME_DIR__ . '/' . $this->options->theme . '/' .
                ($file = trim($this->request->frontPageFile, " ./\\")))
        ) {
            $settings['frontPage'] = 'file:' . $file;
        } else {
            $settings['frontPage'] = 'recent';
        }

        if ('recent' != $settings['frontPage']) {
            $settings['frontArchive'] = empty($settings['frontArchive']) ? 0 : 1;
            if ($settings['frontArchive']) {
                $routingTable = $this->options->routingTable;
                $routingTable['archive']['url'] = '/' . ltrim($this->encodeRule($this->request->archivePattern), '/');
                $routingTable['archive_page']['url'] = rtrim($routingTable['archive']['url'], '/')
                    . '/page/[page:digital]/';

                if (isset($routingTable[0])) {
                    unset($routingTable[0]);
                }

                $settings['routingTable'] = serialize($routingTable);
            }
        } else {
            $settings['frontArchive'] = 0;
        }

        foreach ($settings as $name => $value) {
            $this->update(['value' => $value], $this->db->sql()->where('name = ?', $name));
        }

        Notice::alloc()->set(_t("設定が保存されました"), 'success');
        $this->response->goBack();
    }

    /**
     * 出力フォームの構造
     *
     * @return Form
     */
    public function form(): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/options-reading'), Form::POST_METHOD);

        /** 記事の日付形式 */
        $postDateFormat = new Form\Element\Text(
            'postDateFormat',
            null,
            $this->options->postDateFormat,
            _t('記事の日付形式'),
            _t('この書式は、記事アーカイブに表示される日付のデフォルトの表示書式を指定するために使われる。.') . '<br />'
            . _t('このフォーマットは、テーマによっては動作しない場合があります。, テーマの作者は日付フォーマットをカスタマイズできる.') . '<br />'
            . _t('をご参照ください。 <a href="https://www.php.net/manual/zh/function.date.php">PHP 日付の書式</a>.')
        );
        $postDateFormat->input->setAttribute('class', 'w-40 mono');
        $form->addInput($postDateFormat->addRule('xssCheck', _t('日付の書式に特殊文字を利用するしないでください！')));

        //ホームディスプレイ
        $frontPageParts = explode(':', $this->options->frontPage);
        $frontPageType = $frontPageParts[0];
        $frontPageValue = count($frontPageParts) > 1 ? $frontPageParts[1] : '';

        $frontPageOptions = [
            'recent' => _t('最新の掲載記事を表示')
        ];

        $frontPattern = '</label></span><span class="multiline front-archive%class%">'
            . '<input type="checkbox" id="frontArchive" name="frontArchive" value="1"'
            . ($this->options->frontArchive && 'recent' != $frontPageType ? ' checked' : '') . ' />
<label for="frontArchive">' . _t(
                'また、記事一覧ページのパスを %s',
                '<input type="text" name="archivePattern" class="w-20 mono" value="'
                . htmlspecialchars($this->decodeRule($this->options->routingTable['archive']['url'])) . '" />'
            )
            . '</label>';

        // ページ一覧
        $pages = $this->db->fetchAll($this->db->select('cid', 'title')
            ->from('table.contents')->where('type = ?', 'page')
            ->where('status = ?', 'publish')->where('created < ?', $this->options->time));

        if (!empty($pages)) {
            $pagesSelect = '<select name="frontPagePage" id="frontPage-frontPagePage">';
            foreach ($pages as $page) {
                $selected = '';
                if ('page' == $frontPageType && $page['cid'] == $frontPageValue) {
                    $selected = ' selected="true"';
                }

                $pagesSelect .= '<option value="' . $page['cid'] . '"' . $selected
                    . '>' . $page['title'] . '</option>';
            }
            $pagesSelect .= '</select>';
            $frontPageOptions['page'] = _t(
                '利用する %s ホームページとしてのページ',
                '</label>' . $pagesSelect . '<label for="frontPage-frontPagePage">'
            );
            $selectedFrontPageType = 'page';
        }

        // ファイルリストのカスタマイズ
        $files = glob($this->options->themeFile($this->options->theme, '*.php'));
        $filesSelect = '';

        foreach ($files as $file) {
            $info = Plugin::parseInfo($file);
            $file = basename($file);

            if ('index.php' != $file && 'index' == $info['title']) {
                $selected = '';
                if ('file' == $frontPageType && $file == $frontPageValue) {
                    $selected = ' selected="true"';
                }

                $filesSelect .= '<option value="' . $file . '"' . $selected
                    . '>' . $file . '</option>';
            }
        }

        if (!empty($filesSelect)) {
            $frontPageOptions['file'] = _t(
                'ダイレクト・コール %s テンプレートファイル',
                '</label><select name="frontPageFile" id="frontPage-frontPageFile">'
                . $filesSelect . '</select><label for="frontPage-frontPageFile">'
            );
            $selectedFrontPageType = 'file';
        }

        if (isset($frontPageOptions[$frontPageType]) && 'recent' != $frontPageType && isset($selectedFrontPageType)) {
            $selectedFrontPageType = $frontPageType;
            $frontPattern = str_replace('%class%', '', $frontPattern);
        }

        if (isset($selectedFrontPageType)) {
            $frontPattern = str_replace('%class%', ' hidden', $frontPattern);
            $frontPageOptions[$selectedFrontPageType] .= $frontPattern;
        }

        $frontPage = new Form\Element\Radio('frontPage', $frontPageOptions, $frontPageType, _t('サイトホーム'));
        $form->addInput($frontPage->multiMode());

        /** 記事リスト数 */
        $postsListSize = new Form\Element\Text(
            'postsListSize',
            null,
            $this->options->postsListSize,
            _t('記事リスト数'),
            _t('此数目用于指定显示在侧边栏中的記事リスト数.')
        );
        $postsListSize->input->setAttribute('class', 'w-20');
        $form->addInput($postsListSize->addRule('isInteger', _t('番号をご記入ください。')));

        /** ページあたりの記事数 */
        $pageSize = new Form\Element\Text(
            'pageSize',
            null,
            $this->options->pageSize,
            _t('ページあたりの記事数'),
            _t('この数値は、記事アーカイブの出力に表示される1ページあたりの記事数を指定するために使われます。.')
        );
        $pageSize->input->setAttribute('class', 'w-20');
        $form->addInput($pageSize->addRule('isInteger', _t('番号をご記入ください。')));

        /** FEED全文出力 */
        $feedFullText = new Form\Element\Radio(
            'feedFullText',
            ['0' => _t('出力サマリーのみ'), '1' => _t('全文出力')],
            $this->options->feedFullText,
            _t('聚合全文出力'),
            _t('記事の全文をアグリゲーションに出力したくない場合,请利用する出力サマリーのみ选项.') . '<br />'
            . _t('摘要的文字取决于你在文章中利用する分隔符的位置.')
        );
        $form->addInput($feedFullText);

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * バインド・アクション
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updateReadingSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
