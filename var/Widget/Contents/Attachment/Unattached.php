<?php

namespace Widget\Contents\Attachment;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
/**
 * 関連文書なし
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 * @version $Id$
 */

/**
 * 関連文書なし组件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Unattached extends Contents
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
        /** 基本的なクエリの構築 */
        $select = $this->select()->where('table.contents.type = ? AND
        (table.contents.parent = 0 OR table.contents.parent IS NULL)', 'attachment');

        /** ユーザーへのプラス判断 */
        $this->where('table.contents.authorId = ?', $this->user->uid);

        /** お問い合わせ */
        $select->order('table.contents.created', Db::SORT_DESC);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
