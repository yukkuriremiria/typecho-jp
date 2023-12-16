<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * バーチャル・ドメイン・ヘルパー・クラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Fake extends Element
{
    /**
     * コンストラクタ
     *
     * @param string $name フォームエントリー名
     * @param mixed $value フォームのデフォルト
     */
    public function __construct(string $name, $value)
    {
        $this->name = $name;
        self::$uniqueId++;

        /** カスタム初期関数の実行 */
        $this->init();

        /** フォーム項目の初期化 */
        $this->input = $this->input($name);

        /** フォーム値の初期化 */
        if (null !== $value) {
            $this->value($value);
        }
    }

    /**
     * 現在の入力を初期化する
     *
     * @param string|null $name フォーム要素名
     * @param array|null $options オプション
     * @return Layout|null
     */
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('input');
        $this->inputs[] = $input;
        return $input;
    }

    /**
     * フォーム項目のデフォルト値を設定する
     *
     * @param mixed $value フォーム入力のデフォルト
     */
    protected function inputValue($value)
    {
        $this->input->setAttribute('value', $value);
    }
}
