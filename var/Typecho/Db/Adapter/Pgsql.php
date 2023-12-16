<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースPgsqlアダプタ
 *
 * @package Db
 */
class Pgsql implements Adapter
{
    use PgsqlTrait;

    /**
     * 判断アダプタ是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('pgsql');
    }

    /**
     * 総合データベース连接函数
     *
     * @param Config $config 総合データベース配置
     * @return resource
     * @throws ConnectionException
     */
    public function connect(Config $config)
    {
        $dsn = "host={$config->host} port={$config->port}"
            . " dbname={$config->database} user={$config->user} password={$config->password}";

        if ($config->charset) {
            $dsn .= " options='--client_encoding={$config->charset}'";
        }

        if ($dbLink = @pg_connect($dsn)) {
            return $dbLink;
        }

        /** 総合データベース异常 */
        throw new ConnectionException("Couldn't connect to database.");
    }

    /**
     * 获取総合データベース版本
     *
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string
    {
        $version = pg_version($handle);
        return $version['server'];
    }

    /**
     * 执行総合データベース查询
     *
     * @param string $query 総合データベース查询SQLストリング
     * @param resource $handle 接続オブジェクト
     * @param integer $op 総合データベース读写状态
     * @param string|null $action 総合データベース动作
     * @param string|null $table データシート
     * @return resource
     * @throws SQLException
     */
    public function query(string $query, $handle, int $op = Db::READ, ?string $action = null, ?string $table = null)
    {
        $this->prepareQuery($query, $handle, $action, $table);
        if ($resource = pg_query($handle, $query)) {
            return $resource;
        }

        /** 総合データベース异常 */
        throw new SQLException(
            @pg_last_error($handle),
            pg_result_error_field(pg_get_result($handle), PGSQL_DIAG_SQLSTATE)
        );
    }

    /**
     * データクエリの行の1つを配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param resource $resource このクエリーは、リソース識別子
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        return pg_fetch_assoc($resource) ?: null;
    }

    /**
     * データクエリの行の1つをオブジェクトとして削除する。,ここで、フィールド名はオブジェクトのプロパティに対応しています。
     *
     * @param resource $resource 照会されるリソース・データ
     * @return object|null
     */
    public function fetchObject($resource): ?object
    {
        return pg_fetch_object($resource) ?: null;
    }

    /**
     * @param resource $resource
     * @return array|null
     */
    public function fetchAll($resource): array
    {
        return pg_fetch_all($resource, PGSQL_ASSOC) ?: [];
    }

    /**
     * 最後のクエリによって影響を受けた行数を取り出す。
     *
     * @param resource $resource 照会されるリソース・データ
     * @param resource $handle 接続オブジェクト
     * @return integer
     */
    public function affectedRows($resource, $handle): int
    {
        return pg_affected_rows($resource);
    }

    /**
     * 引用符エスケープ関数
     *
     * @param mixed $string 需要转义的ストリング
     * @return string
     */
    public function quoteValue($string): string
    {
        return '\'' . pg_escape_string($string) . '\'';
    }
}
