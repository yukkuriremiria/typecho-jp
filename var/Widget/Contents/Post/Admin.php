<?php

namespace Widget\Contents\Post;

use Typecho\Cookie;
use Typecho\Db;
use Typecho\Db\Exception as DbException;
use Typecho\Widget\Exception;
use Typecho\Db\Query;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 記事管理リスト・コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Admin extends Contents
{
    /**
     * 数値計算に使用されるステートメント・オブジェクト
     *
     * @var Query
     */
    private $countSql;

    /**
     * 全記事数
     *
     * @var integer
     */
    private $total = false;

    /**
     * 現在のページ
     *
     * @var integer
     */
    private $currentPage;

    /**
     * メニュータイトルの取得
     *
     * @return string
     * @throws Exception|DbException
     */
    public function getMenuTitle(): string
    {
        if (isset($this->request->uid)) {
            return _t('%s記事', $this->db->fetchObject($this->db->select('screenName')->from('table.users')
                ->where('uid = ?', $this->request->filter('int')->uid))->screenName);
        }

        throw new Exception(_t('ユーザーが存在しない'), 404);
    }

    /**
     * フィルター関数のオーバーロード
     *
     * @param array $value
     * @return array
     * @throws DbException
     */
    public function filter(array $value): array
    {
        $value = parent::filter($value);

        if (!empty($value['parent'])) {
            $parent = $this->db->fetchObject($this->select()->where('cid = ?', $value['parent']));

            if (!empty($parent)) {
                $value['commentsNum'] = $parent->commentsNum;
            }
        }

        return $value;
    }

    /**
     * 実行可能関数
     *
     * @throws DbException
     */
    public function execute()
    {
        $this->parameter->setDefault('pageSize=20');
        $this->currentPage = $this->request->get('page', 1);

        /** 基本的なクエリの構築 */
        $select = $this->select();

        /** 上記の編集権限がある場合,すべての記事が閲覧可能,反之只能查看自己記事 */
        if (!$this->user->pass('editor', true)) {
            $select->where('table.contents.authorId = ?', $this->user->uid);
        } else {
            if ('on' == $this->request->__typecho_all_posts) {
                Cookie::set('__typecho_all_posts', 'on');
            } else {
                if ('off' == $this->request->__typecho_all_posts) {
                    Cookie::set('__typecho_all_posts', 'off');
                }

                if ('on' != Cookie::get('__typecho_all_posts')) {
                    $select->where('table.contents.authorId = ?', isset($this->request->uid) ?
                        $this->request->filter('int')->uid : $this->user->uid);
                }
            }
        }

        /** ステータスで検索 */
        if ('draft' == $this->request->status) {
            $select->where('table.contents.type = ?', 'post_draft');
        } elseif ('waiting' == $this->request->status) {
            $select->where(
                '(table.contents.type = ? OR table.contents.type = ?) AND table.contents.status = ?',
                'post',
                'post_draft',
                'waiting'
            );
        } else {
            $select->where(
                'table.contents.type = ? OR (table.contents.type = ? AND table.contents.parent = ?)',
                'post',
                'post_draft',
                0
            );
        }

        /** ろ過分類 */
        if (null != ($category = $this->request->category)) {
            $select->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.relationships.mid = ?', $category);
        }

        /** フィルタータイトル */
        if (null != ($keywords = $this->request->filter('search')->keywords)) {
            $args = [];
            $keywordsList = explode(' ', $keywords);
            $args[] = implode(' OR ', array_fill(0, count($keywordsList), 'table.contents.title LIKE ?'));

            foreach ($keywordsList as $keyword) {
                $args[] = '%' . $keyword . '%';
            }

            call_user_func_array([$select, 'where'], $args);
        }

        /** 計数オブジェクトに値を割り当てる,クローンオブジェクト */
        $this->countSql = clone $select;

        /** お問い合わせ */
        $select->order('table.contents.cid', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 出力ページング
     *
     * @throws Exception
     * @throws DbException
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
        $nav->render('&laquo;', '&raquo;');
    }

    /**
     * 現在の記事の草稿
     *
     * @return bool
     * @throws DbException
     */
    protected function ___hasSaved(): bool
    {
        if (in_array($this->type, ['post_draft', 'page_draft'])) {
            return true;
        }

        $savedPost = $this->db->fetchRow($this->db->select('cid', 'modified', 'status')
            ->from('table.contents')
            ->where(
                'table.contents.parent = ? AND (table.contents.type = ? OR table.contents.type = ?)',
                $this->cid,
                'post_draft',
                'page_draft'
            )
            ->limit(1));

        if ($savedPost) {
            $this->modified = $savedPost['modified'];
            return true;
        }

        return false;
    }
}

