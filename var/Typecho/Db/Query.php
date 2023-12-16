<?php

namespace Typecho\Db;

use Typecho\Db;

/**
 * Typechoデータベース・クエリ・ステートメント構築クラス
 * 使用方法:
 * $query = new Query();    //またはDB累積sqlメソッドはインスタンス化されたオブジェクトを返します。
 * $query->select('posts', 'post_id, post_title')
 * ->where('post_id = %d', 1)
 * ->limit(1);
 * echo $query;
 * 印刷結果は次のようになる。
 * SELECT post_id, post_title FROM posts WHERE 1=1 AND post_id = 1 LIMIT 1
 *
 *
 * @package Db
 */
class Query
{
    /** データベースキーワード */
    private const KEYWORDS = '*PRIMARY|AND|OR|LIKE|ILIKE|BINARY|BY|DISTINCT|AS|IN|IS|NULL';

    /**
     * デフォルトフィールド
     *
     * @var array
     * @access private
     */
    private static $default = [
        'action' => null,
        'table'  => null,
        'fields' => '*',
        'join'   => [],
        'where'  => null,
        'limit'  => null,
        'offset' => null,
        'order'  => null,
        'group'  => null,
        'having' => null,
        'rows'   => [],
    ];

    /**
     * データベースアダプター
     *
     * @var Adapter
     */
    private $adapter;

    /**
     * クエリ文の事前構造化,配列,便利な組み合わせSQLクエリー文字列
     *
     * @var array
     */
    private $sqlPreBuild;

    /**
     * 接頭辞
     *
     * @access private
     * @var string
     */
    private $prefix;

    /**
     * @var array
     */
    private $params = [];

    /**
     * コンストラクタ,引用データベースアダプター作为内部数据
     *
     * @param Adapter $adapter データベースアダプター
     * @param string $prefix 接頭辞
     */
    public function __construct(Adapter $adapter, string $prefix)
    {
        $this->adapter = &$adapter;
        $this->prefix = $prefix;

        $this->sqlPreBuild = self::$default;
    }

    /**
     * set default params
     *
     * @param array $default
     */
    public static function setDefault(array $default)
    {
        self::$default = array_merge(self::$default, $default);
    }

    /**
     * パラメータの取得
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * クエリー文字列の属性値を取得する
     *
     * @access public
     * @param string $attributeName プロパティ名
     * @return string
     */
    public function getAttribute(string $attributeName): ?string
    {
        return $this->sqlPreBuild[$attributeName] ?? null;
    }

    /**
     * クエリ文字列属性値のクリア
     *
     * @access public
     * @param string $attributeName プロパティ名
     * @return Query
     */
    public function cleanAttribute(string $attributeName): Query
    {
        if (isset($this->sqlPreBuild[$attributeName])) {
            $this->sqlPreBuild[$attributeName] = self::$default[$attributeName];
        }
        return $this;
    }

    /**
     * 接続方式
     *
     * @param string $table 結合するテーブル
     * @param string $condition 接続条件
     * @param string $op 接続方法(LEFT, RIGHT, INNER)
     * @return Query
     */
    public function join(string $table, string $condition, string $op = Db::INNER_JOIN): Query
    {
        $this->sqlPreBuild['join'][] = [$this->filterPrefix($table), $this->filterColumn($condition), $op];
        return $this;
    }

    /**
     * 过滤表接頭辞,表接頭辞由table.構成する
     *
     * @param string $string パースされる文字列
     * @return string
     */
    private function filterPrefix(string $string): string
    {
        return (0 === strpos($string, 'table.')) ? substr_replace($string, $this->prefix, 0, 6) : $string;
    }

    /**
     * 配列キーのフィルタリング
     *
     * @access private
     * @param string $str 保留中のフィールド値
     * @return string
     */
    private function filterColumn(string $str): string
    {
        $str = $str . ' 0';
        $length = strlen($str);
        $lastIsAlnum = false;
        $result = '';
        $word = '';
        $split = '';
        $quotes = 0;

        for ($i = 0; $i < $length; $i++) {
            $cha = $str[$i];

            if (ctype_alnum($cha) || false !== strpos('_*', $cha)) {
                if (!$lastIsAlnum) {
                    if (
                        $quotes > 0 && !ctype_digit($word) && '.' != $split
                        && false === strpos(self::KEYWORDS, strtoupper($word))
                    ) {
                        $word = $this->adapter->quoteColumn($word);
                    } elseif ('.' == $split && 'table' == $word) {
                        $word = $this->prefix;
                        $split = '';
                    }

                    $result .= $word . $split;
                    $word = '';
                    $quotes = 0;
                }

                $word .= $cha;
                $lastIsAlnum = true;
            } else {
                if ($lastIsAlnum) {
                    if (0 == $quotes) {
                        if (false !== strpos(' ,)=<>.+-*/', $cha)) {
                            $quotes = 1;
                        } elseif ('(' == $cha) {
                            $quotes = - 1;
                        }
                    }

                    $split = '';
                }

                $split .= $cha;
                $lastIsAlnum = false;
            }

        }

        return $result;
    }

