<?php

namespace Typecho;

/**
 * サーバー・リクエスト処理クラス
 *
 * @package Request
 */
class Request
{
    /**
     * シングルインスタンスハンドル
     *
     * @access private
     * @var Request
     */
    private static $instance;

    /**
     * サンドボックス・パラメーター
     *
     * @access private
     * @var Config|null
     */
    private $sandbox;

    /**
     * 使用パラメータ
     *
     * @access private
     * @var Config|null
     */
    private $params;

    /**
     * ルート情報
     *
     * @access private
     * @var string
     */
    private $pathInfo = null;

    /**
     * requestUri
     *
     * @var string
     * @access private
     */
    private $requestUri = null;

    /**
     * requestRoot
     *
     * @var mixed
     * @access private
     */
    private $requestRoot = null;

    /**
     * ゲインbaseurl
     *
     * @var string
     * @access private
     */
    private $baseUrl = null;

    /**
     * クライアントip住所
     *
     * @access private
     * @var string
     */
    private $ip = null;

    /**
     * ドメイン名のプレフィックス
     *
     * @var string
     */
    private $urlPrefix = null;

    /**
     * ゲインシングルインスタンスハンドル
     *
     * @access public
     * @return Request
     */
    public static function getInstance(): Request
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 変数の初期化
     *
     * @return $this
     */
    public function beginSandbox(Config $sandbox): Request
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * @return $this
     */
    public function endSandbox(): Request
    {
        $this->sandbox = null;
        return $this;
    }

    /**
     * @param Config $params
     * @return $this
     */
    public function proxy(Config $params): Request
    {
        $this->params = $params;
        return $this;
    }

    /**
     * ゲイン实际転送パラメータ
     *
     * @param string $key 所定のパラメータ
     * @param mixed $default デフォルトパラメータ (default: NULL)
     * @param bool|null $exists detect exists
     * @return mixed
     */
    public function get(string $key, $default = null, ?bool &$exists = true)
    {
        $exists = true;
        $value = null;

        switch (true) {
            case isset($this->params) && isset($this->params[$key]):
                $value = $this->params[$key];
                break;
            case isset($this->sandbox):
                if (isset($this->sandbox[$key])) {
                    $value = $this->sandbox[$key];
                } else {
                    $exists = false;
                }
                break;
            case $key === '@json':
                $exists = false;
                if ($this->isJson()) {
                    $body = file_get_contents('php://input');

                    if (false !== $body) {
                        $exists = true;
                        $value = json_decode($body, true, 16);
                        $default = $default ?? $value;
                    }
                }
                break;
            case isset($_GET[$key]):
                $value = $_GET[$key];
                break;
            case isset($_POST[$key]):
                $value = $_POST[$key];
                break;
            default:
                $exists = false;
                break;
        }

        // reset params
        if (isset($this->params)) {
            $this->params = null;
        }

        if (isset($value)) {
            return is_array($default) == is_array($value) ? $value : $default;
        } else {
            return $default;
        }
    }

    /**
     * ゲイン实际転送パラメータ(magic)
     *
     * @param string $key 所定のパラメータ
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * パラメータが存在するかどうかを判断する
     *
     * @param string $key 所定のパラメータ
     * @return boolean
     */
    public function __isset(string $key)
    {
        $this->get($key, null, $exists);
        return $exists;
    }

    /**
     * ゲイン一个数组
     *
     * @param $key
     * @return array
     */
    public function getArray($key): array
    {
        $result = $this->get($key, [], $exists);

        if (!empty($result) || !$exists) {
            return $result;
        }

        return [$this->get($key)];
    }

    /**
     * 从参数列表指定な值中ゲインhttp転送パラメータ
     *
     * @param mixed $params 指定パラメータ
     * @return array
     */
    public function from($params): array
    {
        $result = [];
        $args = is_array($params) ? $params : func_get_args();

        foreach ($args as $arg) {
            $result[$arg] = $this->get($arg);
        }

        return $result;
    }

    /**
     * getRequestRoot
     *
     * @return string
     */
    public function getRequestRoot(): string
    {
        if (null === $this->requestRoot) {
            $root = rtrim($this->getUrlPrefix() . $this->getBaseUrl(), '/') . '/';

            $pos = strrpos($root, '.php/');
            if ($pos) {
                $root = dirname(substr($root, 0, $pos));
            }

            $this->requestRoot = rtrim($root, '/');
        }

        return $this->requestRoot;
    }

