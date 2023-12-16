<?php

namespace IXR;

use Typecho\Widget\Exception as WidgetException;

/**
 * IXRサーバ
 *
 * @package IXR
 */
class Server
{
    /**
     * コールバック関数
     *
     * @var array
     */
    private $callbacks;

    /**
     * デフォルトパラメータ
     *
     * @var array
     */
    private $capabilities;

    /**
     * @var Hook
     */
    private $hook;

    /**
     * コンストラクタ
     *
     * @param array $callbacks コールバック関数
     */
    public function __construct(array $callbacks = [])
    {
        $this->setCapabilities();
        $this->callbacks = $callbacks;
        $this->setCallbacks();
    }

    /**
     * 获取デフォルトパラメータ
     *
     * @access public
     * @return array
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * すべてのメソッドをリストする
     *
     * @access public
     * @return array
     */
    public function listMethods(): array
    {
        // Returns a list of methods - uses array_reverse to ensure user defined
        // methods are listed before server defined methods
        return array_reverse(array_keys($this->callbacks));
    }

    /**
     * 一度に複数のリクエストを処理する
     *
     * @param array $methodcalls
     * @return array
     */
    public function multiCall(array $methodcalls): array
    {
        // See http://www.xmlrpc.com/discuss/msgReader$1208
        $return = [];
        foreach ($methodcalls as $call) {
            $method = $call['methodName'];
            $params = $call['params'];
            if ($method == 'system.multicall') {
                $result = new Error(-32600, 'Recursive calls to system.multicall are forbidden');
            } else {
                $result = $this->call($method, $params);
            }
            if (is_a($result, 'Error')) {
                $return[] = [
                    'faultCode'   => $result->code,
                    'faultString' => $result->message
                ];
            } else {
                $return[] = [$result];
            }
        }
        return $return;
    }

    /**
     * @param string $methodName
     * @return string|Error
     */
    public function methodHelp(string $methodName)
    {
        if (!$this->hasMethod($methodName)) {
            return new Error(-32601, 'server error. requested method ' . $methodName . ' does not exist.');
        }

        [$object, $method] = $this->callbacks[$methodName];

        try {
            $ref = new \ReflectionMethod($object, $method);
            $doc = $ref->getDocComment();

            return $doc ?: '';
        } catch (\ReflectionException $e) {
            return '';
        }
    }

    /**
     * @param Hook $hook
     */
    public function setHook(Hook $hook)
    {
        $this->hook = $hook;
    }

    /**
     * 内部メソッドの呼び出し
     *
     * @param string $methodName メソッド名
     * @param array $args パラメーター
     * @return mixed
     */
    private function call(string $methodName, array $args)
    {
        if (!$this->hasMethod($methodName)) {
            return new Error(-32601, 'server error. requested method ' . $methodName . ' does not exist.');
        }
        $method = $this->callbacks[$methodName];

        if (!is_callable($method)) {
            return new Error(
                -32601,
                'server error. requested class method "' . $methodName . '" does not exist.'
            );
        }

        [$object, $objectMethod] = $method;

        try {
            $ref = new \ReflectionMethod($object, $objectMethod);
            $requiredArgs = $ref->getNumberOfRequiredParameters();
            if (count($args) < $requiredArgs) {
                return new Error(
                    -32602,
                    'server error. requested class method "' . $methodName . '" require ' . $requiredArgs . ' params.'
                );
            }

            foreach ($ref->getParameters() as $key => $parameter) {
                if ($parameter->hasType() && !settype($args[$key], $parameter->getType()->getName())) {
                    return new Error(
                        -32602,
                        'server error. requested class method "'
                        . $methodName . '" ' . $key . ' param has wrong type.'
                    );
                }
            }

            if (isset($this->hook)) {
                $result = $this->hook->beforeRpcCall($methodName, $ref, $args);

                if (isset($result)) {
                    return $result;
                }
            }

            $result = call_user_func_array($method, $args);

            if (isset($this->hook)) {
                $this->hook->afterRpcCall($methodName, $result);
            }

            return $result;
        } catch (\ReflectionException $e) {
            return new Error(
                -32601,
                'server error. requested class method "' . $methodName . '" does not exist.'
            );
        } catch (Exception $e) {
            return new Error(
                $e->getCode(),
                $e->getMessage()
            );
        } catch (WidgetException $e) {
            return new Error(
                -32001,
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return new Error(
                -32001,
                'server error. requested class method "' . $methodName . '" failed.'
            );
        }
    }

    /**
     * エラーをスローする
     *
     * @access private
     * @param integer|Error $error エラーコード
     * @param string|null $message エラーメッセージ
     * @return void
     */
    private function error($error, ?string $message = null)
    {
        // Accepts either an error object or an error code and message
        if (!$error instanceof Error) {
            $error = new Error($error, $message);
        }

        $this->output($error->getXml());
    }

    /**
     * 輸出xml
     *
     * @access private
     * @param string $xml 輸出xml
     */
    private function output(string $xml)
    {
        $xml = '<?xml version="1.0"?>' . "\n" . $xml;
        $length = strlen($xml);
        header('Connection: close');
        header('Content-Length: ' . $length);
        header('Content-Type: text/xml');
        header('Date: ' . date('r'));
        echo $xml;
        exit;
    }

    /**
     * メソッドの存在
     *
     * @access private
     * @param string $method メソッド名
     * @return mixed
     */
    private function hasMethod(string $method)
    {
        return in_array($method, array_keys($this->callbacks));
    }

    /**
     * 设置デフォルトパラメータ
     *
     * @access public
     * @return void
     */
    private function setCapabilities()
    {
        // Initialises capabilities array
        $this->capabilities = [
            'xmlrpc'           => [
                'specUrl'     => 'http://www.xmlrpc.com/spec',
                'specVersion' => 1
            ],
            'faults_interop'   => [
                'specUrl'     => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
                'specVersion' => 20010516
            ],
            'system.multicall' => [
                'specUrl'     => 'http://www.xmlrpc.com/discuss/msgReader$1208',
                'specVersion' => 1
            ],
        ];
    }

    /**
     * デフォルト方式の設定
     *
     * @access private
     * @return void
     */
    private function setCallbacks()
    {
        $this->callbacks['system.getCapabilities'] = [$this, 'getCapabilities'];
        $this->callbacks['system.listMethods'] = [$this, 'listMethods'];
        $this->callbacks['system.multicall'] = [$this, 'multiCall'];
        $this->callbacks['system.methodHelp'] = [$this, 'methodHelp'];
    }

    /**
     * サービスポータル
     */
    public function serve()
    {
        $message = new Message(file_get_contents('php://input') ?: '');

        if (!$message->parse()) {
            $this->error(-32700, 'parse error. not well formed');
        } elseif ($message->messageType != 'methodCall') {
            $this->error(-32600, 'server error. invalid xml-rpc. not conforming to spec. Request must be a methodCall');
        }

        $result = $this->call($message->methodName, $message->params);
        // Is the result an error?
        if ($result instanceof Error) {
            $this->error($result);
        }

        // Encode the result
        $r = new Value($result);
        $resultXml = $r->getXml();

        // Create the XML
        $xml = <<<EOD
<methodResponse>
  <params>
    <param>
      <value>
        $resultXml
      </value>
    </param>
  </params>
</methodResponse>

EOD;

        // Send it
        $this->output($xml);
    }
}
