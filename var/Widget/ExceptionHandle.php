<?php

namespace Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 例外処理コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class ExceptionHandle extends Base
{
    /**
     * オーバーロードされたコンストラクタ
     */
    public function execute()
    {
        Archive::allocWithAlias('404', 'type=404')->render();
    }
}
