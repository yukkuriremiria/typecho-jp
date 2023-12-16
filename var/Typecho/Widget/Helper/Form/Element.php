<?php

namespace Typecho\Widget\Helper\Form;

use Typecho\Widget\Helper\Layout;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * フォーム要素抽象クラス
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
abstract class Element extends Layout
{
    /**
     * シングルトン・ユニークid
     *
     * @access protected
     * @var integer
     */
    protected static $uniqueId = 0;

    /**
     * フォーム要素コンテナ
     *
     * @access public
     * @var Layout
     */
    public $container;

    /**
     * 入力フィールド
     *
     * @access public
     * @var Layout
     */
    public $input;

    /**
     * inputs
     *
     * @var array
     * @access public
     */
    public $inputs = [];

    /**
     * フォーム名
     *
     * @access public
     * @var Layout
     */
    public $label;

    /**
     * フォームバリデータ
     *
     * @access public
     * @var array
     */
    public $rules = [];

    /**
     * フォーム名
     *
     * @access public
     * @var string
     */
    public $name;

    /**
     * フォーム値
     *
     * @access public
     * @var mixed
     */
    public $value;

    /**
     * フォームの説明
     *
     * @access private
     * @var string
     */
    protected $description;

    /**
     * フォームメッセージ
     *
     * @access protected
     * @var string
     */
    protected $message;

    /**
     * マルチライン入力
     *
     * @access public
     * @var array()
     */
    protected $multiline = [];

    /**
     * コンストラクタ
     *
     * @param string|null $name フォームエントリー名
     * @param array|null $options オプション
     * @param mixed $value フォームのデフォルト
     * @param string|null $label フォーム名
     * @param string|null $description フォームの説明
     * @return void
     */
    public function __construct(
        ?string $name = null,
        ?array $options = null,
        $value = null,
        ?string $label = null,
        ?string $description = null
    ) {
        /** 確立html要素別,そしてclass */
        parent::__construct(
            'ul',
            ['class' => 'typecho-option', 'id' => 'typecho-option-item-' . $name . '-' . self::$uniqueId]
        );

        $this->name = $name;
        self::$uniqueId++;

        /** カスタム初期関数の実行 */
        $this->init();

        /** 初始化フォーム名 */
        if (null !== $label) {
            $this->label($label);
        }

        /** フォーム項目の初期化 */
        $this->input = $this->input($name, $options);

        /** 初始化フォーム値 */
        if (null !== $value) {
            $this->value($value);
        }

        /** 初始化フォームの説明 */
        if (null !== $description) {
            $this->description($description);
        }
    }

    /**
     * カスタム初期機能
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * 確立フォーム名
     *
     * @param string $value タイトル文字列
     * @return $this
     */
    public function label(string $value): Element
    {
        /** 確立标题要素別 */
        if (empty($this->label)) {
            $this->label = new Layout('label', ['class' => 'typecho-label']);
            $this->container($this->label);
        }

        $this->label->html($value);
        return $this;
    }

    /**
     * 在容器里增加要素別
     *
     * @param Layout $item 表单要素別
     * @return $this
     */
    public function container(Layout $item): Element
    {
        /** 確立表单容器 */
        if (empty($this->container)) {
            $this->container = new Layout('li');
            $this->addItem($this->container);
        }

        $this->container->addItem($item);
        return $this;
    }

    /**
     * 現在の入力を初期化する
     *
     * @param string|null $name 表单要素別名称
     * @param array|null $options オプション
     * @return Layout|null
     */
    abstract public function input(?string $name = null, ?array $options = null): ?Layout;

    /**
     * 设置表单要素別值
     *
     * @param mixed $value 表单要素別值
     * @return Element
     */
    public function value($value): Element
    {
        $this->value = $value;
        $this->inputValue($value ?? '');
        return $this;
    }

    /**
     * 設定説明情報
     *
     * @param string $description 記述的情報
     * @return Element
     */
    public function description(string $description): Element
    {
        /** 確立描述要素別 */
        if (empty($this->description)) {
            $this->description = new Layout('p', ['class' => 'description']);
            $this->container($this->description);
        }

        $this->description->html($description);
        return $this;
    }

    /**
     * アラートメッセージの設定
     *
     * @param string $message アラート
     * @return Element
     */
    public function message(string $message): Element
    {
        if (empty($this->message)) {
            $this->message = new Layout('p', ['class' => 'message error']);
            $this->container($this->message);
        }

        $this->message->html($message);
        return $this;
    }

    /**
     * マルチライン出力モード
     *
     * @return Layout
     */
    public function multiline(): Layout
    {
        $item = new Layout('span');
        $this->multiline[] = $item;
        return $item;
    }

    /**
     * マルチライン出力モード
     *
     * @return Element
     */
    public function multiMode(): Element
    {
        foreach ($this->multiline as $item) {
            $item->setAttribute('class', 'multiline');
        }
        return $this;
    }

    /**
     * バリデータの追加
     *
     * @param mixed ...$rules
     * @return $this
     */
    public function addRule(...$rules): Element
    {
        $this->rules[] = $rules;
        return $this;
    }

    /**
     * すべての入力項目に対してプロパティ値を一律に設定する
     *
     * @param string $attributeName
     * @param mixed $attributeValue
     */
    public function setInputsAttribute(string $attributeName, $attributeValue)
    {
        foreach ($this->inputs as $input) {
            $input->setAttribute($attributeName, $attributeValue);
        }
    }

    /**
     * 设置表单要素別值
     *
     * @param mixed $value 表单要素別值
     */
    abstract protected function inputValue($value);

    /**
     * filterValue
     *
     * @param string $value
     * @return string
     */
    protected function filterValue(string $value): string
    {
        if (preg_match_all('/[_0-9a-z-]+/i', $value, $matches)) {
            return implode('-', $matches[0]);
        }

        return '';
    }
}
