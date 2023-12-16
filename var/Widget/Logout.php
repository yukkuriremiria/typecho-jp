<?php

namespace Widget;

use Widget\Base\Users;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ログアウトコンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Logout extends Users implements ActionInterface
{
    /**
     * 初期化関数
     *
     * @access public
     * @return void
     */
    public function action()
    {
        // protect
        $this->security->protect();

        $this->user->logout();
        self::pluginHandle()->logout();
        @session_destroy();
        $this->response->goBack(null, $this->options->index);
    }
}
