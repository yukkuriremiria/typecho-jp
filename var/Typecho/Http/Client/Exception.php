<?php

namespace Typecho\Http\Client;

use Typecho\Exception as TypechoException;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Httpクライアント例外クラス
 *
 * @package Http
 */
class Exception extends TypechoException
{
}
