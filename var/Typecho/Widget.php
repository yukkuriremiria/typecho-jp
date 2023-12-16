<?php

namespace Typecho;

use Typecho\Widget\Helper\EmptyClass;
use Typecho\Widget\Request as WidgetRequest;
use Typecho\Widget\Response as WidgetResponse;
use Typecho\Widget\Terminal;

/**
 * Typechoコンポーネント基本クラス
 *
 * @property $sequence
 * @property $length
 * @property-read $request
 * @property-read $response
 * @property-read $parameter
 */
abstract class Widget
{
    /**
     * widgetオブジェクトプール
     *
     * @var array
     */
    private static $widgetPool = [];

    /**
     * widgetニックネーム
     *
     * @var array
     */
    private static $widgetAlias = [];

    /**
     * requestボーイフレンド
     *
     * @var WidgetRequest
     */
    protected $request;

    /**
     * responseボーイフレンド
     *
     * @var WidgetResponse
     */
    protected $response;

    /**
     * データスタック
     *
     * @var array
     */
    protected $stack = [];

    /**
     * 現在のキュー・ポインタの順序値,をとおして1開始
     *
     * @var integer
     */
    protected $sequence = 0;

    /**
     * キューの長さ
     *
     * @var integer
     */
    protected $length = 0;

    /**
     * configボーイフレンド
     *
     * @var Config
     */
    protected $parameter;

    /**
     * データスタック每一行
     *
     * @var array
     */
    protected $row = [];

    /**
     * コンストラクタ,コンポーネントの初期化
     *
     * @param WidgetRequest $request requestボーイフレンド
     * @param WidgetResponse $response responseボーイフレンド
     * @param mixed $params パラメータリスト
     */
    public function __construct(WidgetRequest $request, WidgetResponse $response, $params = null)
    {
        //设置函数内部ボーイフレンド
        $this->request = $request;
        $this->response = $response;
        $this->parameter = Config::factory($params);

        $this->init();
    }

    /**
     * init method
     */
    protected function init()
    {
    }

    /**
     * widgetニックネーム
     *
     * @param string $widgetClass
     * @param string $aliasClass
     */
    public static function alias(string $widgetClass, string $aliasClass)
    {
        self::$widgetAlias[$widgetClass] = $aliasClass;
    }

    /**
     * 工場方式,クラスのスタティックをリストに入れる
     *
     * @param class-string $alias 组件ニックネーム
     * @param mixed $params 渡されたパラメータ
     * @param mixed $request フロントエンドパラメータ
     * @param bool|callable $disableSandboxOrCallback 戻す
     * @return Widget
     */
    public static function widget(
        string $alias,
        $params = null,
        $request = null,
        $disableSandboxOrCallback = true
    ): Widget {
        [$className] = explode('@', $alias);
        $key = Common::nativeClassName($alias);

        if (isset(self::$widgetAlias[$className])) {
            $className = self::$widgetAlias[$className];
        }

        $sandbox = false;

        if ($disableSandboxOrCallback === false || is_callable($disableSandboxOrCallback)) {
            $sandbox = true;
            Request::getInstance()->beginSandbox(new Config($request));
            Response::getInstance()->beginSandbox();
        }

        if ($sandbox || !isset(self::$widgetPool[$key])) {
            $requestObject = new WidgetRequest(Request::getInstance(), isset($request) ? new Config($request) : null);
            $responseObject = new WidgetResponse(Request::getInstance(), Response::getInstance());

            try {
                $widget = new $className($requestObject, $responseObject, $params);
                $widget->execute();

                if ($sandbox && is_callable($disableSandboxOrCallback)) {
                    call_user_func($disableSandboxOrCallback, $widget);
                }
            } catch (Terminal $e) {
                $widget = $widget ?? null;
            } finally {
                if ($sandbox) {
                    Response::getInstance()->endSandbox();
                    Request::getInstance()->endSandbox();

                    return $widget;
                }
            }

            self::$widgetPool[$key] = $widget;
        }

        return self::$widgetPool[$key];
    }

    /**
     * alloc widget instance
     *
     * @param mixed $params
     * @param mixed $request
     * @param bool|callable $disableSandboxOrCallback
     * @return $this
     */
    public static function alloc($params = null, $request = null, $disableSandboxOrCallback = true): Widget
    {
        return self::widget(static::class, $params, $request, $disableSandboxOrCallback);
    }

    /**
     * alloc widget instance with alias
     *
     * @param string|null $alias
     * @param mixed $params
     * @param mixed $request
     * @param bool|callable $disableSandboxOrCallback
     * @return $this
     */
    public static function allocWithAlias(
        ?string $alias,
        $params = null,
        $request = null,
        $disableSandboxOrCallback = true
    ): Widget {
        return self::widget(
            static::class . (isset($alias) ? '@' . $alias : ''),
            $params,
            $request,
            $disableSandboxOrCallback
        );
    }

