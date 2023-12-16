<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Utils\PasswordHash;
use Widget\ActionInterface;
use Widget\Base\Users;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ユーザーコンポーネントの編集
 *
 * @link typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends Users implements ActionInterface
{
    /**
     * 実行可能関数
     *
     * @return void
     * @throws Exception|\Typecho\Db\Exception
     */
    public function execute()
    {
        /** 管理者以上の権限 */
        $this->user->pass('administrator');

        /** 更新モード */
        if (($this->request->uid && 'delete' != $this->request->do) || 'update' == $this->request->do) {
            $this->db->fetchRow($this->select()
                ->where('uid = ?', $this->request->uid)->limit(1), [$this, 'push']);

            if (!$this->have()) {
                throw new Exception(_t('ユーザーが存在しない'), 404);
            }
        }
    }

    /**
     * メニュータイトルの取得
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('編集ユーザー %s', $this->name);
    }

    /**
     * ユーザーが存在するかどうかを判断する
     *
     * @param integer $uid ユーザーキー
     * @return boolean
     * @throws \Typecho\Db\Exception
     */
    public function userExists(int $uid): bool
    {
        $user = $this->db->fetchRow($this->db->select()
            ->from('table.users')
            ->where('uid = ?', $uid)->limit(1));

        return !empty($user);
    }

    /**
     * ユーザー数の増加
     *
     * @throws \Typecho\Db\Exception
     */
    public function insertUser()
    {
        if ($this->form('insert')->validate()) {
            $this->response->goBack();
        }

        $hasher = new PasswordHash(8, true);

        /** データ抽出 */
        $user = $this->request->from('name', 'mail', 'screenName', 'password', 'url', 'group');
        $user['screenName'] = empty($user['screenName']) ? $user['name'] : $user['screenName'];
        $user['password'] = $hasher->hashPassword($user['password']);
        $user['created'] = $this->options->time;

        /** データ挿入 */
        $user['uid'] = $this->insert($user);

        /** ハイライトの設定 */
        Notice::alloc()->highlight('user-' . $user['uid']);

        /** アラート */
        Notice::alloc()->set(_t('ユーザー %s が追加された', $user['screenName']), 'success');

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-users.php', $this->options->adminUrl));
    }

    /**
     * フォームの作成
     *
     * @access public
     * @param string|null $action フォームアクション
     * @return Form
     */
    public function form(?string $action = null): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/users-edit'), Form::POST_METHOD);

        /** ユーザー名称 */
        $name = new Form\Element\Text('name', null, null, _t('ユーザー名') . ' *', _t('此ユーザー名将作为ユーザー登录时所用的名称.')
            . '<br />' . _t('请不要与系统中现有的ユーザー名重复.'));
        $form->addInput($name);

        /** Eメールアドレス */
        $mail = new Form\Element\Text('mail', null, null, _t('Eメールアドレス') . ' *', _t('Eメールアドレス将作为此ユーザー的主要联系方式.')
            . '<br />' . _t('请不要与系统中现有的Eメールアドレス重复.'));
        $form->addInput($mail);

        /** ユーザー昵称 */
        $screenName = new Form\Element\Text('screenName', null, null, _t('ユーザー昵称'), _t('ユーザー昵称可以与ユーザー名不同, フロントエンド・ディスプレイ用.')
            . '<br />' . _t('空欄の場合, 将默认使用ユーザー名.'));
        $form->addInput($screenName);

        /** ユーザー密码 */
        $password = new Form\Element\Password('password', null, null, _t('ユーザー密码'), _t('为此ユーザー分配一个密码.')
            . '<br />' . _t('特殊文字とアルファベットを推奨、数字のミックススタイル,システムの安全性を高める.'));
        $password->input->setAttribute('class', 'w-60');
        $form->addInput($password);

        /** ユーザー密码确认 */
        $confirm = new Form\Element\Password('confirm', null, null, _t('ユーザー密码确认'), _t('パスワードを確認してください, 上記で入力したパスワードと同じものを保持する。.'));
        $confirm->input->setAttribute('class', 'w-60');
        $form->addInput($confirm);

        /** 個人ホームページアドレス */
        $url = new Form\Element\Text('url', null, null, _t('個人ホームページアドレス'), _t('此ユーザー的個人ホームページアドレス, ご利用ください <code>http://</code> 始まり.'));
        $form->addInput($url);

        /** ユーザー组 */
        $group = new Form\Element\Select(
            'group',
            [
                'subscriber'  => _t('ウオッチャー'),
                'contributor' => _t('恩人'), 'editor' => _t('コンパイラ'), 'administrator' => _t('親方')
            ],
            null,
            _t('ユーザー组'),
            _t('不同的ユーザー组拥有不同的权限.') . '<br />' . _t('具体的な権限割り当てフォームについては<a href="https://docs.typecho.org/develop/acl">こちらを参照</a>.')
        );
        $form->addInput($group);

        /** ユーザー动作 */
        $do = new Form\Element\Hidden('do');
        $form->addInput($do);

        /** ユーザーキー */
        $uid = new Form\Element\Hidden('uid');
        $form->addInput($uid);

        /** 送信ボタン */
        $submit = new Form\Element\Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        if (null != $this->request->uid) {
            $submit->value(_t('編集ユーザー'));
            $name->value($this->name);
            $screenName->value($this->screenName);
            $url->value($this->url);
            $mail->value($this->mail);
            $group->value($this->group);
            $do->value('update');
            $uid->value($this->uid);
            $_action = 'update';
        } else {
            $submit->value(_t('ユーザー数の増加'));
            $do->value('insert');
            $_action = 'insert';
        }

        if (empty($action)) {
            $action = $_action;
        }

        /** フォームにルールを追加する */
        if ('insert' == $action || 'update' == $action) {
            $screenName->addRule([$this, 'screenNameExists'], _t('ニックネームはすでに存在する'));
            $screenName->addRule('xssCheck', _t('ニックネームには特殊文字は使用しないでください！'));
            $url->addRule('url', _t('個人ホームページアドレス格式错误'));
            $mail->addRule('required', _t('メールアドレスは必須'));
            $mail->addRule([$this, 'mailExists'], _t('Eメールアドレス已经存在'));
            $mail->addRule('email', _t('電子メールアドレスの書式が正しくない'));
            $password->addRule('minLength', _t('アカウントのセキュリティについて, 6桁以上のパスワードを入力してください。'), 6);
            $confirm->addRule('confirm', _t('二度入力された一貫性のないパスワード'), 'password');
        }

        if ('insert' == $action) {
            $name->addRule('required', _t('必须填写ユーザー名称'));
            $name->addRule('xssCheck', _t('请不要在ユーザー名中使用特殊字符'));
            $name->addRule([$this, 'nameExists'], _t('ユーザー名已经存在'));
            $password->label(_t('ユーザー密码') . ' *');
            $confirm->label(_t('ユーザー密码确认') . ' *');
            $password->addRule('required', _t('パスワードが必要'));
        }

        if ('update' == $action) {
            $name->input->setAttribute('disabled', 'disabled');
            $uid->addRule('required', _t('ユーザーキー不存在'));
            $uid->addRule([$this, 'userExists'], _t('ユーザーが存在しない'));
        }

        return $form;
    }

    /**
     * 更新ユーザー
     *
     * @throws \Typecho\Db\Exception
     */
    public function updateUser()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $user = $this->request->from('mail', 'screenName', 'password', 'url', 'group');
        $user['screenName'] = empty($user['screenName']) ? $user['name'] : $user['screenName'];
        if (empty($user['password'])) {
            unset($user['password']);
        } else {
            $hasher = new PasswordHash(8, true);
            $user['password'] = $hasher->hashPassword($user['password']);
        }

        /** 更新データ */
        $this->update($user, $this->db->sql()->where('uid = ?', $this->request->uid));

        /** ハイライトの設定 */
        Notice::alloc()->highlight('user-' . $this->request->uid);

        /** アラート */
        Notice::alloc()->set(_t('ユーザー %s 更新されました', $user['screenName']), 'success');

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-users.php?' .
            $this->getPageOffsetQuery($this->request->uid), $this->options->adminUrl));
    }

    /**
     * のページオフセットを取得します。URL Query
     *
     * @param integer $uid ユーザーid
     * @return string
     * @throws \Typecho\Db\Exception
     */
    protected function getPageOffsetQuery(int $uid): string
    {
        return 'page=' . $this->getPageOffset('uid', $uid);
    }

    /**
     * 删除ユーザー
     *
     * @throws \Typecho\Db\Exception
     */
    public function deleteUser()
    {
        $users = $this->request->filter('int')->getArray('uid');
        $masterUserId = $this->db->fetchObject($this->db->select(['MIN(uid)' => 'num'])->from('table.users'))->num;
        $deleteCount = 0;

        foreach ($users as $user) {
            if ($masterUserId == $user || $user == $this->user->uid) {
                continue;
            }

            if ($this->delete($this->db->sql()->where('uid = ?', $user))) {
                $deleteCount++;
            }
        }

        /** アラート */
        Notice::alloc()->set(
            $deleteCount > 0 ? _t('ユーザー已经删除') : _t('没有ユーザー被删除'),
            $deleteCount > 0 ? 'success' : 'notice'
        );

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-users.php', $this->options->adminUrl));
    }

    /**
     * エントリ機能
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->is('do=insert'))->insertUser();
        $this->on($this->request->is('do=update'))->updateUser();
        $this->on($this->request->is('do=delete'))->deleteUser();
        $this->response->redirect($this->options->adminUrl);
    }
}
