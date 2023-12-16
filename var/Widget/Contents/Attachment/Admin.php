<?php

namespace Widget\Contents\Attachment;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ファイル管理リスト・コンポーネント
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
     * 実行可能関数
     *
     * @return void
     * @throws Exception|\Typecho\Widget\Exception
     */
    public function execute()
    {
        $this->parameter->setDefault('pageSize=20');
        $this->currentPage = $this->request->get('page', 1);

        /** 基本的なクエリの構築 */
        $select = $this->select()->where('table.contents.type = ?', 'attachment');

        /** 上記の編集権限がある場合,すべてのファイルを閲覧可能,その代わり、自分のファイルだけを見ることができます。 */
        if (!$this->user->pass('editor', true)) {
            $select->where('table.contents.authorId = ?', $this->user->uid);
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
        $select->order('table.contents.created', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 出力ページング
     *
     * @return void
     * @throws Exception|\Typecho\Widget\Exception
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
     * 関連記事
     *
     * @return Config
     * @throws Exception
     */
    protected function ___parentPost(): Config
    {
        return new Config($this->db->fetchRow(
            $this->select()->where('table.contents.cid = ?', $this->parentId)->limit(1)
        ));
    }
}
