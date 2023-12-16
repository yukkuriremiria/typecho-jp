<?php

namespace Typecho\Widget\Helper\Form\Element;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 隠しドメイン・ヘルパー・クラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Hidden extends Element
{
    /**
     * カスタム初期機能
     *
     * @return void
     */
    public function init()
    {
        /** この旅を隠す */
        $this->setAttribute('style', 'display:none');
    }

    /**
     * 現在の入力を初期化する
     *
     * @access public
     * @param string|null $name フォーム要素名
     * @param array|null $options オプション
     * @return Layout|null
     */
    public function input(?string $name = null, ?array $options = null): ?Layout
    {
        $input = new Layout('input', ['name' => $name, 'type' => 'hidden']);
        $this->container($input);
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
        $this->input->setAttribute('value', htmlspecialchars($value));
    }
}
