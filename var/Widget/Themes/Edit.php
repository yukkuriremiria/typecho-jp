<?php

namespace Widget\Themes;

use Typecho\Common;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 編集スタイル・コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends Options implements ActionInterface
{
    /**
     * 外観の交換
     *
     * @param string $theme 外観名
     * @throws Exception
     * @throws \Typecho\Db\Exception
     */
    public function changeTheme(string $theme)
    {
        $theme = trim($theme, './');
        if (is_dir($this->options->themeFile($theme))) {
            /** オリジナルの外観設定情報を削除する */
            $oldTheme = $this->options->missingTheme ?: $this->options->theme;
            $this->delete($this->db->sql()->where('name = ?', 'theme:' . $oldTheme));

            $this->update(['value' => $theme], $this->db->sql()->where('name = ?', 'theme'));

            /** ホームページのリンク解除 */
            if (0 === strpos($this->options->frontPage, 'file:')) {
                $this->update(['value' => 'recent'], $this->db->sql()->where('name = ?', 'frontPage'));
            }

            $this->options->themeUrl = $this->options->themeUrl(null, $theme);

            $configFile = $this->options->themeFile($theme, 'functions.php');

            if (file_exists($configFile)) {
                require_once $configFile;

                if (function_exists('themeConfig')) {
                    $form = new Form();
                    themeConfig($form);
                    $options = $form->getValues();

                    if ($options && !$this->configHandle($options, true)) {
                        $this->insert([
                            'name'  => 'theme:' . $theme,
                            'value' => serialize($options),
                            'user'  => 0
                        ]);
                    }
                }
            }

            Notice::alloc()->highlight('theme-' . $theme);
            Notice::alloc()->set(_t("外観が変更されました"), 'success');
            $this->response->goBack();
        } else {
            throw new Exception(_t('選択したスタイルが存在しない'));
        }
    }

    /**
     * 独自の機能で設定情報を処理する
     *
     * @param array $settings 設定値
     * @param boolean $isInit 初期化されているか
     * @return boolean
     */
    public function configHandle(array $settings, bool $isInit): bool
    {
        if (function_exists('themeConfigHandle')) {
            themeConfigHandle($settings, $isInit);
            return true;
        }

        return false;
    }

    /**
     * アピアランスファイルの編集
     *
     * @param string $theme 外観名
     * @param string $file ファイル名
     * @throws Exception
     */
    public function editThemeFile($theme, $file)
    {
        $path = $this->options->themeFile($theme, $file);

        if (
            file_exists($path) && is_writeable($path)
            && (!defined('__TYPECHO_THEME_WRITEABLE__') || __TYPECHO_THEME_WRITEABLE__)
        ) {
            $handle = fopen($path, 'wb');
            if ($handle && fwrite($handle, $this->request->content)) {
                fclose($handle);
                Notice::alloc()->set(_t("書類 %s 変更が保存されました", $file), 'success');
            } else {
                Notice::alloc()->set(_t("書類 %s 書き込み不可", $file), 'error');
            }
            $this->response->goBack();
        } else {
            throw new Exception(_t('您编辑的書類不存在'));
        }
    }

    /**
     * コンフィギュレーション外観
     *
     * @param string $theme 外観名
     * @throws \Typecho\Db\Exception
     */
    public function config(string $theme)
    {
        // アピアランス機能がロードされました
        $form = Config::alloc()->config();

        /** バリデーションフォーム */
        if (!Config::isExists() || $form->validate()) {
            $this->response->goBack();
        }

        $settings = $form->getAllRequest();

        if (!$this->configHandle($settings, false)) {
            if ($this->options->__get('theme:' . $theme)) {
                $this->update(
                    ['value' => serialize($settings)],
                    $this->db->sql()->where('name = ?', 'theme:' . $theme)
                );
            } else {
                $this->insert([
                    'name'  => 'theme:' . $theme,
                    'value' => serialize($settings),
                    'user'  => 0
                ]);
            }
        }

        /** ハイライトの設定 */
        Notice::alloc()->highlight('theme-' . $theme);

        /** アラート */
        Notice::alloc()->set(_t("外観の設定が保存されました"), 'success');

        /** オリジナルページへ */
        $this->response->redirect(Common::url('options-theme.php', $this->options->adminUrl));
    }

    /**
     * バインド・アクション
     *
     * @throws Exception|\Typecho\Db\Exception
     */
    public function action()
    {
        /** 管理者権限が必要 */
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->is('change'))->changeTheme($this->request->filter('slug')->change);
        $this->on($this->request->is('edit&theme'))
            ->editThemeFile($this->request->filter('slug')->theme, $this->request->edit);
        $this->on($this->request->is('config'))->config($this->options->theme);
        $this->response->redirect($this->options->adminUrl);
    }
}
