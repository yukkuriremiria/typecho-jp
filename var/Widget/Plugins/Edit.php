<?php

namespace Widget\Plugins;

use Typecho\Common;
use Typecho\Db;
use Typecho\Plugin;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * プラグイン管理コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends Options implements ActionInterface
{
    /**
     * @var bool
     */
    private $configNoticed = false;

    /**
     * プラグインの有効化
     *
     * @param $pluginName
     * @throws Exception|Db\Exception|Plugin\Exception
     */
    public function activate($pluginName)
    {
        /** プラグインポータルの取得 */
        [$pluginFileName, $className] = Plugin::portal($pluginName, $this->options->pluginDir);
        $info = Plugin::parseInfo($pluginFileName);

        /** 依存情報の検出 */
        if (Plugin::checkDependence($info['since'])) {

            /** 获取已プラグインの有効化 */
            $plugins = Plugin::export();
            $activatedPlugins = $plugins['activated'];

            /** プラグインをロードする */
            require_once $pluginFileName;

            /** インスタンス化が成功したかどうかの判定 */
            if (
                isset($activatedPlugins[$pluginName]) || !class_exists($className)
                || !method_exists($className, 'activate')
            ) {
                throw new Exception(_t('无法プラグインの有効化'), 500);
            }

            try {
                $result = call_user_func([$className, 'activate']);
                Plugin::activate($pluginName);
                $this->update(
                    ['value' => serialize(Plugin::export())],
                    $this->db->sql()->where('name = ?', 'plugins')
                );
            } catch (Plugin\Exception $e) {
                /** インターセプション・アノマリー */
                Notice::alloc()->set($e->getMessage(), 'error');
                $this->response->goBack();
            }

            $form = new Form();
            call_user_func([$className, 'config'], $form);

            $personalForm = new Form();
            call_user_func([$className, 'personalConfig'], $personalForm);

            $options = $form->getValues();
            $personalOptions = $personalForm->getValues();

            if ($options && !$this->configHandle($pluginName, $options, true)) {
                self::configPlugin($pluginName, $options);
            }

            if ($personalOptions && !$this->personalConfigHandle($className, $personalOptions)) {
                self::configPlugin($pluginName, $personalOptions, true);
            }
        } else {
            $result = _t('<a href="%s">%s</a> このバージョンではtypecho通常運転時', $info['homepage'], $info['title']);
        }

        /** ハイライトの設定 */
        Notice::alloc()->highlight('plugin-' . $pluginName);

        if (isset($result) && is_string($result)) {
            Notice::alloc()->set($result, 'notice');
        } else {
            Notice::alloc()->set(_t('プラグインが有効'), 'success');
        }
        $this->response->goBack();
    }

    /**
     * 独自の機能で設定情報を処理する
     *
     * @access public
     * @param string $pluginName プラグイン名
     * @param array $settings 設定値
     * @param boolean $isInit 初期化されているか
     * @return boolean
     * @throws Plugin\Exception
     */
    public function configHandle(string $pluginName, array $settings, bool $isInit): bool
    {
        /** プラグインポータルの取得 */
        [$pluginFileName, $className] = Plugin::portal($pluginName, $this->options->pluginDir);

        if (!$isInit && method_exists($className, 'configCheck')) {
            $result = call_user_func([$className, 'configCheck'], $settings);

            if (!empty($result) && is_string($result)) {
                Notice::alloc()->set($result, 'notice');
                $this->configNoticed = true;
            }
        }

        if (method_exists($className, 'configHandle')) {
            call_user_func([$className, 'configHandle'], $settings, $isInit);
            return true;
        }

        return false;
    }

    /**
     * プラグイン変数の手動設定
     *
     * @param string $pluginName プラグイン名
     * @param array $settings 変数のキーと値のペア
     * @param bool $isPersonal プライベート変数かどうか
     * @throws Db\Exception
     */
    public static function configPlugin(string $pluginName, array $settings, bool $isPersonal = false)
    {
        $db = Db::get();
        $pluginName = ($isPersonal ? '_' : '') . 'plugin:' . $pluginName;

        $select = $db->select()->from('table.options')
            ->where('name = ?', $pluginName);

        $options = $db->fetchAll($select);

        if (empty($settings)) {
            if (!empty($options)) {
                $db->query($db->delete('table.options')->where('name = ?', $pluginName));
            }
        } else {
            if (empty($options)) {
                $db->query($db->insert('table.options')
                    ->rows([
                        'name'  => $pluginName,
                        'value' => serialize($settings),
                        'user'  => 0
                    ]));
            } else {
                foreach ($options as $option) {
                    $value = unserialize($option['value']);
                    $value = array_merge($value, $settings);

                    $db->query($db->update('table.options')
                        ->rows(['value' => serialize($value)])
                        ->where('name = ?', $pluginName)
                        ->where('user = ?', $option['user']));
                }
            }
        }
    }

    /**
     * 独自の関数でカスタム設定情報を処理する
     *
     * @param string $className クラス名
     * @param array $settings 設定値
     * @return boolean
     */
    public function personalConfigHandle(string $className, array $settings): bool
    {
        if (method_exists($className, 'personalConfigHandle')) {
            call_user_func([$className, 'personalConfigHandle'], $settings, true);
            return true;
        }

        return false;
    }

    /**
     * プラグインを無効にする
     *
     * @param string $pluginName
     * @throws Db\Exception
     * @throws Exception
     * @throws Plugin\Exception
     */
    public function deactivate(string $pluginName)
    {
        /** 获取已プラグインの有効化 */
        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'];
        $pluginFileExist = true;

        try {
            /** プラグインポータルの取得 */
            [$pluginFileName, $className] = Plugin::portal($pluginName, $this->options->pluginDir);
        } catch (Plugin\Exception $e) {
            $pluginFileExist = false;

            if (!isset($activatedPlugins[$pluginName])) {
                throw $e;
            }
        }

        /** インスタンス化が成功したかどうかの判定 */
        if (!isset($activatedPlugins[$pluginName])) {
            throw new Exception(_t('无法プラグインを無効にする'), 500);
        }

        if ($pluginFileExist) {

            /** プラグインをロードする */
            require_once $pluginFileName;

            /** インスタンス化が成功したかどうかの判定 */
            if (
                !isset($activatedPlugins[$pluginName]) || !class_exists($className)
                || !method_exists($className, 'deactivate')
            ) {
                throw new Exception(_t('无法プラグインを無効にする'), 500);
            }

            try {
                $result = call_user_func([$className, 'deactivate']);
            } catch (Plugin\Exception $e) {
                /** インターセプション・アノマリー */
                Notice::alloc()->set($e->getMessage(), 'error');
                $this->response->goBack();
            }

            /** ハイライトの設定 */
            Notice::alloc()->highlight('plugin-' . $pluginName);
        }

        Plugin::deactivate($pluginName);
        $this->update(['value' => serialize(Plugin::export())], $this->db->sql()->where('name = ?', 'plugins'));

        $this->delete($this->db->sql()->where('name = ?', 'plugin:' . $pluginName));
        $this->delete($this->db->sql()->where('name = ?', '_plugin:' . $pluginName));

        if (isset($result) && is_string($result)) {
            Notice::alloc()->set($result, 'notice');
        } else {
            Notice::alloc()->set(_t('プラグインが無効になりました'), 'success');
        }
        $this->response->goBack();
    }

    /**
     * プラグインの設定
     *
     * @param string $pluginName
     * @throws Db\Exception
     * @throws Exception
     * @throws Plugin\Exception
     */
    public function config(string $pluginName)
    {
        $form = Config::alloc()->config();

        /** バリデーションフォーム */
        if ($form->validate()) {
            $this->response->goBack();
        }

        $settings = $form->getAllRequest();

        if (!$this->configHandle($pluginName, $settings, false)) {
            self::configPlugin($pluginName, $settings);
        }

        /** ハイライトの設定 */
        Notice::alloc()->highlight('plugin-' . $pluginName);

        if (!$this->configNoticed) {
            /** アラート */
            Notice::alloc()->set(_t("プラグインの設定が保存されました"), 'success');
        }

        /** オリジナルページへ */
        $this->response->redirect(Common::url('plugins.php', $this->options->adminUrl));
    }

    /**
     * バインド・アクション
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->is('activate'))->activate($this->request->filter('slug')->activate);
        $this->on($this->request->is('deactivate'))->deactivate($this->request->filter('slug')->deactivate);
        $this->on($this->request->is('config'))->config($this->request->filter('slug')->config);
        $this->response->redirect($this->options->adminUrl);
    }
}
