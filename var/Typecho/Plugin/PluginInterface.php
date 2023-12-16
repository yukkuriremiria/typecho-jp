<?php

namespace Typecho\Plugin;

use Typecho\Widget\Helper\Form;

/**
 * プラグインインターフェース
 *
 * @package Plugin
 * @abstract
 */
interface PluginInterface
{
    /**
     * プラグインメソッドの有効化,有効化に失敗した場合,例外を直接投げる
     *
     * @static
     * @access public
     * @return void
     */
    public static function activate();

    /**
     * プラグイン方式を無効にする,無効化に失敗した場合,例外を直接投げる
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate();

    /**
     * プラグイン設定パネルの取得
     *
     * @param Form $form 設定パネル
     */
    public static function config(Form $form);

    /**
     * 个人用户的設定パネル
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form);
}
