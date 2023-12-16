<?php

namespace Typecho\Db\Adapter\Pdo;

use Typecho\Config;
use Typecho\Db\Adapter\MysqlTrait;
use Typecho\Db\Adapter\Pdo;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 総合データベースPdo_Mysqlアダプタ
 *
 * @package Db
 */
class Mysql extends Pdo
{
    use MysqlTrait;

    /**
     * 判断アダプタ是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool
    {
        return parent::isAvailable() && in_array('mysql', \PDO::getAvailableDrivers());
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
     * 初始化総合データベース
     *
     * @param Config $config 総合データベース配置
     * @access public
     * @return \PDO
     */
    public function init(Config $config): \PDO
    {
        $options = [];
        if (!empty($config->sslCa)) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = $config->sslCa;

            if (isset($config->sslVerify)) {
                // FIXME: https://github.com/php/php-src/issues/8577
                $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $config->sslVerify;
            }
        }

        $pdo = new \PDO(
            !empty($config->dsn)
                ? $config->dsn : "mysql:dbname={$config->database};host={$config->host};port={$config->port}",
            $config->user,
            $config->password,
            $options
        );
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($config->charset) {
            $pdo->exec("SET NAMES '{$config->charset}'");
        }

        return $pdo;
    }

    /**
     * 引用符エスケープ関数
     *
     * @param mixed $string エスケープされる文字列
     * @return string
     */
    public function quoteValue($string): string
    {
        return '\'' . str_replace(['\'', '\\'], ['\'\'', '\\\\'], $string) . '\'';
    }
}
