<?php

namespace Widget\Themes;

use Typecho\Widget;
use Widget\Base;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * スタイルファイル一覧コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Files extends Base
{
    /**
     * 現在のスタイル
     *
     * @access private
     * @var string
     */
    private $currentTheme;

    /**
     * 現在のドキュメント
     *
     * @access private
     * @var string
     */
    private $currentFile;

    /**
     * 実行可能関数
     *
     * @throws Widget\Exception
     */
    public function execute()
    {
        /** 管理者権限 */
        $this->user->pass('administrator');
        $this->currentTheme = $this->request->filter('slug')->get('theme', Options::alloc()->theme);

        if (
            preg_match("/^([_0-9a-z-\.\ ])+$/i", $this->currentTheme)
            && is_dir($dir = Options::alloc()->themeFile($this->currentTheme))
            && (!defined('__TYPECHO_THEME_WRITEABLE__') || __TYPECHO_THEME_WRITEABLE__)
        ) {
            $files = array_filter(glob($dir . '/*'), function ($path) {
                return preg_match("/\.(php|js|css|vbs)$/i", $path);
            });

            $this->currentFile = $this->request->get('file', 'index.php');

            if (
                preg_match("/^([_0-9a-z-\.\ ])+$/i", $this->currentFile)
                && file_exists($dir . '/' . $this->currentFile)
            ) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $file = basename($file);
                        $this->push([
                            'file'    => $file,
                            'theme'   => $this->currentTheme,
                            'current' => ($file == $this->currentFile)
                        ]);
                    }
                }

                return;
            }
        }

        throw new Widget\Exception('スタイルファイルが存在しない', 404);
    }

    /**
     * 書き込み権限があるかどうかを判断する
     *
     * @return bool
     */
    public static function isWriteable(): bool
    {
        return (!defined('__TYPECHO_THEME_WRITEABLE__') || __TYPECHO_THEME_WRITEABLE__)
            && !Options::alloc()->missingTheme;
    }

    /**
     * メニュータイトルの取得
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('編集ファイル %s', $this->currentFile);
    }

    /**
     * 文書の内容の取得
     *
     * @return string
     */
    public function currentContent(): string
    {
        return htmlspecialchars(file_get_contents(Options::alloc()
            ->themeFile($this->currentTheme, $this->currentFile)));
    }

    /**
     * ファイルが読み取り可能かどうかを取得する
     *
     * @return bool
     */
    public function currentIsWriteable(): bool
    {
        return is_writeable(Options::alloc()
                ->themeFile($this->currentTheme, $this->currentFile))
            && self::isWriteable();
    }

    /**
     * 获取現在のドキュメント
     *
     * @return string
     */
    public function currentFile(): string
    {
        return $this->currentFile;
    }

    /**
     * 获取現在のスタイル
     *
     * @return string
     */
    public function currentTheme(): string
    {
        return $this->currentTheme;
    }
}
