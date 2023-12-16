<?php

namespace Typecho;

use Typecho\Router\Parser;
use Typecho\Router\Exception as RouterException;

/**
 * Typechoコンポーネント基本クラス
 *
 * @package Router
 */
class Router
{
    /**
     * 現在のルート名
     *
     * @access public
     * @var string
     */
    public static $current;

    /**
     * すでに解決済みのルーティング・テーブル設定
     *
     * @access private
     * @var mixed
     */
    private static $routingTable = [];

    /**
     * パースパス
     *
     * @access public
     *
     * @param string|null $pathInfo フルパス
     * @param mixed $parameter 入力パラメータ
     *
     * @return false|Widget
     * @throws \Exception
     */
    public static function match(?string $pathInfo, $parameter = null)
    {
        foreach (self::$routingTable as $key => $route) {
            if (preg_match($route['regx'], $pathInfo, $matches)) {
                self::$current = $key;

                try {
                    /** 負荷パラメータ */
                    $params = null;

                    if (!empty($route['params'])) {
                        unset($matches[0]);
                        $params = array_combine($route['params'], $matches);
                    }

                    return Widget::widget($route['widget'], $parameter, $params);

                } catch (\Exception $e) {
                    if (404 == $e->getCode()) {
                        Widget::destroy($route['widget']);
                        continue;
                    }

                    throw $e;
                }
            }
        }

        return false;
    }

    /**
     * ルート配信機能
     *
     * @throws RouterException|\Exception
     */
    public static function dispatch()
    {
        /** ゲインPATHINFO */
        $pathInfo = Request::getInstance()->getPathInfo();

        foreach (self::$routingTable as $key => $route) {
            if (preg_match($route['regx'], $pathInfo, $matches)) {
                self::$current = $key;

                try {
                    /** 負荷パラメータ */
                    $params = null;

                    if (!empty($route['params'])) {
                        unset($matches[0]);
                        $params = array_combine($route['params'], $matches);
                    }

                    $widget = Widget::widget($route['widget'], null, $params);

                    if (isset($route['action'])) {
                        $widget->{$route['action']}();
                    }

                    return;

                } catch (\Exception $e) {
                    if (404 == $e->getCode()) {
                        Widget::destroy($route['widget']);
                        continue;
                    }

                    throw $e;
                }
            }
        }

        /** ロード・ルーティング例外サポート */
        throw new RouterException("Path '{$pathInfo}' not found", 404);
    }

    /**
     * ルーティング逆解像度関数
     *
     * @param string $name ルーティング設定テーブル名
     * @param array|null $value ルーティング・パディング
     * @param string|null $prefix 最終合成パスの接頭辞
     *
     * @return string
     */
    public static function url(string $name, ?array $value = null, ?string $prefix = null): string
    {
        $route = self::$routingTable[$name];

        //配列キーの交換
        $pattern = [];
        foreach ($route['params'] as $row) {
            $pattern[$row] = $value[$row] ?? '{' . $row . '}';
        }

        return Common::url(vsprintf($route['format'], $pattern), $prefix);
    }

    /**
     * ルーターのデフォルト設定の設定
     *
     * @access public
     *
     * @param mixed $routes 構成情報
     *
     * @return void
     */
    public static function setRoutes($routes)
    {
        if (isset($routes[0])) {
            self::$routingTable = $routes[0];
        } else {
            /** ルート設定の解析 */
            $parser = new Parser($routes);
            self::$routingTable = $parser->parse();
        }
    }

    /**
     * ゲイン路由信息
     *
     * @param string $routeName ルート名
     *
     * @static
     * @access public
     * @return mixed
     */
    public static function get(string $routeName)
    {
        return self::$routingTable[$routeName] ?? null;
    }
}
