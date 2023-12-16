<?php

namespace Typecho\Db\Adapter;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースPDOMysqlアダプタ
 *
 * @package Db
 */
abstract class Pdo implements Adapter
{
    /**
     * 総合データベース对象
     *
     * @access protected
     * @var \PDO
     */
    protected $object;

    /**
     * 最終稼働データ表
     *
     * @access protected
     * @var string
     */
    protected $lastTable;

    /**
     * 判断アダプタ是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return class_exists('PDO');
    }

    /**
     * 総合データベース连接函数
     *
     * @param Config $config 総合データベース配置
     * @return \PDO
     * @throws ConnectionException
     */
    public function connect(Config $config): \PDO
    {
        try {
            $this->object = $this->init($config);
            $this->object->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $this->object;
        } catch (\PDOException $e) {
            /** 総合データベース异常 */
            throw new ConnectionException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 初始化総合データベース
     *
     * @param Config $config 総合データベース配置
     * @abstract
     * @access public
     * @return \PDO
     */
    abstract public function init(Config $config): \PDO;

    /**
     * 获取総合データベース版本
     *
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string
    {
        return $handle->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * 执行総合データベース查询
     *
     * @param string $query 総合データベース查询SQLストリング
     * @param \PDO $handle 接続オブジェクト
     * @param integer $op 総合データベース读写状态
     * @param string|null $action 総合データベース动作
     * @param string|null $table データシート
     * @return \PDOStatement
     * @throws SQLException
     */
    public function query(
        string $query,
        $handle,
        int $op = Db::READ,
        ?string $action = null,
        ?string $table = null
    ): \PDOStatement {
        try {
            $this->lastTable = $table;
            $resource = $handle->prepare($query);
            $resource->execute();
        } catch (\PDOException $e) {
            /** 総合データベース异常 */
            throw new SQLException($e->getMessage(), $e->getCode());
        }

        return $resource;
    }

    /**
     * データクエリの結果をすべて配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \PDOStatement $resource 照会されるリソース・データ
     * @return array
     */
    public function fetchAll($resource): array
    {
        return $resource->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * データクエリの行の1つを配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \PDOStatement $resource このクエリーは、リソース識別子
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        return $resource->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * データクエリの行の1つをオブジェクトとして削除する。,ここで、フィールド名はオブジェクトのプロパティに対応しています。
     *
     * @param \PDOStatement $resource 照会されるリソース・データ
     * @return object|null
     */
    public function fetchObject($resource): ?object
    {
        return $resource->fetchObject() ?: null;
    }

    /**
     * 引用符エスケープ関数
     *
     * @param mixed $string 需要转义的ストリング
     * @return string
     */
    public function quoteValue($string): string
    {
        return $this->object->quote($string);
    }

    /**
     * 最後のクエリによって影響を受けた行数を取り出す。
     *
     * @param \PDOStatement $resource 照会されるリソース・データ
     * @param \PDO $handle 接続オブジェクト
     * @return integer
     */
    public function affectedRows($resource, $handle): int
    {
        return $resource->rowCount();
    }

    /**
     * 最後の挿入によって返された主キーの値を取る
     *
     * @param \PDOStatement $resource 照会されるリソース・データ
     * @param \PDO $handle 接続オブジェクト
     * @return integer
     */
    public function lastInsertId($resource, $handle): int
    {
        return $handle->lastInsertId();
    }
}
