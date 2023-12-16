<?php

namespace Widget\Contents\Page;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 独立型ページ・リスト・コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Rows extends Contents
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
        $select = $this->select()->where('table.contents.type = ?', 'page')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time)
            ->order('table.contents.order', Db::SORT_ASC);

        //カスタムホームページの削除
        $frontPage = explode(':', $this->options->frontPage);
        if (2 == count($frontPage) && 'page' == $frontPage[0]) {
            $select->where('table.contents.cid <> ?', $frontPage[1]);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
