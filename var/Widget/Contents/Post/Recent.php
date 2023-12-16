<?php

namespace Widget\Contents\Post;

use Typecho\Db;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 最新レビュー コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Recent extends Contents
{
    /**
     * 実行可能関数
     *
     * @throws Db\Exception
     */
    public function execute()
    {
        $this->parameter->setDefault(['pageSize' => $this->options->postsListSize]);

        $this->db->fetchAll($this->select()
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time)
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Db::SORT_DESC)
            ->limit($this->parameter->pageSize), [$this, 'push']);
    }
}
