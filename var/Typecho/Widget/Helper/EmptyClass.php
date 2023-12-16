<?php

namespace Typecho\Widget\Helper;

/**
 * widgetヘルパー,空のオブジェクト・メソッドの処理
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class EmptyClass
{
    /**
     * シングルインスタンスハンドル
     *
     * @access private
     * @var EmptyClass
     */
    private static $instance = null;

    /**
     * 获取シングルインスタンスハンドル
     *
     * @access public
     * @return EmptyClass
     */
    public static function getInstance(): EmptyClass
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * すべてのメソッドリクエストは直接
     *
     * @access public
     * @param string $name メソッド名
     * @param array $args パラメータリスト
     * @return void
     */
    public function __call(string $name, array $args)
    {
    }
}
