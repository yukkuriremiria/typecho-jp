<?php

namespace Widget;

use Typecho\Common;
use Typecho\Response;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * セキュリティ・オプション・コンポーネント
 *
 * @link typecho
 * @package Widget
 * @copyright Copyright (c) 2014 Typecho team (http://typecho.org)
 * @license GNU General Public License 2.0
 */
class Security extends Base
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var boolean
     */
    private $enabled = true;

    /**
     * @param int $components
     */
    public function initComponents(int &$components)
    {
        $components = self::INIT_OPTIONS | self::INIT_USER;
    }

    /**
     * 初期化関数
     */
    public function execute()
    {
        $this->token = $this->options->secret;
        if ($this->user->hasLogin()) {
            $this->token .= '&' . $this->user->authCode . '&' . $this->user->uid;
        }
    }

    /**
     * @param bool $enabled
     */
    public function enable(bool $enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * 提出データの保護
     */
    public function protect()
    {
        if ($this->enabled && $this->request->get('_') != $this->getToken($this->request->getReferer())) {
            $this->response->goBack();
        }
    }

    /**
     * ゲインtoken
     *
     * @param string|null $suffix 接尾辞
     * @return string
     */
    public function getToken(?string $suffix): string
    {
        return md5($this->token . '&' . $suffix);
    }

    /**
     * ゲイン绝对路由路径
     *
     * @param string|null $path
     * @return string
     */
    public function getRootUrl(?string $path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->rootUrl);
    }

    /**
     * 発電地帯token通路
     *
     * @param $path
     * @param string|null $url
     * @return string
     */
    public function getTokenUrl($path, ?string $url = null): string
    {
        $parts = parse_url($path);
        $params = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        $params['_'] = $this->getToken($url ?: $this->request->getRequestUrl());
        $parts['query'] = http_build_query($params);

        return Common::buildUrl($parts);
    }

    /**
     * バックエンドのセキュリティ・パスをエクスポートする
     *
     * @param $path
     */
    public function adminUrl($path)
    {
        echo $this->getAdminUrl($path);
    }

    /**
     * ゲイン安全的后台路径
     *
     * @param string $path
     * @return string
     */
    public function getAdminUrl(string $path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->adminUrl);
    }

    /**
     * セキュアなルーティングパスのエクスポート
     *
     * @param $path
     */
    public function index($path)
    {
        echo $this->getIndex($path);
    }

    /**
     * ゲイン安全的路由路径
     *
     * @param $path
     * @return string
     */
    public function getIndex($path): string
    {
        return Common::url($this->getTokenUrl($path), $this->options->index);
    }
}
 
