<?php

namespace Typecho;

use Typecho\Plugin\Exception as PluginException;

/**
 * プラグイン処理クラス
 *
 * @category typecho
 * @package Plugin
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Plugin
{
    /**
     * 有効なすべてのプラグイン
     *
     * @var array
     */
    private static $plugin = [];

    /**
     * インスタンス化されたプラグイン・オブジェクト
     *
     * @var array
     */
    private static $instances;

    /**
     * 変数の一時保存
     *
     * @var array
     */
    private static $tmp = [];

    /**
     * ユニークハンドル
     *
     * @var string
     */
    private $handle;

    /**
     * アセンブリー
     *
     * @var string
     */
    private $component;

    /**
     * プラグインのシグナルをトリガーするかどうか
     *
     * @var boolean
     */
    private $signal;

    /**
     * プラグインの初期化
     *
     * @param string $handle プラグイン
     */
    public function __construct(string $handle)
    {
        if (defined('__TYPECHO_CLASS_ALIASES__')) {
            $alias = array_search('\\' . ltrim($handle, '\\'), __TYPECHO_CLASS_ALIASES__);
            $handle = $alias ?: $handle;
        }

        $this->handle = Common::nativeClassName($handle);
    }

    /**
     * プラグインの初期化
     *
     * @param array $plugins プラグイン列表
     */
    public static function init(array $plugins)
    {
        $plugins['activated'] = array_key_exists('activated', $plugins) ? $plugins['activated'] : [];
        $plugins['handles'] = array_key_exists('handles', $plugins) ? $plugins['handles'] : [];

        /** 変数の初期化 */
        self::$plugin = $plugins;
    }

    /**
     * ゲイン实例化プラグイン对象
     *
     * @param string $handle プラグイン
     * @return Plugin
     */
    public static function factory(string $handle): Plugin
    {
        return self::$instances[$handle] ?? (self::$instances[$handle] = new self($handle));
    }

    /**
     * 启用プラグイン
     *
     * @param string $pluginName プラグイン名称
     */
    public static function activate(string $pluginName)
    {
        self::$plugin['activated'][$pluginName] = self::$tmp;
        self::$tmp = [];
    }

    /**
     * 禁用プラグイン
     *
     * @param string $pluginName プラグイン名称
     */
    public static function deactivate(string $pluginName)
    {
        /** 関連するコールバック関数をすべて削除する */
        if (
            isset(self::$plugin['activated'][$pluginName]['handles'])
            && is_array(self::$plugin['activated'][$pluginName]['handles'])
        ) {
            foreach (self::$plugin['activated'][$pluginName]['handles'] as $handle => $handles) {
                self::$plugin['handles'][$handle] = self::pluginHandlesDiff(
                    empty(self::$plugin['handles'][$handle]) ? [] : self::$plugin['handles'][$handle],
                    empty($handles) ? [] : $handles
                );
                if (empty(self::$plugin['handles'][$handle])) {
                    unset(self::$plugin['handles'][$handle]);
                }
            }
        }

        /** 禁用当前プラグイン */
        unset(self::$plugin['activated'][$pluginName]);
    }

    /**
     * プラグインhandleを比較して検証する。
     *
     * @param array $pluginHandles
     * @param array $otherPluginHandles
     * @return array
     */
    private static function pluginHandlesDiff(array $pluginHandles, array $otherPluginHandles): array
    {
        foreach ($otherPluginHandles as $handle) {
            while (false !== ($index = array_search($handle, $pluginHandles))) {
                unset($pluginHandles[$index]);
            }
        }

        return $pluginHandles;
    }

    /**
     * 导出当前プラグイン设置
     *
     * @return array
     */
    public static function export(): array
    {
        return self::$plugin;
    }

    /**
     * ゲインプラグイン文件的头信息
     *
     * @param string $pluginFile プラグイン文件路径
     * @return array
     */
    public static function parseInfo(string $pluginFile): array
    {
        $tokens = token_get_all(file_get_contents($pluginFile));
        $isDoc = false;
        $isFunction = false;
        $isClass = false;
        $isInClass = false;
        $isInFunction = false;
        $isDefined = false;
        $current = null;

        /** 初期情報 */
        $info = [
            'description' => '',
            'title' => '',
            'author' => '',
            'homepage' => '',
            'version' => '',
            'since' => '',
            'activate' => false,
            'deactivate' => false,
            'config' => false,
            'personalConfig' => false
        ];

        $map = [
            'package' => 'title',
            'author' => 'author',
            'link' => 'homepage',
            'since' => 'since',
            'version' => 'version'
        ];

        foreach ($tokens as $token) {
            /** ゲインdoc comment */
            if (!$isDoc && is_array($token) && T_DOC_COMMENT == $token[0]) {

                /** 分岐ライン読み出し */
                $described = false;
                $lines = preg_split("(\r|\n)", $token[1]);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && '*' == $line[0]) {
                        $line = trim(substr($line, 1));
                        if (!$described && !empty($line) && '@' == $line[0]) {
                            $described = true;
                        }

                        if (!$described && !empty($line)) {
                            $info['description'] .= $line . "\n";
                        } elseif ($described && !empty($line) && '@' == $line[0]) {
                            $info['description'] = trim($info['description']);
                            $line = trim(substr($line, 1));
                            $args = explode(' ', $line);
                            $key = array_shift($args);

                            if (isset($map[$key])) {
                                $info[$map[$key]] = trim(implode(' ', $args));
                            }
                        }
                    }
                }

                $isDoc = true;
            }

            if (is_array($token)) {
                switch ($token[0]) {
                    case T_FUNCTION:
                        $isFunction = true;
                        break;
                    case T_IMPLEMENTS:
                        $isClass = true;
                        break;
                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;
                    case T_STRING:
                        $string = strtolower($token[1]);
                        switch ($string) {
                            case 'typecho_plugin_interface':
                            case 'plugininterface':
                                $isInClass = $isClass;
                                break;
                            case 'activate':
                            case 'deactivate':
                            case 'config':
                            case 'personalconfig':
                                if ($isFunction) {
                                    $current = ('personalconfig' == $string ? 'personalConfig' : $string);
                                }
                                break;
                            default:
                                if (!empty($current) && $isInFunction && $isInClass) {
                                    $info[$current] = true;
                                }
                                break;
                        }
                        break;
                    default:
                        if (!empty($current) && $isInFunction && $isInClass) {
                            $info[$current] = true;
                        }
                        break;
                }
            } else {
                $token = strtolower($token);
                switch ($token) {
                    case '{':
                        if ($isDefined) {
                            $isInFunction = true;
                        }
                        break;
                    case '(':
                        if ($isFunction && !$isDefined) {
                            $isDefined = true;
                        }
                        break;
                    case '}':
                    case ';':
                        $isDefined = false;
                        $isFunction = false;
                        $isInFunction = false;
                        $current = null;
                        break;
                    default:
                        if (!empty($current) && $isInFunction && $isInClass) {
                            $info[$current] = true;
                        }
                        break;
                }
            }
        }

        return $info;
    }

    /**
     * ゲインプラグイン路径和类名
     * 戻り値は配列
     * 第一项为プラグイン路径,番目の項目はクラス名です。
     *
     * @param string $pluginName プラグイン名
     * @param string $path プラグイン目录
     * @return array
     * @throws PluginException
     */
    public static function portal(string $pluginName, string $path): array
    {
        switch (true) {
            case file_exists($pluginFileName = $path . '/' . $pluginName . '/Plugin.php'):
                $className = "\\" . PLUGIN_NAMESPACE . "\\{$pluginName}\\Plugin";
                break;
            case file_exists($pluginFileName = $path . '/' . $pluginName . '.php'):
                $className = "\\" . PLUGIN_NAMESPACE . "\\" . $pluginName;
                break;
            default:
                throw new PluginException('Missing Plugin ' . $pluginName, 404);
        }

        return [$pluginFileName, $className];
    }

    /**
     * バージョン依存の検出
     *
     * @param string|null $version プラグイン版本
     * @return boolean
     */
    public static function checkDependence(?string $version): bool
    {
        //検出ルールがない場合,スイープ
        if (empty($version)) {
            return true;
        }

        return version_compare(Common::VERSION, $version, '>=');
    }

    /**
     * 判断プラグイン是否存在
     *
     * @param string $pluginName プラグイン名称
     * @return mixed
     */
    public static function exists(string $pluginName)
    {
        return array_key_exists($pluginName, self::$plugin['activated']);
    }

    /**
     * プラグイン调用后的フリップフロップ
     *
     * @param boolean|null $signal フリップフロップ
     * @return Plugin
     */
    public function trigger(?bool &$signal): Plugin
    {
        $signal = false;
        $this->signal = &$signal;
        return $this;
    }

    /**
     * 通过魔术函数设置当前アセンブリー位置
     *
     * @param string $component 当前アセンブリー
     * @return Plugin
     */
    public function __get(string $component)
    {
        $this->component = $component;
        return $this;
    }

    /**
     * コールバック関数の設定
     *
     * @param string $component 当前アセンブリー
     * @param callable $value コールバック関数
     */
    public function __set(string $component, callable $value)
    {
        $weight = 0;

        if (strpos($component, '_') > 0) {
            $parts = explode('_', $component, 2);
            [$component, $weight] = $parts;
            $weight = intval($weight) - 10;
        }

        $component = $this->handle . ':' . $component;

        if (!isset(self::$plugin['handles'][$component])) {
            self::$plugin['handles'][$component] = [];
        }

        if (!isset(self::$tmp['handles'][$component])) {
            self::$tmp['handles'][$component] = [];
        }

        foreach (self::$plugin['handles'][$component] as $key => $val) {
            $key = floatval($key);

            if ($weight > $key) {
                break;
            } elseif ($weight == $key) {
                $weight += 0.001;
            }
        }

        self::$plugin['handles'][$component][strval($weight)] = $value;
        self::$tmp['handles'][$component][] = $value;

        ksort(self::$plugin['handles'][$component], SORT_NUMERIC);
    }

    /**
     * コールバックハンドラ
     *
     * @param string $component 当前アセンブリー
     * @param array $args パラメーター
     * @return mixed
     */
    public function __call(string $component, array $args)
    {
        $component = $this->handle . ':' . $component;
        $last = count($args);
        $args[$last] = $last > 0 ? $args[0] : false;

        if (isset(self::$plugin['handles'][$component])) {
            $args[$last] = null;
            $this->signal = true;
            foreach (self::$plugin['handles'][$component] as $callback) {
                $args[$last] = call_user_func_array($callback, $args);
            }
        }

        return $args[$last];
    }
}
