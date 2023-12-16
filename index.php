<?php
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id: index.php 1153 2009-07-02 10:53:22Z magike.net $
 */

/** 負荷設定サポート */
if (!defined('__TYPECHO_ROOT_DIR__') && !@include_once 'config.inc.php') {
    file_exists('./install.php') ? header('Location: install.php') : print('Missing Config File');
    exit;
}

/** コンポーネントの初期化 */
\Widget\Init::alloc();

/** 初期化プラグインの登録 */
\Typecho\Plugin::factory('index.php')->begin();

/** スターティング・ルート */
\Typecho\Router::dispatch();

/** エンドプラグインの登録 */
\Typecho\Plugin::factory('index.php')->end();
