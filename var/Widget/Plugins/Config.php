<?php

namespace Widget\Plugins;

use Typecho\Plugin;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * プラグイン設定コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Config extends Options
{
    /**
     * プラグイン情報の取得
     *
     * @var array
     */
    public $info;

    /**
     * プラグインファイルのパス
     *
     * @var string
     */
    private $pluginFileName;

    /**
     * プラグイン
     *
     * @var string
     */
    private $className;

    /**
     * バインド・アクション
     *
     * @throws Plugin\Exception
     * @throws Exception|\Typecho\Db\Exception
     */
    public function execute()
    {
        $this->user->pass('administrator');
        $config = $this->request->filter('slug')->config;
        if (empty($config)) {
            throw new Exception(_t('プラグインが存在しない'), 404);
        }

        /** プラグインポータルの取得 */
        [$this->pluginFileName, $this->className] = Plugin::portal($config, $this->options->pluginDir);
        $this->info = Plugin::parseInfo($this->pluginFileName);
    }

    /**
     * メニュータイトルの取得
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('プラグインの設定 %s', $this->info['title']);
    }

    /**
     * プラグインの設定
     *
     * @return Form
     * @throws Exception|Plugin\Exception
     */
    public function config()
    {
        /** プラグイン名の取得 */
        $pluginName = $this->request->filter('slug')->config;

        /** 有効なプラグインを取得する */
        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'];

        /** インスタンス化が成功したかどうかの判定 */
        if (!$this->info['config'] || !isset($activatedPlugins[$pluginName])) {
            throw new Exception(_t('无法プラグインの設定'), 500);
        }

        /** プラグインをロードする */
        require_once $this->pluginFileName;
        $form = new Form($this->security->getIndex('/action/plugins-edit?config=' . $pluginName), Form::POST_METHOD);
        call_user_func([$this->className, 'config'], $form);

        $options = $this->options->plugin($pluginName);

        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $form->getInput($key)->value($val);
            }
        }

        $submit = new Form\Element\Submit(null, null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);
        return $form;
    }
}
