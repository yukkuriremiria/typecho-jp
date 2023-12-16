<?php

namespace Widget\Base;

use Typecho\Db\Query;

/**
 * Base Query Interface
 */
interface QueryInterface
{
    /**
     * 問い合わせ方法
     *
     * @return Query
     */
    public function select(): Query;

    /**
     * すべてのレコードを取得する
     *
     * @access public
     * @param Query $condition クエリ件名
     * @return integer
     */
    public function size(Query $condition): int;

    /**
     * 記録方法の追加
     *
     * @access public
     * @param array $rows フィールドの対応する値
     * @return integer
     */
    public function insert(array $rows): int;

    /**
     * 記録の更新方法
     *
     * @access public
     * @param array $rows フィールドの対応する値
     * @param Query $condition クエリ件名
     * @return integer
     */
    public function update(array $rows, Query $condition): int;

    /**
     * レコードの削除方法
     *
     * @access public
     * @param Query $condition クエリ件名
     * @return integer
     */
    public function delete(Query $condition): int;
}
