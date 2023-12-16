<?php

namespace Typecho\Widget;

use Typecho\Config;
use Typecho\Request as HttpRequest;

/**
 * Widget Request Wrapper
 */
class Request
{
    /**
     * 対応フィルター一覧
     *
     * @access private
     * @var string
     */
    private const FILTERS = [
        'int'     => 'intval',
        'integer' => 'intval',
        'encode'  => 'urlencode',
        'html'    => 'htmlspecialchars',
        'search'  => ['\Typecho\Common', 'filterSearchQuery'],
        'xss'     => ['\Typecho\Common', 'removeXSS'],
        'url'     => ['\Typecho\Common', 'safeUrl'],
        'slug'    => ['\Typecho\Common', 'slugName']
    ];

    /**
     * 現在のフィルター
     *
     * @access private
     * @var array
     */
    private $filter = [];

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var Config
     */
    private $params;

    /**
     * @param HttpRequest $request
     * @param Config|null $params
     */
    public function __construct(HttpRequest $request, ?Config $params = null)
    {
        $this->request = $request;
        $this->params = $params ?? new Config();
    }

    /**
     * セットアップhttp送信パラメータ
     *
     * @access public
     *
     * @param string $name 指定パラメータ
     * @param mixed $value パラメータ値
     *
     * @return void
     */
    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * セットアップ多个参数
     *
     * @access public
     *
     * @param mixed $params パラメータリスト
     *
     * @return void
     */
    public function setParams($params)
    {
        $this->params->setDefault($params);
    }

    /**
     * Add filter to request
     *
     * @param string|callable ...$filters
     * @return $this
     */
    public function filter(...$filters): Request
    {
        foreach ($filters as $filter) {
            $this->filter[] = $this->wrapFilter(
                is_string($filter) && isset(self::FILTERS[$filter])
                ? self::FILTERS[$filter] : $filter
            );
        }

        return $this;
    }

    /**
     * ゲイン实际送信パラメータ(magic)
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
     * @param string $key
     * @param null $default
     * @param bool|null $exists detect exists
     * @return mixed
     */
    public function get(string $key, $default = null, ?bool &$exists = true)
    {
        return $this->applyFilter($this->request->proxy($this->params)->get($key, $default, $exists));
    }

    /**
     * @param $key
     * @return array
     */
    public function getArray($key): array
    {
        return $this->applyFilter($this->request->proxy($this->params)->getArray($key));
    }

    /**
     * @param ...$params
     * @return array
     */
    public function from(...$params): array
    {
        return $this->applyFilter(call_user_func_array([$this->request->proxy($this->params), 'from'], $params));
    }

    /**
     * @return string
     */
    public function getRequestRoot(): string
    {
        return $this->request->getRequestRoot();
    }

    /**
     * 現在の完全なリクエストを取得するurl
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->request->getRequestUrl();
    }

    /**
     * 要求されたリソースのアドレスを取得する
     *
     * @return string|null
     */
    public function getRequestUri(): ?string
    {
        return $this->request->getRequestUri();
    }

    /**
     * 現在pathinfo
     *
     * @return string|null
     */
    public function getPathInfo(): ?string
    {
        return $this->request->getPathInfo();
    }

    /**
     * ゲインurl接頭辞
     *
     * @return string|null
     */
    public function getUrlPrefix(): ?string
    {
        return $this->request->getUrlPrefix();
    }

    /**
     * 現在のuri构造所定のパラメータ的uri
     *
     * @param mixed $parameter 指定パラメータ
     * @return string
     */
    public function makeUriByRequest($parameter = null): string
    {
        return $this->request->makeUriByRequest($parameter);
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
        return $this->request->getServer($name, $default);
    }

    /**
     * ゲインipアドレス
     *
     * @return string
     */
    public function getIp(): string
    {
        return $this->request->getIp();
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
        return $this->request->getHeader($key, $default);
    }

    /**
     * ゲイン客户端
     *
     * @return string
     */
    public function getAgent(): ?string
    {
        return $this->request->getAgent();
    }

    /**
     * ゲイン客户端
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $this->request->getReferer();
    }

    /**
     * を決定する。https
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->request->isSecure();
    }

    /**
     * を決定する。get方法論論論
     *
     * @return boolean
     */
    public function isGet(): bool
    {
        return $this->request->isGet();
    }

    /**
     * を決定する。post方法論論論
     *
     * @return boolean
     */
    public function isPost(): bool
    {
        return $this->request->isPost();
    }

    /**
     * を決定する。put方法論論論
     *
     * @return boolean
     */
    public function isPut(): bool
    {
        return $this->request->isPut();
    }

    /**
     * を決定する。ajax
     *
     * @return boolean
     */
    public function isAjax(): bool
    {
        return $this->request->isAjax();
    }

    /**
     * 入力が要件を満たしているかどうかを判断する
     *
     * @param mixed $query 前提条件
     * @return boolean
     */
    public function is($query): bool
    {
        return $this->request->is($query);
    }

    /**
     * アプリケーションフィルター
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function applyFilter($value)
    {
        if ($this->filter) {
            foreach ($this->filter as $filter) {
                $value = is_array($value) ? array_map($filter, $value) :
                    call_user_func($filter, $value);
            }

            $this->filter = [];
        }

        return $value;
    }

    /**
     * Wrap a filter to make sure it always receives a string.
     *
     * @param callable $filter
     *
     * @return callable
     */
    private function wrapFilter(callable $filter): callable
    {
        return function ($value) use ($filter) {
            return call_user_func($filter, $value ?? '');
        };
    }
}
