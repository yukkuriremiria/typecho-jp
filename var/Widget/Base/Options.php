<?php

namespace Widget\Base;

use Typecho\Db\Exception;
use Typecho\Db\Query;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * グローバル・オプション・コンポーネント
 *
 * @link typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Options extends Base implements QueryInterface
{
    /**
     * 元のクエリーオブジェクトの取得
     *
     * @access public
     * @return Query
     * @throws Exception
     */
    public function select(): Query
    {
        return $this->db->select()->from('table.options');
    }

    /**
     * レコードの挿入
     *
     * @param array $rows レコード挿入値
     * @return integer
     * @throws Exception
     */
    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.options')->rows($rows));
    }

    /**
     * レコードの更新
     *
     * @param array $rows 更新された値を記録する
     * @param Query $condition 更新条件
     * @return integer
     * @throws Exception
     */
    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.options')->rows($rows));
    }

    /**
     * 記録の削除
     *
     * @param Query $condition 条件の削除
     * @return integer
     * @throws Exception
     */
    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.options'));
    }

    /**
     * レコード総数の取得
     *
     * @param Query $condition 計算条件
     * @return integer
     * @throws Exception
     */
    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(name)' => 'num'])->from('table.options'))->num;
    }

    /**
     * にはcheckboxオプションは値が有効かどうかを決定する
     *
     * @param mixed $settings オプションコレクション
     * @param string $name オプション名
     * @return integer
     */
    protected function isEnableByCheckbox($settings, string $name): int
    {
        return is_array($settings) && in_array($name, $settings) ? 1 : 0;
    }
}
