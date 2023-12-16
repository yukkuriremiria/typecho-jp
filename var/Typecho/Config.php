<?php

namespace Typecho;

/**
 * 構成管理クラス
 *
 * @category typecho
 * @package Config
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Config implements \Iterator, \ArrayAccess
{
    /**
     * 現在の構成
     *
     * @access private
     * @var array
     */
    private $currentConfig = [];

    /**
     * 实例化一个現在の構成
     *
     * @access public
     * @param array|string|null $config 構成リスト
     */
    public function __construct($config = [])
    {
        /** 初期化パラメータ */
        $this->setDefault($config);
    }

    /**
     * 工厂模式实例化一个現在の構成
     *
     * @access public
     *
     * @param array|string|null $config 構成リスト
     *
     * @return Config
     */
    public static function factory($config = []): Config
    {
        return new self($config);
    }

    /**
     * デフォルト・コンフィギュレーションの設定
     *
     * @access public
     *
     * @param mixed $config 構成情報
     * @param boolean $replace すでに存在する情報を置き換えるかどうか
     *
     * @return void
     */
    public function setDefault($config, bool $replace = false)
    {
        if (empty($config)) {
            return;
        }

        /** 初期化パラメータ */
        if (is_string($config)) {
            parse_str($config, $params);
        } else {
            $params = $config;
        }

        /** デフォルトパラメーターの設定 */
        foreach ($params as $name => $value) {
            if ($replace || !array_key_exists($name, $this->currentConfig)) {
                $this->currentConfig[$name] = $value;
            }
        }
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->currentConfig);
    }

    /**
     * ポインタをリセットする
     *
     * @access public
     * @return void
     */
    public function rewind(): void
    {
        reset($this->currentConfig);
    }

    /**
     * 現在値を返す
     *
     * @access public
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->currentConfig);
    }

    /**
     * ポインターが1つ後ろに移動する。
     *
     * @access public
     * @return void
     */
    public function next(): void
    {
        next($this->currentConfig);
    }

    /**
     * 現在のポインタを取得
     *
     * @access public
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->currentConfig);
    }

    /**
     * 現在値が末尾に達していることを確認する
     *
     * @access public
     * @return boolean
     */
    public function valid(): bool
    {
        return false !== $this->current();
    }

    /**
     * 設定値を取得するマジック関数
     *
     * @access public
     * @param string $name 構成名
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->offsetGet($name);
    }

    /**
     * マジック関数は設定値を設定する
     *
     * @access public
     * @param string $name 構成名
     * @param mixed $value 設定値
     * @return void
     */
    public function __set(string $name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * 直接输出默认設定値
     *
     * @access public
     * @param string $name 構成名
     * @param array|null $args パラメーター
     * @return void
     */
    public function __call(string $name, ?array $args)
    {
        echo $this->currentConfig[$name];
    }

    /**
     * 判断現在の構成值是否存在
     *
     * @access public
     * @param string $name 構成名
     * @return boolean
     */
    public function __isSet(string $name): bool
    {
        return $this->offsetExists($name);
    }

    /**
     * 魔法のメソッド,打印現在の構成数组
     *
     * @access public
     * @return string
     */
    public function __toString(): string
    {
        return serialize($this->currentConfig);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->currentConfig;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->currentConfig[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->currentConfig[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->currentConfig[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->currentConfig[$offset]);
    }
}
