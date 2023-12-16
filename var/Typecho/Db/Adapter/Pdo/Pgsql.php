<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db;
use Typecho\Db\Adapter\SQLException;
use Typecho\Db\Adapter\Pdo;
use Typecho\Db\Adapter\PgsqlTrait;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースPdo_Pgsqlアダプタ
 *
 * @package Db
 */
class Pgsql extends Pdo
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
        return parent::isAvailable() && in_array('pgsql', \PDO::getAvailableDrivers());
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
        $this->prepareQuery($query, $handle, $action, $table);
        return parent::query($query, $handle, $op, $action, $table);
    }

    /**
     * 初始化総合データベース
     *
     * @param Config $config 総合データベース配置
     * @return \PDO
     */
    public function init(Config $config): \PDO
    {
        $pdo = new \PDO(
            "pgsql:dbname={$config->database};host={$config->host};port={$config->port}",
            $config->user,
            $config->password
        );

        if ($config->charset) {
            $pdo->exec("SET NAMES '{$config->charset}'");
        }

        return $pdo;
    }
}
