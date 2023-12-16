<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db\Exception as DbException;
use Typecho\Widget;
use Utils\PasswordHash;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 現在ログインしているユーザー
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class User extends Users
{
    /**
     * ユーザーグループ
     *
     * @var array
     */
    public $groups = [
        'administrator' => 0,
        'editor' => 1,
        'contributor' => 2,
        'subscriber' => 3,
        'visitor' => 4
    ];

    /**
     * ユーザー
     *
     * @var array
     */
    private $currentUser;

    /**
     * ログインしていますか？
     *
     * @var boolean|null
     */
    private $hasLogin = null;

    /**
     * @param int $components
     */
    protected function initComponents(int &$components)
    {
        $components = self::INIT_OPTIONS;
    }

    /**
     * 実行可能関数
     *
     * @throws DbException
     */
    public function execute()
    {
        if ($this->hasLogin()) {
            $this->push($this->currentUser);

            // update last activated time
            $this->db->query($this->db
                ->update('table.users')
                ->rows(['activated' => $this->options->time])
                ->where('uid = ?', $this->currentUser['uid']));

            // merge personal options
            $options = $this->personalOptions->toArray();

            foreach ($options as $key => $val) {
                $this->options->{$key} = $val;
            }
        }
    }

    /**
     * 判断ユーザーログインしていますか？
     *
     * @return boolean
     * @throws DbException
     */
    public function hasLogin(): ?bool
    {
        if (null !== $this->hasLogin) {
            return $this->hasLogin;
        } else {
            $cookieUid = Cookie::get('__typecho_uid');
            if (null !== $cookieUid) {
                /** ログインの確認 */
                $user = $this->db->fetchRow($this->db->select()->from('table.users')
                    ->where('uid = ?', intval($cookieUid))
                    ->limit(1));

                $cookieAuthCode = Cookie::get('__typecho_authCode');
                if ($user && Common::hashValidate($user['authCode'], $cookieAuthCode)) {
                    $this->currentUser = $user;
                    return ($this->hasLogin = true);
                }

                $this->logout();
            }

            return ($this->hasLogin = false);
        }
    }

    /**
     * ユーザー登出函数
     *
     * @access public
     * @return void
     */
    public function logout()
    {
        self::pluginHandle()->trigger($logoutPluggable)->logout();
        if ($logoutPluggable) {
            return;
        }

        Cookie::delete('__typecho_uid');
        Cookie::delete('__typecho_authCode');
    }

    /**
     * 以ユーザー名和暗号化登录
     *
     * @access public
     * @param string $name ユーザー名
     * @param string $password 暗号化
     * @param boolean $temporarily 一時的なログインかどうか
     * @param integer $expire 有効期限
     * @return boolean
     * @throws DbException
     */
    public function login(string $name, string $password, bool $temporarily = false, int $expire = 0): bool
    {
        //プラグインインターフェース
        $result = self::pluginHandle()->trigger($loginPluggable)->login($name, $password, $temporarily, $expire);
        if ($loginPluggable) {
            return $result;
        }

        /** 开始验证ユーザー **/
        $user = $this->db->fetchRow($this->db->select()
            ->from('table.users')
            ->where((strpos($name, '@') ? 'mail' : 'name') . ' = ?', $name)
            ->limit(1));

        if (empty($user)) {
            return false;
        }

        $hashValidate = self::pluginHandle()->trigger($hashPluggable)->hashValidate($password, $user['password']);
        if (!$hashPluggable) {
            if ('$P$' == substr($user['password'], 0, 3)) {
                $hasher = new PasswordHash(8, true);
                $hashValidate = $hasher->checkPassword($password, $user['password']);
            } else {
                $hashValidate = Common::hashValidate($password, $user['password']);
            }
        }

        if ($user && $hashValidate) {
            if (!$temporarily) {
                $this->commitLogin($user, $expire);
            }

            /** データを押し込む */
            $this->push($user);
            $this->currentUser = $user;
            $this->hasLogin = true;
            self::pluginHandle()->loginSucceed($this, $name, $password, $temporarily, $expire);

            return true;
        }

        self::pluginHandle()->loginFail($this, $name, $password, $temporarily, $expire);
        return false;
    }

    /**
     * @param $user
     * @param int $expire
     * @throws DbException
     */
    public function commitLogin(&$user, int $expire = 0)
    {
        $authCode = function_exists('openssl_random_pseudo_bytes') ?
            bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Common::randString(20));
        $user['authCode'] = $authCode;

        Cookie::set('__typecho_uid', $user['uid'], $expire);
        Cookie::set('__typecho_authCode', Common::hash($authCode), $expire);

        //最終ログイン時刻と認証コードの更新
        $this->db->query($this->db
            ->update('table.users')
            ->expression('logged', 'activated')
            ->rows(['authCode' => $authCode])
            ->where('uid = ?', $user['uid']));
    }

    /**
     * 必要なのはuidまたは完全なuser配列でログインする方法, 主にプラグインなどの特別な機会に使用される
     *
     * @param int | array $uid ユーザーid或者ユーザー数据数组
     * @param boolean $temporarily 一時的なログインかどうか，以前の方法との互換性のため、デフォルトは一時ログインになります。
     * @param integer $expire 有効期限
     * @return boolean
     * @throws DbException
     */
    public function simpleLogin($uid, bool $temporarily = true, int $expire = 0): bool
    {
        if (is_array($uid)) {
            $user = $uid;
        } else {
            $user = $this->db->fetchRow($this->db->select()
                ->from('table.users')
                ->where('uid = ?', $uid)
                ->limit(1));
        }

        if (empty($user)) {
            self::pluginHandle()->simpleLoginFail($this);
            return false;
        }

        if (!$temporarily) {
            $this->commitLogin($user, $expire);
        }

        $this->push($user);
        $this->currentUser = $user;
        $this->hasLogin = true;

        self::pluginHandle()->simpleLoginSucceed($this, $user);
        return true;
    }

    /**
     * 判断ユーザー权限
     *
     * @access public
     * @param string $group ユーザーグループ
     * @param boolean $return リターン・モードかどうか
     * @return boolean
     * @throws DbException|Widget\Exception
     */
    public function pass(string $group, bool $return = false): bool
    {
        if ($this->hasLogin()) {
            if (array_key_exists($group, $this->groups) && $this->groups[$this->group] <= $this->groups[$group]) {
                return true;
            }
        } else {
            if ($return) {
                return false;
            } else {
                //循環リダイレクトの防止
                $this->response->redirect(defined('__TYPECHO_ADMIN__') ? $this->options->loginUrl .
                    (0 === strpos($this->request->getReferer() ?? '', $this->options->loginUrl) ? '' :
                        '?referer=' . urlencode($this->request->makeUriByRequest())) : $this->options->siteUrl, false);
            }
        }

        if ($return) {
            return false;
        } else {
            throw new Widget\Exception(_t('訪問を禁じる'), 403);
        }
    }
}
