<?php

namespace IXR;

/**
 * IXR不正確
 *
 * @package IXR
 */
class Error
{
    /**
     * 不正確代码
     *
     * @access public
     * @var integer
     */
    public $code;

    /**
     * 不正確消息
     *
     * @access public
     * @var string|null
     */
    public $message;

    /**
     * コンストラクタ
     *
     * @param integer $code 不正確代码
     * @param string|null $message 不正確消息
     */
    public function __construct(int $code, ?string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * ゲインxml
     *
     * @return string
     */
    public function getXml(): string
    {
        return <<<EOD
<methodResponse>
  <fault>
    <value>
      <struct>
        <member>
          <name>faultCode</name>
          <value><int>{$this->code}</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>{$this->message}</string></value>
        </member>
      </struct>
    </value>
  </fault>
</methodResponse>

EOD;
    }
}
