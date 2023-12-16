<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db\Adapter\Pdo;
use Typecho\Db\Adapter\SQLiteTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースPdo_SQLiteアダプタ
 *
 * @package Db
 */
class SQLite extends Pdo
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
        return parent::isAvailable() && in_array('sqlite', \PDO::getAvailableDrivers());
    }

    /**
     * 初始化総合データベース
     *
     * @param Config $config 総合データベース配置
     * @access public
     * @return \PDO
     */
    public function init(Config $config): \PDO
    {
        $pdo = new \PDO("sqlite:{$config->file}");
        $this->isSQLite2 = version_compare($pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '3.0.0', '<');
        return $pdo;
    }

    /**
     * データクエリの行の1つをオブジェクトとして削除する。,ここで、フィールド名はオブジェクトのプロパティに対応しています。
     *
     * @param \PDOStatement $resource 照会されるリソース・データ
     * @return object|null
     */
    public function fetchObject($resource): ?object
    {
        $result = $this->fetch($resource);
        return $result ? (object) $result : null;
    }

    /**
     * データクエリの行の一つを配列として削除する。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \PDOStatement $resource このクエリーは、リソース識別子
     * @return array|null
     */
    public function fetch($resource): ?array
    {
        $result = parent::fetch($resource);
        return $result ? $this->filterColumnName($result) : null;
    }

    /**
     * データクエリの結果をすべて配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param \PDOStatement $resource 照会されるリソース・データ
     * @return array
     */
    public function fetchAll($resource): array
    {
        return array_map([$this, 'filterColumnName'], parent::fetchAll($resource));
    }
}
