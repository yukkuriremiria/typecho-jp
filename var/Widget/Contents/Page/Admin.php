<?php

namespace Widget\Contents\Page;

use Typecho\Common;
use Typecho\Db;
use Widget\Contents\Post\Admin as PostAdmin;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 独立型ページ管理リスト・コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Admin extends PostAdmin
{
    /**
     * 実行可能関数
     *
     * @access public
     * @return void
     * @throws Db\Exception
     */
    public function execute()
    {
        /** ろ過状態 */
        $select = $this->select()->where(
            'table.contents.type = ? OR (table.contents.type = ? AND table.contents.parent = ?)',
            'page',
            'page_draft',
            0
        );

        /** フィルタータイトル */
        if (null != ($keywords = $this->request->keywords)) {
            $args = [];
            $keywordsList = explode(' ', $keywords);
            $args[] = implode(' OR ', array_fill(0, count($keywordsList), 'table.contents.title LIKE ?'));

            foreach ($keywordsList as $keyword) {
                $args[] = '%' . Common::filterSearchQuery($keyword) . '%';
            }

            call_user_func_array([$select, 'where'], $args);
        }

        /** お問い合わせ */
        $select->order('table.contents.order', Db::SORT_ASC);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
