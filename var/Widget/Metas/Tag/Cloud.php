<?php

namespace Widget\Metas\Tag;

use Typecho\Common;
use Typecho\Db;
use Widget\Base\Metas;

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
class Cloud extends Metas
{
    /**
     * エントリ機能
     *
     * @throws Db\Exception
     */
    public function execute()
    {
        $this->parameter->setDefault(['sort' => 'count', 'ignoreZeroCount' => false, 'desc' => true, 'limit' => 0]);
        $select = $this->select()->where('type = ?', 'tag')
            ->order($this->parameter->sort, $this->parameter->desc ? Db::SORT_DESC : Db::SORT_ASC);

        /** ゼロ量を無視する */
        if ($this->parameter->ignoreZeroCount) {
            $select->where('count > 0');
        }

        /** 制限総数 */
        if ($this->parameter->limit) {
            $select->limit($this->parameter->limit);
        }

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 分割数による文字列の出力
     *
     * @param mixed ...$args 出力される値
     */
    public function split(...$args)
    {
        array_unshift($args, $this->count);
        echo call_user_func_array([Common::class, 'splitByCount'], $args);
    }
}
