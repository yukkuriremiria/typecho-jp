<?php

namespace Widget\Users;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Plugin;
use Typecho\Widget\Helper\Form;
use Utils\PasswordHash;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;
use Widget\Plugins\Rows;

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
class Profile extends Edit implements ActionInterface
{
    /**
     * 実行可能関数
     */
    public function execute()
    {
        /** 登録ユーザー以上の権限 */
        $this->user->pass('subscriber');
        $this->request->setParam('uid', $this->user->uid);
    }

    /**
     * 出力フォームの構造
     *
     * @access public
     * @return Form
     */
    public function optionsForm(): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);

        /** ライティング設定 */
        $markdown = new Form\Element\Radio(
            'markdown',
            ['0' => _t('凝固'), '1' => _t('見せる')],
            $this->options->markdown,
            _t('利用する Markdown 文法の編集とコンテンツの解析'),
            _t('利用する <a href="https://daringfireball.net/projects/markdown/">Markdown</a> より簡単で直感的なライティングを可能にする構文.')
            . '<br />' . _t('此功能开启不会影响以前没有利用する Markdown 文法エディタの内容.')
        );
        $form->addInput($markdown);

        $xmlrpcMarkdown = new Form\Element\Radio(
            'xmlrpcMarkdown',
            ['0' => _t('凝固'), '1' => _t('見せる')],
            $this->options->xmlrpcMarkdown,
            _t('ある XMLRPC 接口中利用する Markdown 語彙'),
            _t('を全面的にサポートする。 <a href="https://daringfireball.net/projects/markdown/">Markdown</a> 語彙写作的离线编辑器, 見せる此选项后将避免内容被转换为 HTML.')
        );
        $form->addInput($xmlrpcMarkdown);

        /** オートセーブ */
        $autoSave = new Form\Element\Radio(
            'autoSave',
            ['0' => _t('凝固'), '1' => _t('見せる')],
            $this->options->autoSave,
            _t('オートセーブ'),
            _t('オートセーブ功能可以更好地保护你的文章不会丢失.')
        );
        $form->addInput($autoSave);

        /** デフォルトで許可 */
        $allow = [];
        if ($this->options->defaultAllowComment) {
            $allow[] = 'comment';
        }

        if ($this->options->defaultAllowPing) {
            $allow[] = 'ping';
        }

        if ($this->options->defaultAllowFeed) {
            $allow[] = 'feed';
        }

        $defaultAllow = new Form\Element\Checkbox(
            'defaultAllow',
            ['comment' => _t('コメント可能'), 'ping' => _t('見積もり可能'), 'feed' => _t('出现ある聚合中')],
            $allow,
            _t('デフォルトで許可'),
            _t('设置你经常利用する的デフォルトで許可权限')
        );
        $form->addInput($defaultAllow);

        /** ユーザーアクション */
        $do = new Form\Element\Hidden('do', null, 'options');
        $form->addInput($do);

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * 設定リストのカスタマイズ
     *
     * @throws Plugin\Exception
     */
    public function personalFormList()
    {
        $plugins = Rows::alloc('activated=1');

        while ($plugins->next()) {
            if ($plugins->personalConfig) {
                [$pluginFileName, $className] = Plugin::portal($plugins->name, $this->options->pluginDir);

                $form = $this->personalForm($plugins->name, $className, $pluginFileName, $group);
                if ($this->user->pass($group, true)) {
                    echo '<br><section id="personal-' . $plugins->name . '">';
                    echo '<h3>' . $plugins->title . '</h3>';

                    $form->render();

                    echo '</section>';
                }
            }
        }
    }

    /**
     * カスタム設定オプションの出力
     *
     * @access public
     * @param string $pluginName プラグイン名
     * @param string $className クラス名
     * @param string $pluginFileName プラグインファイル名
     * @param string|null $group ユーザーグループ
     * @throws Plugin\Exception
     */
    public function personalForm(string $pluginName, string $className, string $pluginFileName, ?string &$group)
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);
        $form->setAttribute('name', $pluginName);
        $form->setAttribute('id', $pluginName);

        require_once $pluginFileName;
        $group = call_user_func([$className, 'personalConfig'], $form);
        $group = $group ?: 'subscriber';

        $options = $this->options->personalPlugin($pluginName);

        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $form->getInput($key)->value($val);
            }
        }

        $form->addItem(new Form\Element\Hidden('do', null, 'personal'));
        $form->addItem(new Form\Element\Hidden('plugin', null, $pluginName));
        $submit = new Form\Element\Submit('submit', null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        return $form;
    }

    /**
     * ユーザーを更新する
     *
     * @throws Exception
     */
    public function updateProfile()
    {
        if ($this->profileForm()->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $user = $this->request->from('mail', 'screenName', 'url');
        $user['screenName'] = empty($user['screenName']) ? $user['name'] : $user['screenName'];

        /** 更新データ */
        $this->update($user, $this->db->sql()->where('uid = ?', $this->user->uid));

        /** ハイライトの設定 */
        Notice::alloc()->highlight('user-' . $this->user->uid);

        /** アラート */
        Notice::alloc()->set(_t('プロフィールが更新されました'), 'success');

        /** オリジナルページへ */
        $this->response->goBack();
    }

    /**
     * フォームの作成
     *
     * @return Form
     */
    public function profileForm()
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);

        /** ユーザーニックネーム */
        $screenName = new Form\Element\Text('screenName', null, null, _t('愛称'), _t('ユーザーニックネーム可以与用户名不同, フロントエンド・ディスプレイ用.')
            . '<br />' . _t('空欄の場合, 将默认利用する用户名.'));
        $form->addInput($screenName);

        /** 個人ホームページアドレス */
        $url = new Form\Element\Text('url', null, null, _t('個人ホームページアドレス'), _t('此用户的個人ホームページアドレス, ご利用ください <code>http://</code> 始まり.'));
        $form->addInput($url);

        /** Eメールアドレス */
        $mail = new Form\Element\Text('mail', null, null, _t('Eメールアドレス') . ' *', _t('Eメールアドレス将作为此用户的主要联系方式.')
            . '<br />' . _t('请不要与系统中现有的Eメールアドレス重复.'));
        $form->addInput($mail);

        /** ユーザーアクション */
        $do = new Form\Element\Hidden('do', null, 'profile');
        $form->addInput($do);

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('プロフィールの更新'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $screenName->value($this->user->screenName);
        $url->value($this->user->url);
        $mail->value($this->user->mail);

        /** フォームにルールを追加する */
        $screenName->addRule([$this, 'screenNameExists'], _t('愛称已经存ある'));
        $screenName->addRule('xssCheck', _t('请不要ある愛称中利用する特殊字符'));
        $url->addRule('url', _t('個人ホームページアドレス格式错误'));
        $mail->addRule('required', _t('メールアドレスは必須'));
        $mail->addRule([$this, 'mailExists'], _t('Eメールアドレス已经存ある'));
        $mail->addRule('email', _t('電子メールアドレスの書式が正しくない'));

        return $form;
    }

    /**
     * 更新アクションの実行
     *
     * @throws Exception
     */
    public function updateOptions()
    {
        $settings['autoSave'] = $this->request->autoSave ? 1 : 0;
        $settings['markdown'] = $this->request->markdown ? 1 : 0;
        $settings['xmlrpcMarkdown'] = $this->request->xmlrpcMarkdown ? 1 : 0;
        $defaultAllow = $this->request->getArray('defaultAllow');

        $settings['defaultAllowComment'] = in_array('comment', $defaultAllow) ? 1 : 0;
        $settings['defaultAllowPing'] = in_array('ping', $defaultAllow) ? 1 : 0;
        $settings['defaultAllowFeed'] = in_array('feed', $defaultAllow) ? 1 : 0;

        foreach ($settings as $name => $value) {
            if (
                $this->db->fetchObject($this->db->select(['COUNT(*)' => 'num'])
                    ->from('table.options')->where('name = ? AND user = ?', $name, $this->user->uid))->num > 0
            ) {
                Options::alloc()
                    ->update(
                        ['value' => $value],
                        $this->db->sql()->where('name = ? AND user = ?', $name, $this->user->uid)
                    );
            } else {
                Options::alloc()->insert([
                    'name'  => $name,
                    'value' => $value,
                    'user'  => $this->user->uid
                ]);
            }
        }

        Notice::alloc()->set(_t("設定が保存されました"), 'success');
        $this->response->goBack();
    }

    /**
     * パスワードの更新
     *
     * @throws Exception
     */
    public function updatePassword()
    {
        /** バリデーション形式 */
        if ($this->passwordForm()->validate()) {
            $this->response->goBack();
        }

        $hasher = new PasswordHash(8, true);
        $password = $hasher->hashPassword($this->request->password);

        /** 更新データ */
        $this->update(
            ['password' => $password],
            $this->db->sql()->where('uid = ?', $this->user->uid)
        );

        /** ハイライトの設定 */
        Notice::alloc()->highlight('user-' . $this->user->uid);

        /** アラート */
        Notice::alloc()->set(_t('パスワードは正常に変更されました'), 'success');

        /** オリジナルページへ */
        $this->response->goBack();
    }

    /**
     * フォームの作成
     *
     * @return Form
     */
    public function passwordForm(): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/users-profile'), Form::POST_METHOD);

        /** ユーザーパスワード */
        $password = new Form\Element\Password('password', null, null, _t('ユーザーパスワード'), _t('このユーザーにパスワードを割り当てる.')
            . '<br />' . _t('建议利用する特殊字符与字母、数字のミックススタイル,システムの安全性を高める.'));
        $password->input->setAttribute('class', 'w-60');
        $form->addInput($password);

        /** ユーザーパスワード确认 */
        $confirm = new Form\Element\Password('confirm', null, null, _t('ユーザーパスワード确认'), _t('パスワードを確認してください, 上記で入力したパスワードと同じものを保持する。.'));
        $confirm->input->setAttribute('class', 'w-60');
        $form->addInput($confirm);

        /** ユーザーアクション */
        $do = new Form\Element\Hidden('do', null, 'password');
        $form->addInput($do);

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('パスワードの更新'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        $password->addRule('required', _t('パスワードが必要'));
        $password->addRule('minLength', _t('アカウントのセキュリティについて, 6桁以上のパスワードを入力してください。'), 6);
        $confirm->addRule('confirm', _t('二度入力された一貫性のないパスワード'), 'password');

        return $form;
    }

    /**
     * 個人設定の更新
     *
     * @throws \Typecho\Widget\Exception
     */
    public function updatePersonal()
    {
        /** 获取プラグイン名 */
        $pluginName = $this->request->plugin;

        /** 有効なプラグインを取得する */
        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'];

        /** プラグインポータルの取得 */
        [$pluginFileName, $className] = Plugin::portal(
            $this->request->plugin,
            __TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__
        );
        $info = Plugin::parseInfo($pluginFileName);

        if (!$info['personalConfig'] || !isset($activatedPlugins[$pluginName])) {
            throw new \Typecho\Widget\Exception(_t('プラグインを設定できない'), 500);
        }

        $form = $this->personalForm($pluginName, $className, $pluginFileName, $group);
        $this->user->pass($group);

        /** バリデーションフォーム */
        if ($form->validate()) {
            $this->response->goBack();
        }

        $settings = $form->getAllRequest();
        unset($settings['do'], $settings['plugin']);
        $name = '_plugin:' . $pluginName;

        if (!$this->personalConfigHandle($className, $settings)) {
            if (
                $this->db->fetchObject($this->db->select(['COUNT(*)' => 'num'])
                    ->from('table.options')->where('name = ? AND user = ?', $name, $this->user->uid))->num > 0
            ) {
                Options::alloc()
                    ->update(
                        ['value' => serialize($settings)],
                        $this->db->sql()->where('name = ? AND user = ?', $name, $this->user->uid)
                    );
            } else {
                Options::alloc()->insert([
                    'name'  => $name,
                    'value' => serialize($settings),
                    'user'  => $this->user->uid
                ]);
            }
        }

        /** アラート */
        Notice::alloc()->set(_t("%s 設定が保存されました", $info['title']), 'success');

        /** オリジナルページへ */
        $this->response->redirect(Common::url('profile.php', $this->options->adminUrl));
    }

    /**
     * 独自の関数でカスタム設定情報を処理する
     *
     * @access public
     * @param string $className クラス名
     * @param array $settings 設定値
     * @return boolean
     */
    public function personalConfigHandle(string $className, array $settings): bool
    {
        if (method_exists($className, 'personalConfigHandle')) {
            call_user_func([$className, 'personalConfigHandle'], $settings, false);
            return true;
        }

        return false;
    }

    /**
     * エントリ機能
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=profile'))->updateProfile();
        $this->on($this->request->is('do=options'))->updateOptions();
        $this->on($this->request->is('do=password'))->updatePassword();
        $this->on($this->request->is('do=personal&plugin'))->updatePersonal();
        $this->response->redirect($this->options->siteUrl);
    }
}
