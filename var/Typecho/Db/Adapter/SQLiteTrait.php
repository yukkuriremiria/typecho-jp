<?php

namespace Typecho\Db\Adapter;

/**
 * SQLite Special Util
 */
trait SQLiteTrait
{
    use QueryTrait;

    private $isSQLite2 = false;

    /**
     * 空のデータテーブル
     *
     * @param string $table
     * @param mixed $handle 接続オブジェクト
     * @throws SQLException
     */
    public function truncate(string $table, $handle)
    {
        $this->query('DELETE FROM ' . $this->quoteColumn($table), $handle);
    }

    /**
     * オブジェクト引用フィルタリング
     *
     * @param string $string
     * @return string
     */
    public function quoteColumn(string $string): string
    {
        return '"' . $string . '"';
    }

    /**
     * フィルター・フィールド名
     *
     * @access private
     *
     * @param array $result
     *
     * @return array
     */
    private function filterColumnName(array $result): array
    {
        /** 結果がNULLの場合,ダイレクト・リターン */
        if (empty($result)) {
            return $result;
        }

        $tResult = [];

        /** 配列を繰り返し処理する */
        foreach ($result as $key => $val) {
            /** ドット・バイ・ドット */
            if (false !== ($pos = strpos($key, '.'))) {
                $key = substr($key, $pos + 1);
            }

            $tResult[trim($key, '"')] = $val;
        }

        return $tResult;
    }

    /**
     * 扱うsqlite2なdistinct count
     *
     * @param string $sql
     *
     * @return string
     */
    private function filterCountQuery(string $sql): string
    {
        if (preg_match("/SELECT\s+COUNT\(DISTINCT\s+([^\)]+)\)\s+(AS\s+[^\s]+)?\s*FROM\s+(.+)/is", $sql, $matches)) {
            return 'SELECT COUNT(' . $matches[1] . ') ' . $matches[2] . ' FROM SELECT DISTINCT '
                . $matches[1] . ' FROM ' . $matches[3];
        }

        return $sql;
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
        $query = $this->buildQuery($sql);

        if ($this->isSQLite2) {
            $query = $this->filterCountQuery($query);
        }

        return $query;
    }

    /**
     * @return string
     */
    public function getDriver(): string
    {
        return 'sqlite';
    }
}
