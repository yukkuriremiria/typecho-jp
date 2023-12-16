<?php

namespace Widget;

use Typecho\Common;
use Typecho\Exception;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コンポーネントのアップグレード
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Upgrade extends BaseOptions implements ActionInterface
{
    /**
     * アップグレード手順の実施
     *
     * @throws \Typecho\Db\Exception
     */
    public function upgrade()
    {
        $packages = get_class_methods('Upgrade');

        preg_match("/^\w+ ([0-9\.]+)(\/[0-9\.]+)?$/i", $this->options->generator, $matches);
        $currentVersion = $matches[1];
        $currentMinor = '0';
        if (isset($matches[2])) {
            $currentMinor = substr($matches[2], 1);
        }

        $message = [];

        foreach ($packages as $package) {
            preg_match("/^v([_0-9]+)(r[_0-9]+)?$/", $package, $matches);

            $version = str_replace('_', '.', $matches[1]);

            if (version_compare($currentVersion, $version, '>')) {
                break;
            }

            if (isset($matches[2])) {
                $minor = substr(str_replace('_', '.', $matches[2]), 1);

                if (
                    version_compare($currentVersion, $version, '=')
                    && version_compare($currentMinor, $minor, '>=')
                ) {
                    break;
                }

                $version .= '/' . $minor;
            }

            $options = Options::allocWithAlias($package);

            /** アップグレードスクリプトの実行 */
            try {
                $result = call_user_func([\Utils\Upgrade::class, $package], $this->db, $options);
                if (!empty($result)) {
                    $message[] = $result;
                }
            } catch (Exception $e) {
                Notice::alloc()->set($e->getMessage(), 'error');
                $this->response->goBack();
            }

            /** バージョン番号の更新 */
            $this->update(
                ['value' => 'Typecho ' . $version],
                $this->db->sql()->where('name = ?', 'generator')
            );

            Options::destroy($package);
        }

        /** バージョン番号の更新 */
        $this->update(
            ['value' => 'Typecho ' . Common::VERSION],
            $this->db->sql()->where('name = ?', 'generator')
        );

        Notice::alloc()->set(
            empty($message) ? _t("アップグレード完了") : $message,
            empty($message) ? 'success' : 'notice'
        );
    }

    /**
     * 初期化関数
     *
     * @throws \Typecho\Db\Exception
     * @throws \Typecho\Widget\Exception
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->upgrade();
        $this->response->redirect($this->options->adminUrl);
    }
}