    /**
     * コンポーネントのリリース
     *
     * @param string $alias コンポーネント名
     * @deprecated alias for destroy
     */
    public static function destory(string $alias)
    {
        self::destroy($alias);
    }

    /**
     * コンポーネントのリリース
     *
     * @param string|null $alias コンポーネント名
     */
    public static function destroy(?string $alias = null)
    {
        if (Common::nativeClassName(static::class) == 'Typecho_Widget') {
            if (isset($alias)) {
                unset(self::$widgetPool[$alias]);
            } else {
                self::$widgetPool = [];
            }
        } else {
            $alias = static::class . (isset($alias) ? '@' . $alias : '');
            unset(self::$widgetPool[$alias]);
        }
    }

    /**
     * execute function.
     */
    public function execute()
    {
    }

    /**
     * postイベントトリガー
     *
     * @param boolean $condition トリガー条件
     *
     * @return $this|EmptyClass
     */
    public function on(bool $condition)
    {
        if ($condition) {
            return $this;
        } else {
            return new EmptyClass();
        }
    }

    /**
     * クラス自体に値を代入する
     *
     * @param mixed $variable 変数名
     * @return $this
     */
    public function to(&$variable): Widget
    {
        return $variable = $this;
    }

    /**
     * パーススタック内のすべてのデータをフォーマットする
     *
     * @param string $format データ形式
     */
    public function parse(string $format)
    {
        while ($this->next()) {
            echo preg_replace_callback(
                "/\{([_a-z0-9]+)\}/i",
                function (array $matches) {
                    return $this->{$matches[1]};
                },
                $format
            );
        }
    }

    /**
     * スタックの各行の値を返す
     *
     * @return mixed
     */
    public function next()
    {
        $key = key($this->stack);

        if ($key !== null && isset($this->stack[$key])) {
            $this->row = current($this->stack);
            next($this->stack);
            $this->sequence++;
        } else {
            reset($this->stack);
            $this->sequence = 0;
            return false;
        }

        return $this->row;
    }

    /**
     * 各行の値をスタックに押し込む
     *
     * @param array $value 各行の値
     * @return mixed
     */
    public function push(array $value)
    {
        //行データを順番にセットする
        $this->row = $value;
        $this->length++;

        $this->stack[] = $value;
        return $value;
    }

    /**
     * 残差に基づく出力
     *
     * @param mixed ...$args
     */
    public function alt(...$args)
    {
        $num = count($args);
        $split = $this->sequence % $num;
        echo $args[(0 == $split ? $num : $split) - 1];
    }

    /**
     * スタックが空かどうかを返す
     *
     * @return boolean
     */
    public function have(): bool
    {
        return !empty($this->stack);
    }

    /**
     * マジック機能,他の関数をフックする
     *
     * @param string $name 関数名
     * @param array $args 関数パラメータ
     */
    public function __call(string $name, array $args)
    {
        $method = 'call' . ucfirst($name);
        self::pluginHandle()->trigger($plugged)->{$method}($this, $args);

        if (!$plugged) {
            echo $this->{$name};
        }
    }

    /**
     * 获取ボーイフレンド插件句柄
     *
     * @return Plugin
     */
    public static function pluginHandle(): Plugin
    {
        return Plugin::factory(static::class);
    }

    /**
     * マジック機能,内部変数の取得に使用
     *
     * @param string $name 変数名
     * @return mixed
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->row)) {
            return $this->row[$name];
        } else {
            $method = '___' . $name;

            if (method_exists($this, $method)) {
                return $this->$method();
            } else {
                $return = self::pluginHandle()->trigger($plugged)->{$method}($this);
                if ($plugged) {
                    return $return;
                }
            }
        }

        return null;
    }

    /**
     * 设定堆栈各行の値
     *
     * @param string $name に対応するキー値。
     * @param mixed $value 対応値
     */
    public function __set(string $name, $value)
    {
        $this->row[$name] = $value;
    }

    /**
     * スタック値が存在することを確認する
     *
     * @param string $name
     * @return boolean
     */
    public function __isSet(string $name)
    {
        return isset($this->row[$name]);
    }

    /**
     * 出力オーダー値
     *
     * @return int
     */
    public function ___sequence(): int
    {
        return $this->sequence;
    }

    /**
     * 出力データ長
     *
     * @return int
     */
    public function ___length(): int
    {
        return $this->length;
    }

    /**
     * @return WidgetRequest
     */
    public function ___request(): WidgetRequest
    {
        return $this->request;
    }

    /**
     * @return WidgetResponse
     */
    public function ___response(): WidgetResponse
    {
        return $this->response;
    }

    /**
     * @return Config
     */
    public function ___parameter(): Config
    {
        return $this->parameter;
    }
}
