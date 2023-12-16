<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 送信ボタンフォーム項目ヘルパークラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Submit extends Element
{
    /**
     * 現在の入力を初期化する
     *
     * @param string|null $name フォーム要素名
     * @param array|null $options オプション
     * @return Layout|null
     */
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $this->setAttribute('class', 'typecho-option typecho-option-submit');
        $input = new Layout('button', ['type' => 'submit']);
        $this->container($input);
        $this->inputs[] = $input;

        return $input;
    }

    /**
     * フォーム要素の値を設定する
     *
     * @param mixed $value フォーム要素の値
     */
    protected function inputValue($value)
    {
        $this->input->html($value);
    }
}
