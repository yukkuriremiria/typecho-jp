<?php

namespace Typecho;

/**
 * Typecho例外基底クラス
 * 主なオーバーロード例外印刷関数
 *
 * @package Exception
 */
class Exception extends \Exception
{

    public function __construct($message, $code = 0)
    {
        $this->message = $message;
        $this->code = $code;
    }
}
