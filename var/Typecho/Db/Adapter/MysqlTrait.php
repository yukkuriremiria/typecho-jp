<?php

namespace Typecho\Db\Adapter;

trait MysqlTrait
{
    use QueryTrait;

    /**
     * 空のデータテーブル
     *
     * @param string $table
     * @param mixed $handle 接続オブジェクト
     * @throws SQLException
     */
    public function truncate(string $table, $handle)
    {
        $this->query('TRUNCATE TABLE ' . $this->quoteColumn($table), $handle);
    }

    /**
     * 合成クエリー文
     *
     * @access public
     * @param array $sql クエリオブジェクト・レキシカル配列
     * @return string
     */
    public function parseSelect(array $sql): string
    {
        return $this->buildQuery($sql);
    }

    /**
     * @return string
     */
    public function getDriver(): string
    {
        return 'mysql';
    }
}
