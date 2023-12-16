<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * バックエンド・メンバー・リスト・コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Admin extends Users
{
    /**
     * ページングターゲット
     *
     * @var Query
     */
    private $countSql;

    /**
     * 全記事数
     *
     * @var integer
     */
    private $total;

    /**
     * 現在のページ
     *
     * @var integer
     */
    private $currentPage;

    /**
     * 実行可能関数
     *
     * @throws Db\Exception
     */
    public function execute()
    {
        $this->parameter->setDefault('pageSize=20');
        $select = $this->select();
        $this->currentPage = $this->request->get('page', 1);

        /** フィルタータイトル */
        if (null != ($keywords = $this->request->keywords)) {
            $select->where(
                'name LIKE ? OR screenName LIKE ?',
                '%' . Common::filterSearchQuery($keywords) . '%',
                '%' . Common::filterSearchQuery($keywords) . '%'
            );
        }

        $this->countSql = clone $select;

        $select->order('table.users.uid', Db::SORT_ASC)
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
            !isset($this->total) ? $this->total = $this->size($this->countSql) : $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );
        $nav->render('&laquo;', '&raquo;');
    }

    /**
     * ドメイン名とパスのみを出力
     *
     * @return string
     */
    protected function ___domainPath(): string
    {
        $parts = parse_url($this->url);
        return $parts['host'] . ($parts['path'] ?? null);
    }

    /**
     * 掲載記事数
     *
     * @return integer
     * @throws Db\Exception
     */
    protected function ___postsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.authorId = ?', $this->uid))->num;
    }
}
