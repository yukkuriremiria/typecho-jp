<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * パスワード入力フォーム単一項目ヘルパークラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Password extends Element
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
        $input = new Layout('input', ['id' => $name . '-0-' . self::$uniqueId,
            'name' => $name, 'type' => 'password', 'class' => 'password']);
        $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
        $this->container($input);
        $this->inputs[] = $input;
        return $input;
    }

    /**
     * フォーム項目のデフォルト値を設定する
     *
     * @param mixed $value フォーム項目のデフォルト値
     */
    protected function inputValue($value)
    {
        $this->input->setAttribute('value', htmlspecialchars($value));
    }
}
