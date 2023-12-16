<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースSQLiteアダプタ
 *
 * @package Db
 */
class SQLite implements Adapter
{
    use SQLiteTrait;

    /**
     * 判断アダプタ是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('sqlite3');
    }

    /**
     * 総合データベース连接函数
     *
     * @param Config $config 総合データベース配置
     * @return \SQLite3
     * @throws ConnectionException
     */
    public function connect(Config $config): \SQLite3
    {
        try {
            $dbHandle = new \SQLite3($config->file);
            $this->isSQLite2 = version_compare(\SQLite3::version()['versionString'], '3.0.0', '<');
        } catch (\Exception $e) {
            throw new ConnectionException($e->getMessage(), $e->getCode());
        }

        return $dbHandle;
    }

    /**
     * 获取総合データベース版本
     *
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string
    {
        return \SQLite3::version()['versionString'];
    }

    /**
     * 执行総合データベース查询
     *
     * @param string $query 総合データベース查询SQLストリング
     * @param \SQLite3 $handle 接続オブジェクト
     * @param integer $op 総合データベース读写状态
     * @param string|null $action 総合データベース动作
     * @param string|null $table データシート
     * @return \SQLite3Result
     * @throws SQLException
     */
    public function query(
        string $query,
        $handle,
        int $op = Db::READ,
        ?string $action = null,
        ?string $table = null
    ): \SQLite3Result {
        if ($stm = $handle->prepare($query)) {
            if ($resource = $stm->execute()) {
                return $resource;
            }
        }

        /** 総合データベース异常 */
        throw new SQLException($handle->lastErrorMsg(), $handle->lastErrorCode());
    }

    /**
     * データクエリの行の1つをオブジェクトとして削除する。,ここで、フィールド名はオブジェクトのプロパティに対応しています。
     *
     * @param \SQLite3Result $resource 照会されるリソース・データ
     * @return object|null
     */
    public function fetchObject($resource): ?object
    {
        $result = $this->fetch($resource);
        return $result ? (object) $result : null;
    }

    /**
     * データクエリの行の1つを配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \SQLite3Result $resource このクエリーは、リソース識別子
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        $result = $resource->fetchArray(SQLITE3_ASSOC);
        return $result ? $this->filterColumnName($result) : null;
    }

    /**
     * データクエリの結果をすべて配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \SQLite3Result $resource 照会されるリソース・データ
     * @return array
     */
    public function fetchAll($resource): array
    {
        $result = [];

        while ($row = $this->fetch($resource)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * 引用符エスケープ関数
     *
     * @param mixed $string 需要转义的ストリング
     * @return string
     */
    public function quoteValue($string): string
    {
        return '\'' . str_replace('\'', '\'\'', $string) . '\'';
    }

    /**
     * 最後のクエリによって影響を受けた行数を取り出す。
     *
     * @param \SQLite3Result $resource 照会されるリソース・データ
     * @param \SQLite3 $handle 接続オブジェクト
     * @return integer
     */
    public function affectedRows($resource, $handle): int
    {
        return $handle->changes();
    }

    /**
     * 最後の挿入によって返された主キーの値を取る
     *
     * @param \SQLite3Result $resource 照会されるリソース・データ
     * @param \SQLite3 $handle 接続オブジェクト
     * @return integer
     */
    public function lastInsertId($resource, $handle): int
    {
        return $handle->lastInsertRowID();
    }
}
