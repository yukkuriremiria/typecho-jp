<?php

namespace Typecho\Widget\Helper;

/**
 * HTMLレイアウト・ヘルパー
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Layout
{
    /**
     * 要素リスト
     *
     * @access private
     * @var array
     */
    private $items = [];

    /**
     * フォーム・プロパティのリスト
     *
     * @access private
     * @var array
     */
    private $attributes = [];

    /**
     * ラベル名
     *
     * @access private
     * @var string
     */
    private $tagName = 'div';

    /**
     * 自動開閉式か
     *
     * @access private
     * @var boolean
     */
    private $close = false;

    /**
     * 強制自動閉鎖の有無
     *
     * @access private
     * @var boolean
     */
    private $forceClose = null;

    /**
     * 内部データ
     *
     * @access private
     * @var string
     */
    private $html;

    /**
     * 親ノード
     *
     * @access private
     * @var Layout
     */
    private $parent;

    /**
     * コンストラクタ,设置ラベル名
     *
     * @param string $tagName ラベル名
     * @param array|null $attributes 物件リスト
     *
     */
    public function __construct(string $tagName = 'div', ?array $attributes = null)
    {
        $this->setTagName($tagName);

        if (!empty($attributes)) {
            foreach ($attributes as $attributeName => $attributeValue) {
                $this->setAttribute($attributeName, (string)$attributeValue);
            }
        }
    }

    /**
     * フォームのプロパティを設定する
     *
     * @param string $attributeName プロパティ名
     * @param mixed $attributeValue 属性値
     * @return $this
     */
    public function setAttribute(string $attributeName, $attributeValue): Layout
    {
        $this->attributes[$attributeName] = (string) $attributeValue;
        return $this;
    }

    /**
     * 要素の削除
     *
     * @param Layout $item 要素別
     * @return $this
     */
    public function removeItem(Layout $item): Layout
    {
        unset($this->items[array_search($item, $this->items)]);
        return $this;
    }

    /**
     * getItems
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * getTagName
     *
     * @return string
     */
    public function getTagName(): string
    {
        return $this->tagName;
    }

    /**
     * タグ名の設定
     *
     * @param string $tagName タグ名
     */
    public function setTagName(string $tagName)
    {
        $this->tagName = $tagName;
    }

    /**
     * プロパティを削除する
     *
     * @param string $attributeName プロパティ名
     * @return $this
     */
    public function removeAttribute(string $attributeName): Layout
    {
        if (isset($this->attributes[$attributeName])) {
            unset($this->attributes[$attributeName]);
        }

        return $this;
    }

    /**
     * プロパティの取得
     *
     * @access public
     *
     * @param string $attributeName プロパティ名
     * @return string|null
     */
    public function getAttribute(string $attributeName): ?string
    {
        return $this->attributes[$attributeName] ?? null;
    }

    /**
     * 设置自動開閉式か
     *
     * @param boolean $close 自動開閉式か
     * @return $this
     */
    public function setClose(bool $close): Layout
    {
        $this->forceClose = $close;
        return $this;
    }

    /**
     * 获取親ノード
     *
     * @return Layout
     */
    public function getParent(): Layout
    {
        return $this->parent;
    }

    /**
     * 设置親ノード
     *
     * @param Layout $parent 親ノード
     * @return $this
     */
    public function setParent(Layout $parent): Layout
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * 增加到某布局要素別集合中
     *
     * @param Layout $parent レイアウト・オブジェクト
     * @return $this
     */
    public function appendTo(Layout $parent): Layout
    {
        $parent->addItem($this);
        return $this;
    }

    /**
     * 增加要素別
     *
     * @param Layout $item 要素別
     * @return $this
     */
    public function addItem(Layout $item): Layout
    {
        $item->setParent($this);
        $this->items[] = $item;
        return $this;
    }

    /**
     * プロパティの取得
     *
     * @param string $name プロパティ名
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * プロパティの設定
     *
     * @param string $name プロパティ名
     * @param string $value 属性値
     */
    public function __set(string $name, string $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * 输出所有要素別
     */
    public function render()
    {
        if (empty($this->items) && empty($this->html)) {
            $this->close = true;
        }

        if (null !== $this->forceClose) {
            $this->close = $this->forceClose;
        }

        $this->start();
        $this->html();
        $this->end();
    }

    /**
     * スタートタブ
     */
    public function start()
    {
        /** 出力ラベル */
        echo $this->tagName ? "<{$this->tagName}" : null;

        /** 出力プロパティ */
        foreach ($this->attributes as $attributeName => $attributeValue) {
            echo " {$attributeName}=\"{$attributeValue}\"";
        }

        /** セルフクロージング対応 */
        if (!$this->close && $this->tagName) {
            echo ">\n";
        }
    }

    /**
     * 设置内部データ
     *
     * @param string|null $html 内部データ
     * @return void|$this
     */
    public function html(?string $html = null)
    {
        if (null === $html) {
            if (empty($this->html)) {
                foreach ($this->items as $item) {
                    $item->render();
                }
            } else {
                echo $this->html;
            }
        } else {
            $this->html = $html;
            return $this;
        }
    }

    /**
     * 終了タグ
     *
     * @return void
     */
    public function end()
    {
        if ($this->tagName) {
            echo $this->close ? " />\n" : "</{$this->tagName}>\n";
        }
    }
}
