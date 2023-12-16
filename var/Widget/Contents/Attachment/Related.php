<?php

namespace Widget\Contents\Attachment;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 記事 関連文書 コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Related extends Contents
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
        $this->parameter->setDefault('parentId=0&limit=0');

        //そうでなければcid(価値がある
        if (!$this->parameter->parentId) {
            return;
        }

        /** 基本的なクエリの構築 */
        $select = $this->select()->where('table.contents.type = ?', 'attachment');

        //orderファイル内のフィールドは、それらが属する記事を表す。
        $select->where('table.contents.parent = ?', $this->parameter->parentId);

        /** お問い合わせ */
        $select->order('table.contents.created', Db::SORT_ASC);

        if ($this->parameter->limit > 0) {
            $select->limit($this->parameter->limit);
        }

        if ($this->parameter->offset > 0) {
            $select->offset($this->parameter->offset);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }
}
