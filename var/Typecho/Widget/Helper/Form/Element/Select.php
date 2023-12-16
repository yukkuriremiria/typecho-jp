<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ドロップダウンリスト・ヘルパークラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Select extends Element
{
    /**
     * 選択値
     *
     * @var array
     */
    private $options = [];

    /**
     * 現在の入力を初期化する
     *
     * @param string|null $name フォーム要素名
     * @param array|null $options オプション
     * @return Layout|null
     */
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('select');
        $this->container($input->setAttribute('name', $name)
            ->setAttribute('id', $name . '-0-' . self::$uniqueId));
        $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
        $this->inputs[] = $input;

        foreach ($options as $value => $label) {
            $this->options[$value] = new Layout('option');
            $input->addItem($this->options[$value]->setAttribute('value', $value)->html($label));
        }

        return $input;
    }

    /**
     * フォーム要素の値を設定する
     *
     * @param mixed $value フォーム要素の値
     */
    protected function inputValue($value)
    {
        foreach ($this->options as $option) {
            $option->removeAttribute('selected');
        }

        if (isset($this->options[$value])) {
            $this->options[$value]->setAttribute('selected', 'true');
        }
    }
}
