<?php

namespace Widget\Metas\Tag;

use Typecho\Db;
use Typecho\Widget\Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * タグクラウド・コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Admin extends Cloud
{
    /**
     * エントリ機能
     *
     * @throws Db\Exception
     */
    public function execute()
    {
        $select = $this->select()->where('type = ?', 'tag')->order('mid', Db::SORT_DESC);
        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * メニュータイトルの取得
     *
     * @return string|null
     * @throws Exception|Db\Exception
     */
    public function getMenuTitle(): ?string
    {
        if (isset($this->request->mid)) {
            $tag = $this->db->fetchRow($this->select()
                ->where('type = ? AND mid = ?', 'tag', $this->request->mid));

            if (!empty($tag)) {
                return _t('編集タグ %s', $tag['name']);
            }
        } else {
            return null;
        }

        throw new Exception(_t('ラベルが存在しない'), 404);
    }
}
