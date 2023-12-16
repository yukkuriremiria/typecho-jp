<?php

namespace Typecho\Http;

use Typecho\Common;
use Typecho\Http\Client\Exception;

/**
 * Httpクライアント
 *
 * @category typecho
 * @package Http
 */
class Client
{
    /** POST方法論論論論 */
    public const METHOD_POST = 'POST';

    /** GET方法論論論論 */
    public const METHOD_GET = 'GET';

    /** PUT方法論論論論 */
    public const METHOD_PUT = 'PUT';

    /** DELETE方法論論論論 */
    public const METHOD_DELETE = 'DELETE';

    /**
     * 方法論論論論名
     *
     * @var string
     */
    private $method = self::METHOD_GET;

    /**
     * 転送パラメータ
     *
     * @var string
     */
    private $query;

    /**
     * User Agent
     *
     * @var string
     */
    private $agent;

    /**
     * タイムアウトの設定
     *
     * @var string
     */
    private $timeout = 3;

    /**
     * @var bool
     */
    private $multipart = true;

    /**
     * である必要がある。bodyで渡される値。
     *
     * @var array|string
     */
    private $data = [];

    /**
     * ヘッダ情報パラメータ
     *
     * @access private
     * @var array
     */
    private $headers = [];

    /**
     * cookies
     *
     * @var array
     */
    private $cookies = [];

    /**
     * @var array
     */
    private $options = [];

    /**
     * ヘッダー情報を返す
     *
     * @var array
     */
    private $responseHeader = [];

    /**
     * リターンコード
     *
     * @var integer
     */
    private $responseStatus;

    /**
     * ボディへの返信
     *
     * @var string
     */
    private $responseBody;

    /**
     * 指定されたCOOKIE(価値がある
     *
     * @param string $key 指定パラメータ
     * @param mixed $value セットアップ的(価値がある
     * @return $this
     */
    public function setCookie(string $key, $value): Client
    {
        $this->cookies[$key] = $value;
        return $this;
    }

    /**
     * セットアップ転送パラメータ
     *
     * @param mixed $query 転送パラメータ
     * @return $this
     */
    public function setQuery($query): Client
    {
        $query = is_array($query) ? http_build_query($query) : $query;
        $this->query = empty($this->query) ? $query : $this->query . '&' . $query;
        return $this;
    }

    /**
     * セットアップ要件POSTデータ
     *
     * @param array|string $data 必要POSTデータ
     * @param string $method
     * @return $this
     */
    public function setData($data, string $method = self::METHOD_POST): Client
    {
        if (is_array($data) && is_array($this->data)) {
            $this->data = array_merge($this->data, $data);
        } else {
            $this->data = $data;
        }

        $this->setMethod($method);
        return $this;
    }

    /**
     * セットアップ要件请求的Json数字
     *
     * @param $data
     * @param string $method
     * @return $this
     */
    public function setJson($data, string $method = self::METHOD_POST): Client
    {
        $this->setData(json_encode($data), $method)
            ->setMultipart(true)
            ->setHeader('Content-Type', 'application/json');

        return $this;
    }

    /**
     * セットアップ方法論論論論名
     *
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method): Client
    {
        $this->method = $method;
        return $this;
    }

    /**
     * セットアップ要件POSTドキュメント
     *
     * @param array $files 必要POSTドキュメント
     * @param string $method
     * @return $this
     */
    public function setFiles(array $files, string $method = self::METHOD_POST): Client
    {
        if (is_array($this->data)) {
            foreach ($files as $name => $file) {
                $this->data[$name] = new \CURLFile($file);
            }
        }

        $this->setMethod($method);
        return $this;
    }

    /**
     * タイムアウトの設定时间
     *
     * @param integer $timeout タイムアウト
     * @return $this
     */
    public function setTimeout(int $timeout): Client
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * setAgent
     *
     * @param string $agent
     * @return $this
     */
    public function setAgent(string $agent): Client
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @param bool $multipart
     * @return $this
     */
    public function setMultipart(bool $multipart): Client
    {
        $this->multipart = $multipart;
        return $this;
    }

    /**
     * @param int $key
     * @param mixed $value
     * @return $this
     */
    public function setOption(int $key, $value): Client
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * セットアップヘッダ情報パラメータ
     *
     * @param string $key パラメータ名
     * @param string $value 参数(価値がある
     * @return $this
     */
    public function setHeader(string $key, string $value): Client
    {
        $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)));

        if ($key == 'User-Agent') {
            $this->setAgent($value);
        } else {
            $this->headers[$key] = $value;
        }

        return $this;
    }

    /**
     * リクエストを送信
     *
     * @param string $url リクエストアドレス
     * @throws Exception
     */
    public function send(string $url)
    {
        $params = parse_url($url);
        $query = empty($params['query']) ? '' : $params['query'];

        if (!empty($this->query)) {
            $query = empty($query) ? $this->query : '&' . $this->query;
        }

        if (!empty($query)) {
            $params['query'] = $query;
        }

        $url = Common::buildUrl($params);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

        if (isset($this->agent)) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->agent);
        }

        /** セットアップheaderインフォメーション */
        if (!empty($this->headers)) {
            $headers = [];

            foreach ($this->headers as $key => $val) {
                $headers[] = $key . ': ' . $val;
            }

            if (!empty($this->cookies)) {
                $headers[] = 'Cookie: ' . str_replace('&', '; ', http_build_query($this->cookies));
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (!empty($this->data)) {
            $data = $this->data;

            if (!$this->multipart) {
                curl_setopt($ch, CURLOPT_POST, true);
                $data = is_array($data) ? http_build_query($data) : $data;
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) {
            $parts = explode(':', $header, 2);

            if (count($parts) == 2) {
                [$key, $value] = $parts;
                $this->responseHeader[strtolower(trim($key))] = trim($value);
            }

            return strlen($header);
        });

        foreach ($this->options as $key => $val) {
            curl_setopt($ch, $key, $val);
        }

        $response = curl_exec($ch);
        if (false === $response) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception($error, 500);
        }

        $this->responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->responseBody = $response;
        curl_close($ch);
    }

    /**
     * 获取回执的头部インフォメーション
     *
     * @param string $key 头インフォメーション名称
     * @return string
     */
    public function getResponseHeader(string $key): ?string
    {
        $key = strtolower($key);
        return $this->responseHeader[$key] ?? null;
    }

    /**
     * 获取リターンコード
     *
     * @return integer
     */
    public function getResponseStatus(): int
    {
        return $this->responseStatus;
    }

    /**
     * 获取ボディへの返信
     *
     * @return string
     */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    /**
     * 利用可能なコネクションの取得
     *
     * @return ?Client
     */
    public static function get(): ?Client
    {
        return extension_loaded('curl') ? new static() : null;
    }
}
