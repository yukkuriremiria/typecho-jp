<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 複数行テキスト・フィールド・ヘルパー・クラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Textarea extends Element
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
        $input = new Layout('textarea', ['id' => $name . '-0-' . self::$uniqueId, 'name' => $name]);
        $this->label->setAttribute('for', $name . '-0-' . self::$uniqueId);
        $this->container($input->setClose(false));
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
        $this->input->html(htmlspecialchars($value));
    }
}
