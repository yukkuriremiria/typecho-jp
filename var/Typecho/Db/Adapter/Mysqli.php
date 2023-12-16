<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースMysqliアダプタ
 *
 * @package Db
 */
class Mysqli implements Adapter
{
    use MysqlTrait;

    /**
     * 総合データベース连接ストリング标示
     *
     * @access private
     * @var \mysqli
     */
    private $dbLink;

    /**
     * 判断アダプタ是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('mysqli');
    }

    /**
     * 総合データベース连接函数
     *
     * @param Config $config 総合データベース配置
     * @return \mysqli
     * @throws ConnectionException
     */
    public function connect(Config $config): \mysqli
    {
        $mysqli = mysqli_init();
        if ($mysqli) {
            if (!empty($config->sslCa)) {
                $mysqli->ssl_set(null, null, $config->sslCa, null, null);

                if (isset($config->sslVerify)) {
                    $mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $config->sslVerify);
                }
            }

            $mysqli->real_connect(
                $config->host,
                $config->user,
                $config->password,
                $config->database,
                (empty($config->port) ? null : $config->port)
            );

            $this->dbLink = $mysqli;

            if ($config->charset) {
                $this->dbLink->query("SET NAMES '{$config->charset}'");
            }

            return $this->dbLink;
        }

        /** 総合データベース异常 */
        throw new ConnectionException("Couldn't connect to database.", mysqli_connect_errno());
    }

    /**
     * 获取総合データベース版本
     *
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string
    {
        return $this->dbLink->server_version;
    }

    /**
     * 执行総合データベース查询
     *
     * @param string $query 総合データベース查询SQLストリング
     * @param mixed $handle 接続オブジェクト
     * @param integer $op 総合データベース读写状态
     * @param string|null $action 総合データベース动作
     * @param string|null $table データシート
     * @throws SQLException
     */
    public function query(
        string $query,
        $handle,
        int $op = Db::READ,
        ?string $action = null,
        ?string $table = null
    ) {
        if ($resource = @$this->dbLink->query($query)) {
            return $resource;
        }

        /** 総合データベース异常 */
        throw new SQLException($this->dbLink->error, $this->dbLink->errno);
    }

    /**
     * オブジェクト引用フィルタリング
     *
     * @access public
     * @param string $string
     * @return string
     */
    public function quoteColumn(string $string): string
    {
        return '`' . $string . '`';
    }

    /**
     * データクエリの行の一つを配列として削除する。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \mysqli_result $resource このクエリーは、リソース識別子
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        return $resource->fetch_assoc();
    }

    /**
     * データクエリの結果をすべて配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \mysqli_result $resource このクエリーは、リソース識別子
     * @return array
     */
    public function fetchAll($resource): array
    {
        return $resource->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * データクエリの行の1つをオブジェクトとして削除する。,ここで、フィールド名はオブジェクトのプロパティに対応しています。
     *
     * @param \mysqli_result $resource 照会されるリソース・データ
     * @return object|null
     */
    public function fetchObject($resource): ?object
    {
        return $resource->fetch_object();
    }

    /**
     * 引用符エスケープ関数
     *
     * @param mixed $string 需要转义的ストリング
     * @return string
     */
    public function quoteValue($string): string
    {
        return "'" . $this->dbLink->real_escape_string($string) . "'";
    }

    /**
     * 最後のクエリによって影響を受けた行数を取り出す。
     *
     * @param mixed $resource 照会されるリソース・データ
     * @param \mysqli $handle 接続オブジェクト
     * @return integer
     */
    public function affectedRows($resource, $handle): int
    {
        return $handle->affected_rows;
    }

    /**
     * 最後の挿入によって返された主キーの値を取る
     *
     * @param mixed $resource 照会されるリソース・データ
     * @param \mysqli $handle 接続オブジェクト
     * @return integer
     */
    public function lastInsertId($resource, $handle): int
    {
        return $handle->insert_id;
    }
}
