<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db\Exception;
use Typecho\Validate;
use Utils\PasswordHash;
use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 登録コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Register extends Users implements ActionInterface
{
    /**
     * 初期化関数
     *
     * @throws Exception
     */
    public function action()
    {
        // protect
        $this->security->protect();

        /** すでにログインしている場合 */
        if ($this->user->hasLogin() || !$this->options->allowRegister) {
            /** ダイレクト・リターン */
            $this->response->redirect($this->options->index);
        }

        /** 検証クラスを初期化する */
        $validator = new Validate();
        $validator->addRule('name', 'required', _t('ユーザー名は必須'));
        $validator->addRule('name', 'minLength', _t('ユーザー名には少なくとも2文字'), 2);
        $validator->addRule('name', 'maxLength', _t('ユーザー名には32文字'), 32);
        $validator->addRule('name', 'xssCheck', _t('ユーザー名に特殊文字は使用しないでください！'));
        $validator->addRule('name', [$this, 'nameExists'], _t('ユーザー名はすでに存在する'));
        $validator->addRule('mail', 'required', _t('メールアドレスは必須'));
        $validator->addRule('mail', [$this, 'mailExists'], _t('メールアドレスがすでに存在する'));
        $validator->addRule('mail', 'email', _t('電子メールアドレスの書式が正しくない'));
        $validator->addRule('mail', 'maxLength', _t('メールアドレスには64文字'), 64);

        /** リクエストにpassword */
        if (array_key_exists('password', $_REQUEST)) {
            $validator->addRule('password', 'required', _t('パスワードが必要'));
            $validator->addRule('password', 'minLength', _t('アカウントのセキュリティについて, 6桁以上のパスワードを入力してください。'), 6);
            $validator->addRule('password', 'maxLength', _t('覚えやすくするために, パスワードの長さは18桁を超えないこと。'), 18);
            $validator->addRule('confirm', 'confirm', _t('二度入力された一貫性のないパスワード'), 'password');
        }

        /** インターセプト検証例外 */
        if ($error = $validator->run($this->request->from('name', 'password', 'mail', 'confirm'))) {
            Cookie::set('__typecho_remember_name', $this->request->name);
            Cookie::set('__typecho_remember_mail', $this->request->mail);

            /** アラートメッセージの設定 */
            Notice::alloc()->set($error);
            $this->response->goBack();
        }

        $hasher = new PasswordHash(8, true);
        $generatedPassword = Common::randString(7);

        $dataStruct = [
            'name' => $this->request->name,
            'mail' => $this->request->mail,
            'screenName' => $this->request->name,
            'password' => $hasher->hashPassword($generatedPassword),
            'created' => $this->options->time,
            'group' => 'subscriber'
        ];

        $dataStruct = self::pluginHandle()->register($dataStruct);

        $insertId = $this->insert($dataStruct);
        $this->db->fetchRow($this->select()->where('uid = ?', $insertId)
            ->limit(1), [$this, 'push']);

        self::pluginHandle()->finishRegister($this);

        $this->user->login($this->request->name, $generatedPassword);

        Cookie::delete('__typecho_first_run');
        Cookie::delete('__typecho_remember_name');
        Cookie::delete('__typecho_remember_mail');

        Notice::alloc()->set(
            _t(
                'ユーザー <strong>%s</strong> すでに登録済み, パスワードは <strong>%s</strong>',
                $this->screenName,
                $generatedPassword
            ),
            'success'
        );
        $this->response->redirect($this->options->adminUrl);
    }
}
