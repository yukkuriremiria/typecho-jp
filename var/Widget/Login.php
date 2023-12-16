<?php

namespace Widget;

use Typecho\Cookie;
use Typecho\Validate;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ログインコンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Login extends Users implements ActionInterface
{
    /**
     * 初期化関数
     *
     * @access public
     * @return void
     */
    public function action()
    {
        // protect
        $this->security->protect();

        /** すでにログインしている場合 */
        if ($this->user->hasLogin()) {
            /** ダイレクト・リターン */
            $this->response->redirect($this->options->index);
        }

        /** 検証クラスを初期化する */
        $validator = new Validate();
        $validator->addRule('name', 'required', _t('ユーザー名を入力してください'));
        $validator->addRule('password', 'required', _t('パスワードを入力してください'));
        $expire = 30 * 24 * 3600;

        /** パスワードを記憶するステータス */
        if ($this->request->remember) {
            Cookie::set('__typecho_remember_remember', 1, $expire);
        } elseif (Cookie::get('__typecho_remember_remember')) {
            Cookie::delete('__typecho_remember_remember');
        }

        /** 検証例外のインターセプト */
        if ($error = $validator->run($this->request->from('name', 'password'))) {
            Cookie::set('__typecho_remember_name', $this->request->name);

            /** アラートメッセージの設定 */
            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        /** ユーザー認証の開始 **/
        $valid = $this->user->login(
            $this->request->name,
            $this->request->password,
            false,
            1 == $this->request->remember ? $expire : 0
        );

        /** パスワード比較 */
        if (!$valid) {
            /** 網羅的列挙の防止,ハイバネーション360分の1度に相当する角度または弧の単位 */
            sleep(3);

            self::pluginHandle()->loginFail(
                $this->user,
                $this->request->name,
                $this->request->password,
                1 == $this->request->remember
            );

            Cookie::set('__typecho_remember_name', $this->request->name);
            Notice::alloc()->set(_t('無効なユーザー名またはパスワード'), 'error');
            $this->response->goBack('?referer=' . urlencode($this->request->referer));
        }

        self::pluginHandle()->loginSucceed(
            $this->user,
            $this->request->name,
            $this->request->password,
            1 == $this->request->remember
        );

        /** ジャンプ検証後のアドレス */
        if (!empty($this->request->referer)) {
            /** fix #952 & validate redirect url */
            if (
                0 === strpos($this->request->referer, $this->options->adminUrl)
                || 0 === strpos($this->request->referer, $this->options->siteUrl)
            ) {
                $this->response->redirect($this->request->referer);
            }
        } elseif (!$this->user->pass('contributor', true)) {
            /** 一般ユーザーがバックエンドに直接ジャンプできないようにする。 */
            $this->response->redirect($this->options->profileUrl);
        }

        $this->response->redirect($this->options->adminUrl);
    }
}
