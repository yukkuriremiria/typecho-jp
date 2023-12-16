<?php

namespace IXR;

use Typecho\Http\Client as HttpClient;

/**
 * IXRクライアント
 * reload by typecho team(http://www.typecho.org)
 *
 * @package IXR
 */
class Client
{
    /** 默认クライアント */
    private const DEFAULT_USERAGENT = 'Typecho XML-RPC PHP Library';

    /**
     * 住所
     *
     * @var string
     */
    private $url;

    /**
     * メッセージ本文
     *
     * @var Message
     */
    private $message;

    /**
     * コミッショニングスイッチ
     *
     * @var boolean
     */
    private $debug = false;

    /**
     * 要求接頭辞
     *
     * @var string|null
     */
    private $prefix;

    /**
     * @var Error
     */
    private $error;

    /**
     * クライアント构造函数
     *
     * @param string $url 服务端住所
     * @param string|null $prefix
     * @return void
     */
    public function __construct(
        string $url,
        ?string $prefix = null
    ) {
        $this->url = $url;
        $this->prefix = $prefix;
    }

    /**
     * デバッグモードの設定
     * @deprecated
     */
    public function setDebug()
    {
        $this->debug = true;
    }

    /**
     * 要求を実行する
     *
     * @param string $method
     * @param array $args
     * @return bool
     */
    private function rpcCall(string $method, array $args): bool
    {
        $request = new Request($method, $args);
        $xml = $request->getXml();

        $client = HttpClient::get();
        if (!$client) {
            $this->error = new Error(-32300, 'transport error - could not open socket');
            return false;
        }

        try {
            $client->setHeader('Content-Type', 'text/xml')
                ->setHeader('User-Agent', self::DEFAULT_USERAGENT)
                ->setData($xml)
                ->send($this->url);
        } catch (HttpClient\Exception $e) {
            $this->error = new Error(-32700, $e->getMessage());
            return false;
        }

        $contents = $client->getResponseBody();

        if ($this->debug) {
            echo '<pre>' . htmlspecialchars($contents) . "\n</pre>\n\n";
        }

        // Now parse what we've got back
        $this->message = new Message($contents);
        if (!$this->message->parse()) {
            // XML error
            $this->error = new Error(-32700, 'parse error. not well formed');
            return false;
        }

        // Is the message a fault?
        if ($this->message->messageType == 'fault') {
            $this->error = new Error($this->message->faultCode, $this->message->faultString);
            return false;
        }

        // Message must be OK
        return true;
    }

    /**
     * 接頭辞の追加
     * <code>
     * $rpc->metaWeblog->newPost();
     * </code>
     *
     * @param string $prefix 接頭辞
     * @return Client
     */
    public function __get(string $prefix): Client
    {
        return new self($this->url, $this->prefix . $prefix . '.');
    }

    /**
     * マジック・プロパティの追加
     * by 70
     *
     * @return mixed
     * @throws Exception
     */
    public function __call($method, $args)
    {
        $return = $this->rpcCall($this->prefix . $method, $args);

        if ($return) {
            return $this->getResponse();
        } else {
            throw new Exception($this->getErrorMessage(), $this->getErrorCode());
        }
    }

    /**
     * 戻り値の取得
     *
     * @return mixed
     */
    public function getResponse()
    {
        // methodResponses can only have one param - return that
        return $this->message->params[0];
    }

    /**
     * エラーか？
     *
     * @return bool
     */
    public function isError(): bool
    {
        return isset($this->error);
    }

    /**
     * エラーコードの取得
     *
     * @return int
     */
    private function getErrorCode(): int
    {
        return $this->error->code;
    }

    /**
     * エラーメッセージの取得
     *
     * @return string
     */
    private function getErrorMessage(): string
    {
        return $this->error->message;
    }
}
