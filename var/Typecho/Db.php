<?php

namespace Typecho;

use Typecho\Db\Adapter;
use Typecho\Db\Query;
use Typecho\Db\Exception as DbException;

/**
 * データ・サポートを取得するメソッドを含むクラス.
 * 定義されなければならない__TYPECHO_DB_HOST__, __TYPECHO_DB_PORT__, __TYPECHO_DB_NAME__,
 * __TYPECHO_DB_USER__, __TYPECHO_DB_PASS__, __TYPECHO_DB_CHAR__
 *
 * @package Db
 */
class Db
{
    /** データベースを読む */
    public const READ = 1;

    /** データベースへの書き込み */
    public const WRITE = 2;

    /** 昇順 */
    public const SORT_ASC = 'ASC';

    /** 降順 */
    public const SORT_DESC = 'DESC';

    /** テーブル接続方法 */
    public const INNER_JOIN = 'INNER';

    /** テーブル外接続方式 */
    public const OUTER_JOIN = 'OUTER';

    /** 表 左 接続方法 */
    public const LEFT_JOIN = 'LEFT';

    /** テーブル右接続方式 */
    public const RIGHT_JOIN = 'RIGHT';

    /** データベースのクエリー操作 */
    public const SELECT = 'SELECT';

    /** データベースの更新操作 */
    public const UPDATE = 'UPDATE';

    /** データベースの挿入操作 */
    public const INSERT = 'INSERT';

    /** データベースの削除操作 */
    public const DELETE = 'DELETE';

    /**
     * データベースアダプター
     * @var Adapter
     */
    private $adapter;

    /**
     * デフォルト設定
     *
     * @var array
     */
    private $config;

    /**
     * 接続済み
     *
     * @access private
     * @var array
     */
    private $connectedPool;

    /**
     * 接頭辞
     *
     * @access private
     * @var string
     */
    private $prefix;

    /**
     * アダプター名
     *
     * @access private
     * @var string
     */
    private $adapterName;

    /**
     * インスタンス化されたデータベース・オブジェクト
     * @var Db
     */
    private static $instance;

    /**
     * データベース・クラス・コンストラクタ
     *
     * @param mixed $adapterName アダプター名
     * @param string $prefix 接頭辞
     *
     * @throws DbException
     */
    public function __construct($adapterName, string $prefix = 'typecho_')
    {
        /** ゲインアダプター名 */
        $adapterName = $adapterName == 'Mysql' ? 'Mysqli' : $adapterName;
        $this->adapterName = $adapterName;

        /** データベースアダプター */
        $adapterName = '\Typecho\Db\Adapter\\' . str_replace('_', '\\', $adapterName);

        if (!call_user_func([$adapterName, 'isAvailable'])) {
            throw new DbException("Adapter {$adapterName} is not available");
        }

        $this->prefix = $prefix;

        /** 内部変数の初期化 */
        $this->connectedPool = [];

        $this->config = [
            self::READ => [],
            self::WRITE => []
        ];

        //アダプタ・オブジェクトのインスタンス化
        $this->adapter = new $adapterName();
    }

    /**
     * @return Adapter
     */
    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    /**
     * ゲインアダプター名
     *
     * @access public
     * @return string
     */
    public function getAdapterName(): string
    {
        return $this->adapterName;
    }

    /**
     * ゲイン表接頭辞
     *
     * @access public
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param Config $config
     * @param int $op
     */
    public function addConfig(Config $config, int $op)
    {
        if ($op & self::READ) {
            $this->config[self::READ][] = $config;
        }

        if ($op & self::WRITE) {
            $this->config[self::WRITE][] = $config;
        }
    }

    /**
     * getConfig
     *
     * @param int $op
     *
     * @return Config
     * @throws DbException
     */
    public function getConfig(int $op): Config
    {
        if (empty($this->config[$op])) {
            /** DbException */
            throw new DbException('Missing Database Connection');
        }

        $key = array_rand($this->config[$op]);
        return $this->config[$op][$key];
    }

    /**
     * 接続プールのリセット
     *
     * @return void
     */
    public function flushPool()
    {
        $this->connectedPool = [];
    }

    /**
     * データベースの選択
     *
     * @param int $op
     *
     * @return mixed
     * @throws DbException
     */
    public function selectDb(int $op)
    {
        if (!isset($this->connectedPool[$op])) {
            $selectConnectionConfig = $this->getConfig($op);
            $selectConnectionHandle = $this->adapter->connect($selectConnectionConfig);
            $this->connectedPool[$op] = $selectConnectionHandle;
        }

        return $this->connectedPool[$op];
    }

    /**
     * ゲインSQLレキシカル・ビルダーはオブジェクトをインスタンス化する
     *
     * @return Query
     */
    public function sql(): Query
    {
        return new Query($this->adapter, $this->prefix);
    }

    /**
     * 複数のデータベースをサポートする
     *
     * @access public
     * @param array $config データベースの例
     * @param integer $op データベース操作
     * @return void
     */
    public function addServer(array $config, int $op)
    {
        $this->addConfig(Config::factory($config), $op);
        $this->flushPool();
    }

