<?php

namespace TypechoPlugin\HelloWorld;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Hello World
 *
 * @package HelloWorld
 * @author qining
 * @version 1.0.0
 * @link http://typecho.org
 */
class Plugin implements PluginInterface
{
    /**
     * プラグインの有効化,アクティベーションに失敗した場合,例外を直接投げる
     */
    public static function activate()
    {
        \Typecho\Plugin::factory('admin/menu.php')->navBar = __CLASS__ . '::render';
    }

    /**
     * プラグイン方式を無効にする,無効化に失敗した場合,例外を直接投げる
     */
    public static function deactivate()
    {
    }

    /**
     * プラグイン設定パネルの取得
     *
     * @param Form $form 設定パネル
     */
    public static function config(Form $form)
    {
        /** 分類名 */
        $name = new Text('word', null, 'Hello World', _t('何か言ってくれ。'));
        $form->addInput($name);
    }

    /**
     * 个人用户的設定パネル
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * プラグインの実装方法
     *
     * @access public
     * @return void
     */
    public static function render()
    {
        echo '<span class="message success">'
            . htmlspecialchars(Options::alloc()->plugin('HelloWorld')->word)
            . '</span>';
    }
}