    /**
     * ゲイン当前リクエストurl
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->getUrlPrefix() . $this->getRequestUri();
    }

    /**
     * 現在のuriテクトニック所定のパラメータなuri
     *
     * @param mixed $parameter 指定パラメータ
     * @return string
     */
    public function makeUriByRequest($parameter = null): string
    {
        /** 初始化住所 */
        $requestUri = $this->getRequestUrl();
        $parts = parse_url($requestUri);

        /** 初期化パラメータ */
        if (is_string($parameter)) {
            parse_str($parameter, $args);
        } elseif (is_array($parameter)) {
            $args = $parameter;
        } else {
            return $requestUri;
        }

        /** テクトニックquery */
        if (isset($parts['query'])) {
            parse_str($parts['query'], $currentArgs);
            $args = array_merge($currentArgs, $args);
        }
        $parts['query'] = http_build_query($args);

        /** 返回住所 */
        return Common::buildUrl($parts);
    }

    /**
     * ゲイン当前pathinfo
     *
     * @return string
     */
    public function getPathInfo(): ?string
    {
        /** キャッシュ情報 */
        if (null !== $this->pathInfo) {
            return $this->pathInfo;
        }

        //協議Zend Framework右pathinfoバランスの取れた参加, より良い互換性
        $pathInfo = null;

        //扱うrequestUri
        $requestUri = $this->getRequestUri();
        $finalBaseUrl = $this->getBaseUrl();

        // Remove the query string from REQUEST_URI
        if ($pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if (
            (null !== $finalBaseUrl)
            && (false === ($pathInfo = substr($requestUri, strlen($finalBaseUrl))))
        ) {
            // If substr() returns false then PATH_INFO is set to an empty string
            $pathInfo = '/';
        } elseif (null === $finalBaseUrl) {
            $pathInfo = $requestUri;
        }

        if (!empty($pathInfo)) {
            //针右iisなutf8コードによる強制変換
            $pathInfo = defined('__TYPECHO_PATHINFO_ENCODING__') ?
                mb_convert_encoding($pathInfo, 'UTF-8', __TYPECHO_PATHINFO_ENCODING__) : $pathInfo;
        } else {
            $pathInfo = '/';
        }

        // fix issue 456
        return ($this->pathInfo = '/' . ltrim(urldecode($pathInfo), '/'));
    }

    /**
     * ゲイン环境变量
     *
     * @param string $name ゲイン环境变量名
     * @param string|null $default
     * @return string|null
     */
    public function getServer(string $name, string $default = null): ?string
    {
        return $_SERVER[$name] ?? $default;
    }

    /**
     * ゲインip住所
     *
     * @return string
     */
    public function getIp(): string
    {
        if (null === $this->ip) {
            $header = defined('__TYPECHO_IP_SOURCE__') ? __TYPECHO_IP_SOURCE__ : 'X-Forwarded-For';
            $ip = $this->getHeader($header, $this->getHeader('Client-Ip', $this->getServer('REMOTE_ADDR')));

            if (!empty($ip)) {
                [$ip] = array_map('trim', explode(',', $ip));
                $ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
            }

            if (!empty($ip)) {
                $this->ip = $ip;
            } else {
                $this->ip = 'unknown';
            }
        }

        return $this->ip;
    }

    /**
     * get header value
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function getHeader(string $key, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->getServer($key, $default);
    }

    /**
     * ゲインクライアント
     *
     * @return string
     */
    public function getAgent(): ?string
    {
        return $this->getHeader('User-Agent');
    }

    /**
     * ゲインクライアント
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $this->getHeader('Referer');
    }

    /**
     * を決定する。https
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && !strcasecmp('https', $_SERVER['HTTP_X_FORWARDED_PROTO']))
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && !strcasecmp('quic', $_SERVER['HTTP_X_FORWARDED_PROTO']))
            || (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && 443 == $_SERVER['HTTP_X_FORWARDED_PORT'])
            || (!empty($_SERVER['HTTPS']) && 'off' != strtolower($_SERVER['HTTPS']))
            || (!empty($_SERVER['SERVER_PORT']) && 443 == $_SERVER['SERVER_PORT'])
            || (defined('__TYPECHO_SECURE__') && __TYPECHO_SECURE__);
    }

    /**
     * @return bool
     */
    public function isCli(): bool
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * を決定する。get方法論論論
     *
     * @return boolean
     */
    public function isGet(): bool
    {
        return 'GET' == $this->getServer('REQUEST_METHOD');
    }

    /**
     * を決定する。post方法論論論
     *
     * @return boolean
     */
    public function isPost(): bool
    {
        return 'POST' == $this->getServer('REQUEST_METHOD');
    }

    /**
     * を決定する。put方法論論論
     *
     * @return boolean
     */
    public function isPut(): bool
    {
        return 'PUT' == $this->getServer('REQUEST_METHOD');
    }

    /**
     * を決定する。ajax
     *
     * @return boolean
     */
    public function isAjax(): bool
    {
        return 'XMLHttpRequest' == $this->getHeader('X-Requested-With');
    }

    /**
     * を決定する。Jsonリクエスト
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return !!preg_match("/^\s*application\/json(;|$)/i", $this->getHeader('Content-Type', ''));
    }

    /**
     * 入力が要件を満たしているかどうかを判断する
     *
     * @param mixed $query 前提条件
     * @return boolean
     */
    public function is($query): bool
    {
        $validated = false;

        /** パース文字列 */
        if (is_string($query)) {
            parse_str($query, $params);
        } elseif (is_array($query)) {
            $params = $query;
        }

        /** 検証文字列 */
        if (!empty($params)) {
            $validated = true;
            foreach ($params as $key => $val) {
                $param = $this->get($key, null, $exists);
                $validated = empty($val) ? $exists : ($val == $param);

                if (!$validated) {
                    break;
                }
            }
        }

        return $validated;
    }

    /**
     * ゲインリクエスト资源住所
     *
     * @return string|null
     */
    public function getRequestUri(): ?string
    {
        if (!empty($this->requestUri)) {
            return $this->requestUri;
        }

        //扱うrequestUri
        $requestUri = '/';

        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (
            // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
            isset($_SERVER['IIS_WasUrlRewritten'])
            && $_SERVER['IIS_WasUrlRewritten'] == '1'
            && isset($_SERVER['UNENCODED_URL'])
            && $_SERVER['UNENCODED_URL'] != ''
        ) {
            $requestUri = $_SERVER['UNENCODED_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            $parts = @parse_url($requestUri);

            if (isset($_SERVER['HTTP_HOST']) && strstr($requestUri, $_SERVER['HTTP_HOST'])) {
                if (false !== $parts) {
                    $requestUri = (empty($parts['path']) ? '' : $parts['path'])
                        . ((empty($parts['query'])) ? '' : '?' . $parts['query']);
                }
            } elseif (!empty($_SERVER['QUERY_STRING']) && empty($parts['query'])) {
                // fix query missing
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        return $this->requestUri = $requestUri;
    }

    /**
     * ゲインurl接頭辞
     *
     * @return string|null
     */
    public function getUrlPrefix(): ?string
    {
        if (empty($this->urlPrefix)) {
            if (defined('__TYPECHO_URL_PREFIX__')) {
                $this->urlPrefix = __TYPECHO_URL_PREFIX__;
            } elseif (php_sapi_name() != 'cli') {
                $this->urlPrefix = ($this->isSecure() ? 'https' : 'http') . '://'
                    . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);
            }
        }

        return $this->urlPrefix;
    }

    /**
     * getBaseUrl
     *
     * @return string
     */
    private function getBaseUrl(): ?string
    {
        if (null !== $this->baseUrl) {
            return $this->baseUrl;
        }

        //扱うbaseUrl
        $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

        if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
            $baseUrl = $_SERVER['PHP_SELF'];
        } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $_SERVER['PHP_SELF'] ?? '';
            $file = $_SERVER['SCRIPT_FILENAME'] ?? '';
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
        }

        // Does the baseUrl have anything in common with the request_uri?
        $finalBaseUrl = null;
        $requestUri = $this->getRequestUri();

        if (0 === strpos($requestUri, $baseUrl)) {
            // full $baseUrl matches
            $finalBaseUrl = $baseUrl;
        } elseif (0 === strpos($requestUri, dirname($baseUrl))) {
            // directory portion of $baseUrl matches
            $finalBaseUrl = rtrim(dirname($baseUrl), '/');
        } elseif (!strpos($requestUri, basename($baseUrl))) {
            // no match whatsoever; set it blank
            $finalBaseUrl = '';
        } elseif (
            (strlen($requestUri) >= strlen($baseUrl))
            && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))
        ) {
            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return ($this->baseUrl = (null === $finalBaseUrl) ? rtrim($baseUrl, '/') : $finalBaseUrl);
    }
}
