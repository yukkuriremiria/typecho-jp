<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * テキスト入力フォーム リストヘルパー
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Text extends Element
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
            'name' => $name, 'type' => 'text', 'class' => 'text']);
        $this->container($input);
        $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
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
        if (isset($value)) {
            $this->input->setAttribute('value', htmlspecialchars($value));
        } else {
            $this->input->removeAttribute('value');
        }
    }
}
