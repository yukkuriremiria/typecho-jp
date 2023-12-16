<?php

namespace Typecho\Db;

use Typecho\Config;
use Typecho\Db;

/**
 * Typechoデータベースアダプター
 * 共通のデータベース適応インターフェースを定義する
 *
 * @package Db
 */
interface Adapter
{
    /**
     * アダプターが利用可能かどうかを判断する
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable(): bool;

    /**
     * データベース接続関数
     *
     * @param Config $config データベース構成
     * @return mixed
     */
    public function connect(Config $config);

    /**
     * データベースバージョンの取得
     *
     * @param mixed $handle
     * @return string
     */
    public function getVersion($handle): string;

    /**
     * データベースタイプの取得
     *
     * @return string
     */
    public function getDriver(): string;

    /**
     * 空のデータテーブル
     *
     * @param string $table データテーブル名
     * @param mixed $handle 接続オブジェクト
     */
    public function truncate(string $table, $handle);

    /**
     * データベースクエリの実行
     *
     * @param string $query データベースクエリSQLストリング
     * @param mixed $handle 接続オブジェクト
     * @param integer $op データベースの読み書き状況
     * @param string|null $action データベースアクション
     * @param string|null $table データシート
     * @return resource
     */
    public function query(string $query, $handle, int $op = Db::READ, ?string $action = null, ?string $table = null);

    /**
     * データクエリの行の1つを配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param resource $resource 照会されるリソース・データ
     * @return array|null
     */
    public function fetch($resource): ?array;

    /**
     * データクエリの結果をすべて配列として取り出す。,ここで、フィールド名は配列のキーに対応する
     *
     * @param resource $resource 照会されるリソース・データ
     * @return array
     */
    public function fetchAll($resource): array;

    /**
     * データクエリの行の1つをオブジェクトとして削除する。,ここで、フィールド名はオブジェクトのプロパティに対応しています。
     *
     * @param resource $resource 照会されるリソース・データ
     * @return object|null
     */
    public function fetchObject($resource): ?object;

    /**
     * 引用符エスケープ関数
     *
     * @param mixed $string 需要转义的ストリング
     * @return string
     */
    public function quoteValue($string): string;

    /**
     * オブジェクト引用フィルタリング
     *
     * @access public
     * @param string $string
     * @return string
     */
    public function quoteColumn(string $string): string;

    /**
     * 合成クエリー文
     *
     * @access public
     * @param array $sql クエリオブジェクト・レキシカル配列
     * @return string
     */
    public function parseSelect(array $sql): string;

    /**
     * 最後のクエリによって影響を受けた行数を取り出す。
     *
     * @param resource $resource 照会されるリソース・データ
     * @param mixed $handle 接続オブジェクト
     * @return integer
     */
    public function affectedRows($resource, $handle): int;

    /**
     * 最後の挿入によって返された主キーの値を取る
     *
     * @param resource $resource 照会されるリソース・データ
     * @param mixed $handle 接続オブジェクト
     * @return integer
     */
    public function lastInsertId($resource, $handle): int;
}