    /**
     * AND条件付きクエリ文
     *
     * @param ...$args
     * @return $this
     */
    public function where(...$args): Query
    {
        [$condition] = $args;
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['where']) ? ' WHERE ' : ' AND';

        if (count($args) <= 1) {
            $this->sqlPreBuild['where'] .= $operator . ' (' . $condition . ')';
        } else {
            array_shift($args);
            $this->sqlPreBuild['where'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    /**
     * エスケープパラメータ
     *
     * @param array $values
     * @access protected
     * @return array
     */
    protected function quoteValues(array $values): array
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = '(' . implode(',', array_map([$this, 'quoteValue'], $value)) . ')';
            } else {
                $value = $this->quoteValue($value);
            }
        }

        return $values;
    }

    /**
     * 遅延エスケープ
     *
     * @param $value
     * @return string
     */
    public function quoteValue($value): string
    {
        $this->params[] = $value;
        return '#param:' . (count($this->params) - 1) . '#';
    }

    /**
     * OR条件付きクエリ文
     *
     * @param ...$args
     * @return Query
     */
    public function orWhere(...$args): Query
    {
        [$condition] = $args;
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['where']) ? ' WHERE ' : ' OR';

        if (func_num_args() <= 1) {
            $this->sqlPreBuild['where'] .= $operator . ' (' . $condition . ')';
        } else {
            array_shift($args);
            $this->sqlPreBuild['where'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    /**
     * クエリの行数制限
     *
     * @param mixed $limit クエリーする行数
     * @return Query
     */
    public function limit($limit): Query
    {
        $this->sqlPreBuild['limit'] = intval($limit);
        return $this;
    }

    /**
     * クエリ行オフセット
     *
     * @param mixed $offset オフセットされる行数
     * @return Query
     */
    public function offset($offset): Query
    {
        $this->sqlPreBuild['offset'] = intval($offset);
        return $this;
    }

    /**
     * ページング検索
     *
     * @param mixed $page ページネーション
     * @param mixed $pageSize ページあたりの行数
     * @return Query
     */
    public function page($page, $pageSize): Query
    {
        $pageSize = intval($pageSize);
        $this->sqlPreBuild['limit'] = $pageSize;
        $this->sqlPreBuild['offset'] = (max(intval($page), 1) - 1) * $pageSize;
        return $this;
    }

    /**
     * 書き込む列とその値を指定する。
     *
     * @param array $rows
     * @return Query
     */
    public function rows(array $rows): Query
    {
        foreach ($rows as $key => $row) {
            $this->sqlPreBuild['rows'][$this->filterColumn($key)]
                = is_null($row) ? 'NULL' : $this->adapter->quoteValue($row);
        }
        return $this;
    }

    /**
     * 書き込む列とその値を指定する。
     * 一行で引用符をエスケープしない
     *
     * @param string $key カラム名
     * @param mixed $value 指定値
     * @param bool $escape 逃げるかどうか
     * @return Query
     */
    public function expression(string $key, $value, bool $escape = true): Query
    {
        $this->sqlPreBuild['rows'][$this->filterColumn($key)] = $escape ? $this->filterColumn($value) : $value;
        return $this;
    }

    /**
     * ソート順(ORDER BY)
     *
     * @param string $orderBy 並べ替えインデックス
     * @param string $sort 並べ替え(ASC, DESC)
     * @return Query
     */
    public function order(string $orderBy, string $sort = Db::SORT_ASC): Query
    {
        if (empty($this->sqlPreBuild['order'])) {
            $this->sqlPreBuild['order'] = ' ORDER BY ';
        } else {
            $this->sqlPreBuild['order'] .= ', ';
        }

        $this->sqlPreBuild['order'] .= $this->filterColumn($orderBy) . (empty($sort) ? null : ' ' . $sort);
        return $this;
    }

    /**
     * 集合体(GROUP BY)
     *
     * @param string $key 集約されたキー値
     * @return Query
     */
    public function group(string $key): Query
    {
        $this->sqlPreBuild['group'] = ' GROUP BY ' . $this->filterColumn($key);
        return $this;
    }

    /**
     * @param string $condition
     * @param ...$args
     * @return $this
     */
    public function having(string $condition, ...$args): Query
    {
        $condition = str_replace('?', "%s", $this->filterColumn($condition));
        $operator = empty($this->sqlPreBuild['having']) ? ' HAVING ' : ' AND';

        if (count($args) == 0) {
            $this->sqlPreBuild['having'] .= $operator . ' (' . $condition . ')';
        } else {
            $this->sqlPreBuild['having'] .= $operator . ' (' . vsprintf($condition, $this->quoteValues($args)) . ')';
        }

        return $this;
    }

    /**
     * クエリーフィールドの選択
     *
     * @param mixed ...$args クエリフィールド
     * @return $this
     */
    public function select(...$args): Query
    {
        $this->sqlPreBuild['action'] = Db::SELECT;

        $this->sqlPreBuild['fields'] = $this->getColumnFromParameters($args);
        return $this;
    }

    /**
     * 从参数中合成クエリフィールド
     *
     * @access private
     * @param array $parameters
     * @return string
     */
    private function getColumnFromParameters(array $parameters): string
    {
        $fields = [];

        foreach ($parameters as $value) {
            if (is_array($value)) {
                foreach ($value as $key => $val) {
                    $fields[] = $key . ' AS ' . $val;
                }
            } else {
                $fields[] = $value;
            }
        }

        return $this->filterColumn(implode(' , ', $fields));
    }

    /**
     * クエリーレコード操作(SELECT)
     *
     * @param string $table 照会テーブル
     * @return Query
     */
    public function from(string $table): Query
    {
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * レコードの更新操作(UPDATE)
     *
     * @param string $table 更新されるレコードの表
     * @return Query
     */
    public function update(string $table): Query
    {
        $this->sqlPreBuild['action'] = Db::UPDATE;
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * レコード削除操作(DELETE)
     *
     * @param string $table 削除されるレコードの表
     * @return Query
     */
    public function delete(string $table): Query
    {
        $this->sqlPreBuild['action'] = Db::DELETE;
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * レコード挿入操作(INSERT)
     *
     * @param string $table 挿入されるレコードの表
     * @return Query
     */
    public function insert(string $table): Query
    {
        $this->sqlPreBuild['action'] = Db::INSERT;
        $this->sqlPreBuild['table'] = $this->filterPrefix($table);
        return $this;
    }

    /**
     * @param string $query
     * @return string
     */
    public function prepare(string $query): string
    {
        $params = $this->params;
        $adapter = $this->adapter;

        return preg_replace_callback("/#param:([0-9]+)#/", function ($matches) use ($params, $adapter) {
            if (array_key_exists($matches[1], $params)) {
                return is_null($params[$matches[1]]) ? 'NULL' : $adapter->quoteValue($params[$matches[1]]);
            } else {
                return $matches[0];
            }
        }, $query);
    }

    /**
     * 最終的なクエリー・ステートメントを作成する
     *
     * @return string
     */
    public function __toString()
    {
        switch ($this->sqlPreBuild['action']) {
            case Db::SELECT:
                return $this->adapter->parseSelect($this->sqlPreBuild);
            case Db::INSERT:
                return 'INSERT INTO '
                    . $this->sqlPreBuild['table']
                    . '(' . implode(' , ', array_keys($this->sqlPreBuild['rows'])) . ')'
                    . ' VALUES '
                    . '(' . implode(' , ', array_values($this->sqlPreBuild['rows'])) . ')'
                    . $this->sqlPreBuild['limit'];
            case Db::DELETE:
                return 'DELETE FROM '
                    . $this->sqlPreBuild['table']
                    . $this->sqlPreBuild['where'];
            case Db::UPDATE:
                $columns = [];
                if (isset($this->sqlPreBuild['rows'])) {
                    foreach ($this->sqlPreBuild['rows'] as $key => $val) {
                        $columns[] = "$key = $val";
                    }
                }

                return 'UPDATE '
                    . $this->sqlPreBuild['table']
                    . ' SET ' . implode(' , ', $columns)
                    . $this->sqlPreBuild['where'];
            default:
                return null;
        }
    }
}
