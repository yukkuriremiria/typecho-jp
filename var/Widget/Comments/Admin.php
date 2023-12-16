<?php

namespace Widget\Comments;

use Typecho\Cookie;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Comments;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * バックエンド・コメント出力コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Admin extends Comments
{
    /**
     * ページングターゲット
     *
     * @access private
     * @var Query
     */
    private $countSql;

    /**
     * 現在のページ
     *
     * @access private
     * @var integer
     */
    private $currentPage;

    /**
     * 全記事数
     *
     * @access private
     * @var integer
     */
    private $total = false;

    /**
     * メニュータイトルの取得
     *
     * @return string
     * @throws Exception
     */
    public function getMenuTitle(): string
    {
        $content = $this->parentContent;

        if ($content) {
            return _t('%s批評', $content['title']);
        }

        throw new Exception(_t('コンテンツが存在しない'), 404);
    }

    /**
     * 実行可能関数
     *
     * @throws Db\Exception|Exception
     */
    public function execute()
    {
        $select = $this->select();
        $this->parameter->setDefault('pageSize=20');
        $this->currentPage = $this->request->get('page', 1);

        /** フィルタータイトル */
        if (null != ($keywords = $this->request->filter('search')->keywords)) {
            $select->where('table.comments.text LIKE ?', '%' . $keywords . '%');
        }

        /** コントリビューター以上の権限を持っている場合,コメントはすべて閲覧可能,反之只能查看自己批評 */
        if (!$this->user->pass('editor', true)) {
            $select->where('table.comments.ownerId = ?', $this->user->uid);
        } elseif (!isset($this->request->cid)) {
            if ('on' == $this->request->__typecho_all_comments) {
                Cookie::set('__typecho_all_comments', 'on');
            } else {
                if ('off' == $this->request->__typecho_all_comments) {
                    Cookie::set('__typecho_all_comments', 'off');
                }

                if ('on' != Cookie::get('__typecho_all_comments')) {
                    $select->where('table.comments.ownerId = ?', $this->user->uid);
                }
            }
        }

        if (in_array($this->request->status, ['approved', 'waiting', 'spam'])) {
            $select->where('table.comments.status = ?', $this->request->status);
        } elseif ('hold' == $this->request->status) {
            $select->where('table.comments.status <> ?', 'approved');
        } else {
            $select->where('table.comments.status = ?', 'approved');
        }

        //記事別アーカイブ機能の追加
        if (isset($this->request->cid)) {
            $select->where('table.comments.cid = ?', $this->request->filter('int')->cid);
        }

        $this->countSql = clone $select;

        $select->order('table.comments.coid', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 出力ページング
     *
     * @throws Exception|Db\Exception
     */
    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        /** ボックス・ページングの使用 */
        $nav = new Box(
            false === $this->total ? $this->total = $this->size($this->countSql) : $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );
        $nav->render(_t('&laquo;'), _t('&raquo;'));
    }

    /**
     * 現在のコンテンツ構造を取得する
     *
     * @return array|null
     * @throws Db\Exception
     */
    protected function ___parentContent(): ?array
    {
        $cid = isset($this->request->cid) ? $this->request->filter('int')->cid : $this->cid;
        return $this->db->fetchRow(Contents::alloc()->select()
            ->where('table.contents.cid = ?', $cid)
            ->limit(1), [Contents::alloc(), 'filter']);
    }
}
