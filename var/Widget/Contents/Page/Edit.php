<?php

namespace Widget\Contents\Page;

use Typecho\Common;
use Typecho\Date;
use Typecho\Widget\Exception;
use Widget\Contents\Post\Edit as PostEdit;
use Widget\ActionInterface;
use Widget\Notice;
use Widget\Service;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ページ構成要素の編集
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends PostEdit implements ActionInterface
{
    /**
     * のカスタムフィールドhookな
     *
     * @var string
     * @access protected
     */
    protected $themeCustomFieldsHook = 'themePageFields';

    /**
     * 実行可能関数
     *
     * @access public
     * @return void
     * @throws Exception
     * @throws \Typecho\Db\Exception
     */
    public function execute()
    {
        /** 編集者以上であること */
        $this->user->pass('editor');

        /** 記事コンテンツを入手する */
        if (!empty($this->request->cid)) {
            $this->db->fetchRow($this->select()
                ->where('table.contents.type = ? OR table.contents.type = ?', 'page', 'page_draft')
                ->where('table.contents.cid = ?', $this->request->filter('int')->cid)
                ->limit(1), [$this, 'push']);

            if ('page_draft' == $this->status && $this->parent) {
                $this->response->redirect(Common::url('write-page.php?cid=' . $this->parent, $this->options->adminUrl));
            }

            if (!$this->have()) {
                throw new Exception(_t('ページが存在しない'), 404);
            } elseif (!$this->allow('edit')) {
                throw new Exception(_t('編集権限なし'), 403);
            }
        }
    }

    /**
     * 出版記事
     */
    public function writePage()
    {
        $contents = $this->request->from(
            'text',
            'template',
            'allowComment',
            'allowPing',
            'allowFeed',
            'slug',
            'order',
            'visibility'
        );

        $contents['title'] = $this->request->get('title', _t('無名ページ'));
        $contents['created'] = $this->getCreated();
        $contents['visibility'] = ('hidden' == $contents['visibility'] ? 'hidden' : 'publish');

        if ($this->request->markdown && $this->options->markdown) {
            $contents['text'] = '<!--markdown-->' . $contents['text'];
        }

        $contents = self::pluginHandle()->write($contents, $this);

        if ($this->request->is('do=publish')) {
            /** 既存の記事の再投稿 */
            $contents['type'] = 'page';
            $this->publish($contents);

            // プラグイン・インターフェイスの公開を終了する
            self::pluginHandle()->finishPublish($contents, $this);

            /** 送信ping */
            Service::alloc()->sendPing($this);

            /** アラートメッセージの設定 */
            Notice::alloc()->set(
                _t('ウェブページ "<a href="%s">%s</a>" 出版', $this->permalink, $this->title),
                'success'
            );

            /** ハイライトの設定 */
            Notice::alloc()->highlight($this->theId);

            /** ウェブページ跳转 */
            $this->response->redirect(Common::url('manage-pages.php?', $this->options->adminUrl));
        } else {
            /** 記事を保存 */
            $contents['type'] = 'page_draft';
            $this->save($contents);

            // プラグイン・インターフェイスの公開を終了する
            self::pluginHandle()->finishSave($contents, $this);

            /** ハイライトの設定 */
            Notice::alloc()->highlight($this->cid);

            if ($this->request->isAjax()) {
                $created = new Date($this->options->time);
                $this->response->throwJson([
                    'success' => 1,
                    'time'    => $created->format('H:i:s A'),
                    'cid'     => $this->cid,
                    'draftId' => $this->draft['cid']
                ]);
            } else {
                /** アラートメッセージの設定 */
                Notice::alloc()->set(_t('概要 "%s" 保存済み', $this->title), 'success');

                /** 返回原ウェブページ */
                $this->response->redirect(Common::url('write-page.php?cid=' . $this->cid, $this->options->adminUrl));
            }
        }
    }

    /**
     * 标记ウェブページ
     *
     * @throws \Typecho\Db\Exception
     */
    public function markPage()
    {
        $status = $this->request->get('status');
        $statusList = [
            'publish' => _t('公然と'),
            'hidden'  => _t('ネッスル')
        ];

        if (!isset($statusList[$status])) {
            $this->response->goBack();
        }

        $pages = $this->request->filter('int')->getArray('cid');
        $markCount = 0;

        foreach ($pages as $page) {
            // タグ付けプラグインのインターフェース
            self::pluginHandle()->mark($status, $page, $this);
            $condition = $this->db->sql()->where('cid = ?', $page);

            if ($this->db->query($condition->update('table.contents')->rows(['status' => $status]))) {
                // 处理概要
                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $page, 'page_draft')
                    ->limit(1));

                if (!empty($draft)) {
                    $this->db->query($this->db->update('table.contents')->rows(['status' => $status])
                        ->where('cid = ?', $draft['cid']));
                }

                // 完成タグ付けプラグインのインターフェース
                self::pluginHandle()->finishMark($status, $page, $this);

                $markCount++;
            }

            unset($condition);
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $markCount > 0 ? _t('ウェブページ已经被标记为<strong>%s</strong>', $statusList[$status]) : _t('没有ウェブページ被标记'),
                $markCount > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * 删除ウェブページ
     *
     * @throws \Typecho\Db\Exception
     */
    public function deletePage()
    {
        $pages = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($pages as $page) {
            // プラグインインターフェースの削除
            self::pluginHandle()->delete($page, $this);

            if ($this->delete($this->db->sql()->where('cid = ?', $page))) {
                /** コメント削除 */
                $this->db->query($this->db->delete('table.comments')
                    ->where('cid = ?', $page));

                /** 添付ファイルのリンク解除 */
                $this->unAttach($page);

                /** ホームページのリンク解除 */
                if ($this->options->frontPage == 'page:' . $page) {
                    $this->db->query($this->db->update('table.options')
                        ->rows(['value' => 'recent'])
                        ->where('name = ?', 'frontPage'));
                }

                /** 删除概要 */
                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $page, 'page_draft')
                    ->limit(1));

                /** カスタムフィールドの削除 */
                $this->deleteFields($page);

                if ($draft) {
                    $this->deleteDraft($draft['cid']);
                    $this->deleteFields($draft['cid']);
                }

                // 完成プラグインインターフェースの削除
                self::pluginHandle()->finishDelete($page, $this);

                $deleteCount++;
            }
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $deleteCount > 0 ? _t('ウェブページ已经被删除') : _t('没有ウェブページ被删除'),
                $deleteCount > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * 删除ウェブページ所属概要
     *
     * @throws \Typecho\Db\Exception
     */
    public function deletePageDraft()
    {
        $pages = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($pages as $page) {
            /** 删除概要 */
            $draft = $this->db->fetchRow($this->db->select('cid')
                ->from('table.contents')
                ->where('table.contents.parent = ? AND table.contents.type = ?', $page, 'page_draft')
                ->limit(1));

            if ($draft) {
                $this->deleteDraft($draft['cid']);
                $this->deleteFields($draft['cid']);
                $deleteCount++;
            }
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $deleteCount > 0 ? _t('概要已经被删除') : _t('没有概要被删除'),
                $deleteCount > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * ウェブページ排序
     *
     * @throws \Typecho\Db\Exception
     */
    public function sortPage()
    {
        $pages = $this->request->filter('int')->getArray('cid');

        if ($pages) {
            foreach ($pages as $sort => $cid) {
                $this->db->query($this->db->update('table.contents')->rows(['order' => $sort + 1])
                    ->where('cid = ?', $cid));
            }
        }

        if (!$this->request->isAjax()) {
            /** オリジナルページへ */
            $this->response->goBack();
        } else {
            $this->response->throwJson(['success' => 1, 'message' => _t('ウェブページ排序已经完成')]);
        }
    }

    /**
     * バインド・アクション
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=publish') || $this->request->is('do=save'))->writePage();
        $this->on($this->request->is('do=delete'))->deletePage();
        $this->on($this->request->is('do=mark'))->markPage();
        $this->on($this->request->is('do=deleteDraft'))->deletePageDraft();
        $this->on($this->request->is('do=sort'))->sortPage();
        $this->response->redirect($this->options->adminUrl);
    }
}