    /**
     * ゲイン版本
     *
     * @param int $op
     *
     * @return string
     * @throws DbException
     */
    public function getVersion(int $op = self::READ): string
    {
        return $this->adapter->getVersion($this->selectDb($op));
    }

    /**
     * デフォルトのデータベース・オブジェクトの設定
     *
     * @access public
     * @param Db $db データベースオブジェクト
     * @return void
     */
    public static function set(Db $db)
    {
        self::$instance = $db;
    }

    /**
     * ゲインデータベースの例化对象
     * 用静态变量存储インスタンス化されたデータベース・オブジェクト,データ接続は一度しか行われないことが保証される。
     *
     * @return Db
     * @throws DbException
     */
    public static function get(): Db
    {
        if (empty(self::$instance)) {
            /** DbException */
            throw new DbException('Missing Database Object');
        }

        return self::$instance;
    }

    /**
     * クエリーフィールドの選択
     *
     * @param ...$ags
     *
     * @return Query
     * @throws DbException
     */
    public function select(...$ags): Query
    {
        $this->selectDb(self::READ);

        $args = func_get_args();
        return call_user_func_array([$this->sql(), 'select'], $args ?: ['*']);
    }

    /**
     * レコードの更新操作(UPDATE)
     *
     * @param string $table 更新されるレコードの表
     *
     * @return Query
     * @throws DbException
     */
    public function update(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->update($table);
    }

    /**
     * レコード削除操作(DELETE)
     *
     * @param string $table 削除されるレコードの表
     *
     * @return Query
     * @throws DbException
     */
    public function delete(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->delete($table);
    }

    /**
     * レコード挿入操作(INSERT)
     *
     * @param string $table 挿入されるレコードの表
     *
     * @return Query
     * @throws DbException
     */
    public function insert(string $table): Query
    {
        $this->selectDb(self::WRITE);

        return $this->sql()->insert($table);
    }

    /**
     * @param $table
     * @throws DbException
     */
    public function truncate($table)
    {
        $table = preg_replace("/^table\./", $this->prefix, $table);
        $this->adapter->truncate($table, $this->selectDb(self::WRITE));
    }

    /**
     * クエリーステートメントを実行する
     *
     * @param mixed $query クエリ・ステートメントまたはクエリ・オブジェクト
     * @param int $op データベースの読み書き状況
     * @param string $action 動作
     *
     * @return mixed
     * @throws DbException
     */
    public function query($query, int $op = self::READ, string $action = self::SELECT)
    {
        $table = null;

        /** アダプタでのクエリ実行 */
        if ($query instanceof Query) {
            $action = $query->getAttribute('action');
            $table = $query->getAttribute('table');
            $op = (self::UPDATE == $action || self::DELETE == $action
                || self::INSERT == $action) ? self::WRITE : self::READ;
        } elseif (!is_string($query)) {
            /** 万が一queryオブジェクトでも文字列でもない,そして、クエリーリソースハンドルとして判断する。,ダイレクト・リターン */
            return $query;
        }

        /** 接続プールの選択 */
        $handle = $this->selectDb($op);

        /** お問い合わせ */
        $resource = $this->adapter->query($query instanceof Query ?
            $query->prepare($query) : $query, $handle, $op, $action, $table);

        if ($action) {
            //クエリーアクションに基づき、適切なリソースを返します。
            switch ($action) {
                case self::UPDATE:
                case self::DELETE:
                    return $this->adapter->affectedRows($resource, $handle);
                case self::INSERT:
                    return $this->adapter->lastInsertId($resource, $handle);
                case self::SELECT:
                default:
                    return $resource;
            }
        } else {
            //万が一直接クエリーステートメントを実行する则返回资源
            return $resource;
        }
    }

    /**
     * すべての行を一度に削除する
     *
     * @param mixed $query クエリ件名
     * @param callable|null $filter 行フィルター機能,クエリの各行を最初のパラメータとして、指定したフィルタに渡す。
     *
     * @return array
     * @throws DbException
     */
    public function fetchAll($query, ?callable $filter = null): array
    {
        //検索を実行する
        $resource = $this->query($query);
        $result = $this->adapter->fetchAll($resource);

        return $filter ? array_map($filter, $result) : $result;
    }

    /**
     * 一度に一行ずつ
     *
     * @param mixed $query クエリ件名
     * @param callable|null $filter 行フィルター機能,クエリの各行を最初のパラメータとして、指定したフィルタに渡す。
     * @return array|null
     * @throws DbException
     */
    public function fetchRow($query, ?callable $filter = null): ?array
    {
        $resource = $this->query($query);

        return ($rows = $this->adapter->fetch($resource)) ?
            ($filter ? call_user_func($filter, $rows) : $rows) :
            null;
    }

    /**
     * 一度に1つのオブジェクトを削除する
     *
     * @param mixed $query クエリ件名
     * @param array|null $filter 行フィルター機能,クエリの各行を最初のパラメータとして、指定したフィルタに渡す。
     * @return object|null
     * @throws DbException
     */
    public function fetchObject($query, ?array $filter = null): ?object
    {
        $resource = $this->query($query);

        return ($rows = $this->adapter->fetchObject($resource)) ?
            ($filter ? call_user_func($filter, $rows) : $rows) :
            null;
    }
}
