<?php

namespace Typecho;

use Typecho\Widget\Terminal;

/**
 * Typechoパブリックメソッド
 *
 * @category typecho
 * @package Response
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Response
{
    /**
     * http code
     *
     * @access private
     * @var array
     */
    private const HTTP_CODE = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    ];

    //デフォルトの文字エンコーディング
    /**
     * シングルインスタンスハンドル
     *
     * @access private
     * @var Response
     */
    private static $instance;

    /**
     * 文字エンコーディング
     *
     * @var string
     */
    private $charset = 'UTF-8';

    /**
     * @var string
     */
    private $contentType = 'text/html';

    /**
     * @var callable[]
     */
    private $responders = [];

    /**
     * @var array
     */
    private $cookies = [];

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var int
     */
    private $status = 200;

    /**
     * @var bool
     */
    private $enableAutoSendHeaders = true;

    /**
     * @var bool
     */
    private $sandbox = false;

    /**
     * init responder
     */
    public function __construct()
    {
        $this->clean();
    }

    /**
     * 获取シングルインスタンスハンドル
     *
     * @return Response
     */
    public static function getInstance(): Response
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return $this
     */
    public function beginSandbox(): Response
    {
        $this->sandbox = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function endSandbox(): Response
    {
        $this->sandbox = false;
        return $this;
    }

    /**
     * @param bool $enable
     */
    public function enableAutoSendHeaders(bool $enable = true)
    {
        $this->enableAutoSendHeaders = $enable;
    }

    /**
     * clean all
     */
    public function clean()
    {
        $this->headers = [];
        $this->cookies = [];
        $this->status = 200;
        $this->responders = [];
        $this->setContentType('text/html');
    }

    /**
     * send all headers
     */
    public function sendHeaders()
    {
        if ($this->sandbox) {
            return;
        }

        $sentHeaders = [];
        foreach (headers_list() as $header) {
            [$key] = explode(':', $header, 2);
            $sentHeaders[] = strtolower(trim($key));
        }

        header('HTTP/1.1 ' . $this->status . ' ' . self::HTTP_CODE[$this->status], true, $this->status);

        // set header
        foreach ($this->headers as $name => $value) {
            if (!in_array(strtolower($name), $sentHeaders)) {
                header($name . ': ' . $value, true);
            }
        }

        // set cookie
        foreach ($this->cookies as $cookie) {
            [$key, $value, $timeout, $path, $domain, $secure, $httponly] = $cookie;

            if ($timeout > 0) {
                $now = time();
                $timeout += $timeout > $now - 86400 ? 0 : $now;
            } elseif ($timeout < 0) {
                $timeout = 1;
            }

            setrawcookie($key, rawurlencode($value), $timeout, $path, $domain, $secure, $httponly);
        }
    }

    /**
     * respond data
     * @throws Terminal
     */
    public function respond()
    {
        if ($this->sandbox) {
            throw new Terminal();
        }

        if ($this->enableAutoSendHeaders) {
            $this->sendHeaders();
        }

        foreach ($this->responders as $responder) {
            call_user_func($responder, $this);
        }

        exit;
    }

    /**
     * セットアップHTTP情勢
     *
     * @access public
     * @param integer $code httpコーディング
     * @return $this
     */
    public function setStatus(int $code): Response
    {
        if (!$this->sandbox) {
            $this->status = $code;
        }

        return $this;
    }

    /**
     * セットアップhttp始終
     *
     * @param string $name な
     * @param string $value 対応値
     * @return $this
     */
    public function setHeader(string $name, string $value): Response
    {
        if (!$this->sandbox) {
            $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * セットアップ指定的COOKIE(価値がある
     *
     * @param string $key 指定パラメータ
     * @param mixed $value セットアップ的(価値がある
     * @param integer $timeout 有効期限,デフォルト0,セッション終了時刻を示す。
     * @param string $path ルート情報
     * @param string|null $domain ドメイン情報
     * @param bool $secure 安全なパスしかできないのか？ HTTPS 接続はクライアントに渡される
     * @param bool $httponly このサービスは HTTP プロトコルアクセス
     * @return $this
     */
    public function setCookie(
        string $key,
        $value,
        int $timeout = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false
    ): Response {
        if (!$this->sandbox) {
            $this->cookies[] = [$key, $value, $timeout, $path, $domain, $secure, $httponly];
        }

        return $this;
    }

    /**
     * あるhttp始終部请求中声明类型和文字セット
     *
     * @param string $contentType 文書タイプ
     * @return $this
     */
    public function setContentType(string $contentType = 'text/html'): Response
    {
        if (!$this->sandbox) {
            $this->contentType = $contentType;
            $this->setHeader('Content-Type', $this->contentType . '; charset=' . $this->charset);
        }

        return $this;
    }

    /**
     * 文字セット取得
     *
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * セットアップ默认回执编码
     *
     * @param string $charset 文字セット
     * @return $this
     */
    public function setCharset(string $charset): Response
    {
        if (!$this->sandbox) {
            $this->charset = $charset;
            $this->setHeader('Content-Type', $this->contentType . '; charset=' . $this->charset);
        }

        return $this;
    }

    /**
     * add responder
     *
     * @param callable $responder
     * @return $this
     */
    public function addResponder(callable $responder): Response
    {
        if (!$this->sandbox) {
            $this->responders[] = $responder;
        }

        return $this;
    }
}
