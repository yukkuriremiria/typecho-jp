<?php

namespace Typecho\Router;

/**
 * ルーター・パーサー
 *
 * @category typecho
 * @package Router
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Parser
{
    /**
     * デフォルト・マッチ・テーブル
     *
     * @access private
     * @var array
     */
    private $defaultRegex;

    /**
     * ルーターマッピングテーブル
     *
     * @access private
     * @var array
     */
    private $routingTable;

    /**
     * パラメータ表
     *
     * @access private
     * @var array
     */
    private $params;

    /**
     * ルーティングテーブルの設定
     *
     * @access public
     * @param array $routingTable ルーターマッピングテーブル
     */
    public function __construct(array $routingTable)
    {
        $this->routingTable = $routingTable;

        $this->defaultRegex = [
            'string' => '(.%s)',
            'char' => '([^/]%s)',
            'digital' => '([0-9]%s)',
            'alpha' => '([_0-9a-zA-Z-]%s)',
            'alphaslash' => '([_0-9a-zA-Z-/]%s)',
            'split' => '((?:[^/]+/)%s[^/]+)',
        ];
    }

    /**
     * 正規文字列の部分一致と置換
     *
     * @access public
     * @param array $matches マッチングセクション
     * @return string
     */
    public function match(array $matches): string
    {
        $params = explode(' ', $matches[1]);
        $paramsNum = count($params);
        $this->params[] = $params[0];

        if (1 == $paramsNum) {
            return sprintf($this->defaultRegex['char'], '+');
        } elseif (2 == $paramsNum) {
            return sprintf($this->defaultRegex[$params[1]], '+');
        } elseif (3 == $paramsNum) {
            return sprintf($this->defaultRegex[$params[1]], $params[2] > 0 ? '{' . $params[2] . '}' : '*');
        } elseif (4 == $paramsNum) {
            return sprintf($this->defaultRegex[$params[1]], '{' . $params[2] . ',' . $params[3] . '}');
        }

        return $matches[0];
    }

    /**
     * ルーティングテーブルの解析
     *
     * @access public
     * @return array
     */
    public function parse(): array
    {
        $result = [];

        foreach ($this->routingTable as $key => $route) {
            $this->params = [];
            $route['regx'] = preg_replace_callback(
                "/%([^%]+)%/",
                [$this, 'match'],
                preg_quote(str_replace(['[', ']', ':'], ['%', '%', ' '], $route['url']))
            );

            /** スラッシュの処理 */
            $route['regx'] = rtrim($route['regx'], '/');
            $route['regx'] = '|^' . $route['regx'] . '[/]?$|';

            $route['format'] = preg_replace("/\[([^\]]+)\]/", "%s", $route['url']);
            $route['params'] = $this->params;

            $result[$key] = $route;
        }

        return $result;
    }
}
