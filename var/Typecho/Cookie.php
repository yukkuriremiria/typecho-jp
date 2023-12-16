<?php

namespace Typecho;

/**
 * cookieアジュバント
 *
 * @author qining
 * @category typecho
 * @package Cookie
 */
class Cookie
{
    /**
     * 接頭辞
     *
     * @var string
     * @access private
     */
    private static $prefix = '';

    /**
     * トレール
     *
     * @var string
     * @access private
     */
    private static $path = '/';

    /**
     * @var string
     * @access private
     */
    private static $domain = '';

    /**
     * @var bool
     * @access private
     */
    private static $secure = false;

    /**
     * @var bool
     * @access private
     */
    private static $httponly = false;

    /**
     * 获取接頭辞
     *
     * @access public
     * @return string
     */
    public static function getPrefix(): string
    {
        return self::$prefix;
    }

    /**
     * 设置接頭辞
     *
     * @param string $url
     *
     * @access public
     * @return void
     */
    public static function setPrefix(string $url)
    {
        self::$prefix = md5($url);
        $parsed = parse_url($url);

        self::$domain = $parsed['host'];
        /** 在トレール后面强制加上斜杠 */
        self::$path = empty($parsed['path']) ? '/' : Common::url(null, $parsed['path']);
    }

    /**
     * ディレクトリを取得
     *
     * @access public
     * @return string
     */
    public static function getPath(): string
    {
        return self::$path;
    }

    /**
     * @access public
     * @return string
     */
    public static function getDomain(): string
    {
        return self::$domain;
    }

    /**
     * @access public
     * @return bool
     */
    public static function getSecure(): bool
    {
        return self::$secure ?: false;
    }

    /**
     * 追加オプションの設定
     *
     * @param array $options
     * @return void
     */
    public static function setOptions(array $options)
    {
        self::$domain = $options['domain'] ?: self::$domain;
        self::$secure = $options['secure'] ? (bool) $options['secure'] : false;
        self::$httponly = $options['httponly'] ? (bool) $options['httponly'] : false;
    }

    /**
     * 指定されたCOOKIE(価値がある
     *
     * @param string $key 指定パラメータ
     * @param string|null $default デフォルト・パラメーター
     * @return mixed
     */
    public static function get(string $key, ?string $default = null)
    {
        $key = self::$prefix . $key;
        $value = $_COOKIE[$key] ?? $default;
        return is_array($value) ? $default : $value;
    }

    /**
     * 指定されたCOOKIE(価値がある
     *
     * @param string $key 指定パラメータ
     * @param mixed $value 设置的(価値がある
     * @param integer $expire 有効期限,デフォルト0,セッション終了時刻を示す。
     */
    public static function set(string $key, $value, int $expire = 0)
    {
        $key = self::$prefix . $key;
        $_COOKIE[$key] = $value;
        Response::getInstance()->setCookie($key, $value, $expire, self::$path, self::$domain, self::$secure, self::$httponly);
    }

    /**
     * 指定されたCOOKIE(価値がある
     *
     * @param string $key 指定パラメータ
     */
    public static function delete(string $key)
    {
        $key = self::$prefix . $key;
        if (!isset($_COOKIE[$key])) {
            return;
        }

        Response::getInstance()->setCookie($key, '', -1, self::$path, self::$domain, self::$secure, self::$httponly);
        unset($_COOKIE[$key]);
    }
}

