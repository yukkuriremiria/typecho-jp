<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Config;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Plugin;
use Typecho\Router;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ユーザ抽象クラス
 *
 * @property int $uid
 * @property string $name
 * @property string $password
 * @property string $mail
 * @property string $url
 * @property string $screenName
 * @property int $created
 * @property int $activated
 * @property int $logged
 * @property string $group
 * @property string $authCode
 * @property-read Config $personalOptions
 * @property-read string $permalink
 * @property-read string $feedUrl
 * @property-read string $feedRssUrl
 * @property-read string $feedAtomUrl
 */
class Users extends Base implements QueryInterface
{
    /**
     * ユーザー名が存在するかどうかを判断する
     *
     * @param string $name 利用者ID
     * @return boolean
     * @throws Exception
     */
    public function nameExists(string $name): bool
    {
        $select = $this->db->select()
            ->from('table.users')
            ->where('name = ?', $name)
            ->limit(1);

        if ($this->request->uid) {
            $select->where('uid <> ?', $this->request->uid);
        }

        $user = $this->db->fetchRow($select);
        return !$user;
    }

    /**
     * 電子メールが存在するかどうかの判断
     *
     * @param string $mail 電子メール
     * @return boolean
     * @throws Exception
     */
    public function mailExists(string $mail): bool
    {
        $select = $this->db->select()
            ->from('table.users')
            ->where('mail = ?', $mail)
            ->limit(1);

        if ($this->request->uid) {
            $select->where('uid <> ?', $this->request->uid);
        }

        $user = $this->db->fetchRow($select);
        return !$user;
    }

    /**
     * ユーザーニックネームが存在するかどうかを判断する
     *
     * @param string $screenName 愛称
     * @return boolean
     * @throws Exception
     */
    public function screenNameExists(string $screenName): bool
    {
        $select = $this->db->select()
            ->from('table.users')
            ->where('screenName = ?', $screenName)
            ->limit(1);

        if ($this->request->uid) {
            $select->where('uid <> ?', $this->request->uid);
        }

        $user = $this->db->fetchRow($select);
        return !$user;
    }

    /**
     * 各行の値をスタックに押し込む
     *
     * @param array $value 1行あたりの価値
     * @return array
     */
    public function push(array $value): array
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    /**
     * 汎用フィルター
     *
     * @param array $value フィルタリングする行データ
     * @return array
     */
    public function filter(array $value): array
    {
        //静的リンクの生成
        $routeExists = (null != Router::get('author'));

        $value['permalink'] = $routeExists ? Router::url('author', $value, $this->options->index) : '#';

        /** 集約リンクの生成 */
        /** RSS 2.0 */
        $value['feedUrl'] = $routeExists ? Router::url('author', $value, $this->options->feedUrl) : '#';

        /** RSS 1.0 */
        $value['feedRssUrl'] = $routeExists ? Router::url('author', $value, $this->options->feedRssUrl) : '#';

        /** ATOM 1.0 */
        $value['feedAtomUrl'] = $routeExists ? Router::url('author', $value, $this->options->feedAtomUrl) : '#';

        $value = Users::pluginHandle()->filter($value, $this);
        return $value;
    }

    /**
     * 問い合わせ方法
     *
     * @return Query
     * @throws Exception
     */
    public function select(): Query
    {
        return $this->db->select()->from('table.users');
    }

    /**
     * すべてのレコードを取得する
     *
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(uid)' => 'num'])->from('table.users'))->num;
    }

    /**
     * 記録方法の追加
     *
     * @param array $rows フィールドの対応する値
     * @return integer
     * @throws Exception
     */
    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.users')->rows($rows));
    }

    /**
     * 記録の更新方法
     *
     * @param array $rows フィールドの対応する値
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.users')->rows($rows));
    }

    /**
     * レコードの削除方法
     *
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.users'));
    }

    /**
     * 各論gravatarユーザーアバターのエクスポート
     *
     * @param integer $size アバターサイズ
     * @param string $rating アバター評価
     * @param string|null $default デフォルトの出力アバター
     * @param string|null $class デフォルトcss class
     */
    public function gravatar(int $size = 40, string $rating = 'X', ?string $default = null, ?string $class = null)
    {
        $url = Common::gravatarUrl($this->mail, $size, $rating, $default, $this->request->isSecure());
        echo '<img' . (empty($class) ? '' : ' class="' . $class . '"') . ' src="' . $url . '" alt="' .
            $this->screenName . '" width="' . $size . '" height="' . $size . '" />';
    }

    /**
     * personalOptions
     *
     * @return Config
     * @throws Exception
     */
    protected function ___personalOptions(): Config
    {
        $rows = $this->db->fetchAll($this->db->select()
            ->from('table.options')->where('user = ?', $this->uid));
        $options = [];
        foreach ($rows as $row) {
            $options[$row['name']] = $row['value'];
        }

        return new Config($options);
    }

    /**
     * ページオフセット取得
     *
     * @param string $column フィールド名
     * @param integer $offset オフセット値
     * @param string|null $group ユーザーグループ
     * @param integer $pageSize ページネーション値
     * @return integer
     * @throws Exception
     */
    protected function getPageOffset(string $column, int $offset, ?string $group = null, int $pageSize = 20): int
    {
        $select = $this->db->select(['COUNT(uid)' => 'num'])->from('table.users')
            ->where("table.users.{$column} > {$offset}");

        if (!empty($group)) {
            $select->where('table.users.group = ?', $group);
        }

        $count = $this->db->fetchObject($select)->num + 1;
        return ceil($count / $pageSize);
    }
}
