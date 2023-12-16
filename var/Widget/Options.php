<?php

namespace Widget;

use Typecho\Common;
use Typecho\Config;
use Typecho\Db;
use Typecho\Router;
use Typecho\Router\Parser;
use Typecho\Widget;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Db\Exception as DbException;
use Typecho\Date;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * グローバル・オプション・コンポーネント
 *
 * @property string $feedUrl
 * @property string $feedRssUrl
 * @property string $feedAtomUrl
 * @property string $commentsFeedUrl
 * @property string $commentsFeedRssUrl
 * @property string $commentsFeedAtomUrl
 * @property string $themeUrl
 * @property string $xmlRpcUrl
 * @property string $index
 * @property string $siteUrl
 * @property array $routingTable
 * @property string $rootUrl
 * @property string $pluginUrl
 * @property string $pluginDir
 * @property string $adminUrl
 * @property string $loginUrl
 * @property string $originalSiteUrl
 * @property string $loginAction
 * @property string $registerUrl
 * @property string $registerAction
 * @property string $profileUrl
 * @property string $logoutUrl
 * @property string $title
 * @property string $description
 * @property string $keywords
 * @property string $lang
 * @property string $theme
 * @property string|null $missingTheme
 * @property int $pageSize
 * @property int $serverTimezone
 * @property int $timezone
 * @property string $charset
 * @property string $contentType
 * @property string $generator
 * @property string $software
 * @property string $version
 * @property bool $markdown
 * @property bool $xmlrpcMarkdown
 * @property array $allowedAttachmentTypes
 * @property string $attachmentTypes
 * @property int $time
 * @property string $frontPage
 * @property int $commentsListSize
 * @property bool $commentsShowCommentOnly
 * @property string $actionTable
 * @property string $panelTable
 * @property bool $commentsThreaded
 * @property bool $defaultAllowComment
 * @property bool $defaultAllowPing
 * @property bool $defaultAllowFeed
 * @property string $commentDateFormat
 * @property string $commentsAvatarRating
 * @property string $commentsPageDisplay
 * @property int $commentsPageSize
 * @property string $commentsOrder
 * @property bool $commentsMarkdown
 * @property bool $commentsShowUrl
 * @property bool $commentsUrlNofollow
 * @property bool $commentsAvatar
 * @property bool $commentsPageBreak
 * @property bool $commentsRequireModeration
 * @property bool $commentsWhitelist
 * @property bool $commentsRequireMail
 * @property bool $commentsRequireUrl
 * @property bool $commentsCheckReferer
 * @property bool $commentsAntiSpam
 * @property bool $commentsAutoClose
 * @property bool $commentsPostIntervalEnable
 * @property string $commentsHTMLTagAllowed
 * @property bool $allowRegister
 * @property bool $allowXmlRpc
 * @property int $postsListSize
 * @property bool $feedFullText
 * @property int $defaultCategory
 * @property bool $frontArchive
 * @property array $plugins
 * @property string $secret
 * @property bool $installed
 */
class Options extends Base
{
    /**
     * キャッシュのためのプラグイン設定
     *
     * @access private
     * @var array
     */
    private $pluginConfig = [];

    /**
     * キャッシュのためのプラグイン設定
     *
     * @access private
     * @var array
     */
    private $personalPluginConfig = [];

    /**
     * @param int $components
     */
    protected function initComponents(int &$components)
    {
        $components = self::INIT_NONE;
    }

    /**
     * @param Config $parameter
     */
    protected function initParameter(Config $parameter)
    {
        if (!$parameter->isEmpty()) {
            $this->row = $this->parameter->toArray();
        } else {
            $this->db = Db::get();
        }
    }

    /**
     * 実行可能関数
     *
     * @throws DbException
     */
    public function execute()
    {
        if (isset($this->db)) {
            $values = $this->db->fetchAll($this->db->select()->from('table.options')
                ->where('user = 0'), [$this, 'push']);

            // finish install
            if (empty($values)) {
                $this->response->redirect(defined('__TYPECHO_ADMIN__')
                    ? '../install.php?step=3' : 'install.php?step=3');
            }
        }

        /** スキン変数のオーバーロードをサポート */
        if (!empty($this->row['theme:' . $this->row['theme']])) {
            $themeOptions = null;

            /** 解決変数 */
            if ($themeOptions = unserialize($this->row['theme:' . $this->row['theme']])) {
                /** オーバーライド変数 */
                $this->row = array_merge($this->row, $themeOptions);
            }
        }

        $this->stack[] = &$this->row;

        /** ルート・ディレクトリの動的取得 */
        $this->rootUrl = defined('__TYPECHO_ROOT_URL__') ? __TYPECHO_ROOT_URL__ : $this->request->getRequestRoot();
        if (defined('__TYPECHO_ADMIN__')) {
            /** での認識adminカタログの状況 */
            $adminDir = '/' . trim(defined('__TYPECHO_ADMIN_DIR__') ? __TYPECHO_ADMIN_DIR__ : '/admin/', '/');
            $this->rootUrl = substr($this->rootUrl, 0, - strlen($adminDir));
        }

        /** サイト情報の初期化 */
        if (defined('__TYPECHO_SITE_URL__')) {
            $this->siteUrl = __TYPECHO_SITE_URL__;
        } elseif (defined('__TYPECHO_DYNAMIC_SITE_URL__') && __TYPECHO_DYNAMIC_SITE_URL__) {
            $this->siteUrl = $this->rootUrl;
        }

        $this->originalSiteUrl = $this->siteUrl;
        $this->siteUrl = Common::url(null, $this->siteUrl);
        $this->plugins = unserialize($this->plugins);

        /** ダイナミックジャッジメントスキンカタログ */
        $this->missingTheme = null;

        if (!is_dir($this->themeFile($this->theme))) {
            $this->missingTheme = $this->theme;
            $this->theme = 'default';
        }

        /** 認知度を高めるSSL接続サポート */
        if ($this->request->isSecure() && 0 === strpos($this->siteUrl, 'http://')) {
            $this->siteUrl = substr_replace($this->siteUrl, 'https', 0, 4);
        }

        /** ルーティングテーブルの自動初期化 */
        $this->routingTable = unserialize($this->routingTable);
        if (isset($this->db) && !isset($this->routingTable[0])) {
            /** ルートを解析してキャッシュする */
            $parser = new Parser($this->routingTable);
            $parsedRoutingTable = $parser->parse();
            $this->routingTable = array_merge([$parsedRoutingTable], $this->routingTable);
            $this->db->query($this->db->update('table.options')->rows(['value' => serialize($this->routingTable)])
                ->where('name = ?', 'routingTable'));
        }
    }

    /**
     * スキンファイルを入手する
     *
     * @param string $theme
     * @param string $file
     * @return string
     */
    public function themeFile(string $theme, string $file = ''): string
    {
        return __TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__ . '/' . trim($theme, './') . '/' . trim($file, './');
    }

    /**
     * 親クラスのオーバーロードpush関数,すべての変数値をスタックに押し込む
     *
     * @param array $value 1行あたりの価値
     * @return array
     */
    public function push(array $value): array
    {
        //行データを順番にセットする
        $this->row[$value['name']] = $value['value'];
        return $value;
    }

    /**
     * サイトパスのエクスポート
     *
     * @param string|null $path サブパス
     */
    public function siteUrl(?string $path = null)
    {
        echo Common::url($path, $this->siteUrl);
    }

    /**
     * 出力解析アドレス
     *
     * @param string|null $path サブパス
     */
    public function index(?string $path = null)
    {
        echo Common::url($path, $this->index);
    }

    /**
     * 出力テンプレートのパス
     *
     * @param string|null $path サブパス
     * @param string|null $theme テンプレート名
     * @return string | void
     */
    public function themeUrl(?string $path = null, ?string $theme = null)
    {
        if (!isset($theme)) {
            echo Common::url($path, $this->themeUrl);
        } else {
            $url = defined('__TYPECHO_THEME_URL__') ? __TYPECHO_THEME_URL__ :
                Common::url(__TYPECHO_THEME_DIR__ . '/' . $theme, $this->siteUrl);

            return isset($path) ? Common::url($path, $url) : $url;
        }
    }

    /**
     * 出力プラグインパス
     *
     * @param string|null $path サブパス
     */
    public function pluginUrl(?string $path = null)
    {
        echo Common::url($path, $this->pluginUrl);
    }

    /**
     * プラグインディレクトリの取得
     *
     * @param string|null $plugin
     * @return string
     */
    public function pluginDir(?string $plugin = null): string
    {
        return Common::url($plugin, $this->pluginDir);
    }

    /**
     * エクスポート・バックエンド・パス
     *
     * @param string|null $path サブパス
     * @param bool $return
     * @return void|string
     */
    public function adminUrl(?string $path = null, bool $return = false)
    {
        $url = Common::url($path, $this->adminUrl);

        if ($return) {
            return $url;
        }

        echo $url;
    }

    /**
     * バックエンドの静的ファイルパスの取得またはエクスポート
     *
     * @param string $type
     * @param string|null $file
     * @param bool $return
     * @return void|string
     */
    public function adminStaticUrl(string $type, ?string $file = null, bool $return = false)
    {
        $url = Common::url($type, $this->adminUrl);

        if (empty($file)) {
            return $url;
        }

        $url = Common::url($file, $url) . '?v=' . $this->version;

        if ($return) {
            return $url;
        }

        echo $url;
    }

    /**
     * エンコードされた出力はhtmlタブ
     */
    public function commentsHTMLTagAllowed()
    {
        echo htmlspecialchars($this->commentsHTMLTagAllowed);
    }

    /**
     * プラグイン・システム・パラメーターの取得
     *
     * @param mixed $pluginName プラグイン名
     * @return mixed
     * @throws PluginException
     */
    public function plugin($pluginName)
    {
        if (!isset($this->pluginConfig[$pluginName])) {
            if (
                !empty($this->row['plugin:' . $pluginName])
                && false !== ($options = unserialize($this->row['plugin:' . $pluginName]))
            ) {
                $this->pluginConfig[$pluginName] = new Config($options);
            } else {
                throw new PluginException(_t('プラグイン%sのコンフィギュレーション情報。', $pluginName), 500);
            }
        }

        return $this->pluginConfig[$pluginName];
    }

    /**
     * ゲイン个人プラグイン系统参数
     *
     * @param mixed $pluginName プラグイン名
     *
     * @return mixed
     * @throws PluginException
     */
    public function personalPlugin($pluginName)
    {
        if (!isset($this->personalPluginConfig[$pluginName])) {
            if (
                !empty($this->row['_plugin:' . $pluginName])
                && false !== ($options = unserialize($this->row['_plugin:' . $pluginName]))
            ) {
                $this->personalPluginConfig[$pluginName] = new Config($options);
            } else {
                throw new PluginException(_t('プラグイン%sのコンフィギュレーション情報。', $pluginName), 500);
            }
        }

        return $this->personalPluginConfig[$pluginName];
    }

    /**
     * RSS2.0
     *
     * @return string
     */
    protected function ___feedUrl(): string
    {
        return Router::url('feed', ['feed' => '/'], $this->index);
    }

    /**
     * RSS1.0
     *
     * @return string
     */
    protected function ___feedRssUrl(): string
    {
        return Router::url('feed', ['feed' => '/rss/'], $this->index);
    }

    /**
     * ATOM1.O
     *
     * @return string
     */
    protected function ___feedAtomUrl(): string
    {
        return Router::url('feed', ['feed' => '/atom/'], $this->index);
    }

    /**
     * 解説RSS2.0重合
     *
     * @return string
     */
    protected function ___commentsFeedUrl(): string
    {
        return Router::url('feed', ['feed' => '/comments/'], $this->index);
    }

    /**
     * 解説RSS1.0重合
     *
     * @return string
     */
    protected function ___commentsFeedRssUrl(): string
    {
        return Router::url('feed', ['feed' => '/rss/comments/'], $this->index);
    }

    /**
     * 解説ATOM1.0重合
     *
     * @return string
     */
    protected function ___commentsFeedAtomUrl(): string
    {
        return Router::url('feed', ['feed' => '/atom/comments/'], $this->index);
    }

    /**
     * xmlrpc apiアドレス
     *
     * @return string
     */
    protected function ___xmlRpcUrl(): string
    {
        return Router::url('do', ['action' => 'xmlrpc'], $this->index);
    }

    /**
     * 解決パスの接頭辞を取得する
     *
     * @return string
     */
    protected function ___index(): string
    {
        return ($this->rewrite || (defined('__TYPECHO_REWRITE__') && __TYPECHO_REWRITE__))
            ? $this->rootUrl : Common::url('index.php', $this->rootUrl);
    }

    /**
     * テンプレートパスの取得
     *
     * @return string
     */
    protected function ___themeUrl(): string
    {
        return $this->themeUrl(null, $this->theme);
    }

    /**
     * ゲインプラグイン路径
     *
     * @return string
     */
    protected function ___pluginUrl(): string
    {
        return defined('__TYPECHO_PLUGIN_URL__') ? __TYPECHO_PLUGIN_URL__ :
            Common::url(__TYPECHO_PLUGIN_DIR__, $this->siteUrl);
    }

    /**
     * @return string
     */
    protected function ___pluginDir(): string
    {
        return Common::url(__TYPECHO_PLUGIN_DIR__, __TYPECHO_ROOT_DIR__);
    }

    /**
     * バックエンドのパスを取得する
     *
     * @return string
     */
    protected function ___adminUrl(): string
    {
        return Common::url(defined('__TYPECHO_ADMIN_DIR__') ?
            __TYPECHO_ADMIN_DIR__ : '/admin/', $this->rootUrl);
    }

    /**
     * ゲイン登录アドレス
     *
     * @return string
     */
    protected function ___loginUrl(): string
    {
        return Common::url('login.php', $this->adminUrl);
    }

    /**
     * ゲイン登录提交アドレス
     *
     * @return string
     */
    protected function ___loginAction(): string
    {
        return Security::alloc()->getTokenUrl(
            Router::url(
                'do',
                ['action' => 'login', 'widget' => 'Login'],
                Common::url('index.php', $this->rootUrl)
            )
        );
    }

    /**
     * ゲイン注册アドレス
     *
     * @return string
     */
    protected function ___registerUrl(): string
    {
        return Common::url('register.php', $this->adminUrl);
    }

    /**
     * ゲイン登录提交アドレス
     *
     * @return string
     * @throws Widget\Exception
     */
    protected function ___registerAction(): string
    {
        return Security::alloc()->getTokenUrl(
            Router::url('do', ['action' => 'register', 'widget' => 'Register'], $this->index)
        );
    }

    /**
     * ゲイン个人档案アドレス
     *
     * @return string
     */
    protected function ___profileUrl(): string
    {
        return Common::url('profile.php', $this->adminUrl);
    }

    /**
     * ゲイン登出アドレス
     *
     * @return string
     */
    protected function ___logoutUrl(): string
    {
        return Security::alloc()->getTokenUrl(
            Common::url('/action/logout', $this->index)
        );
    }

    /**
     * システムのタイムゾーンを取得する
     *
     * @return integer
     */
    protected function ___serverTimezone(): int
    {
        return Date::$serverTimezoneOffset;
    }

    /**
     * ゲインGMT標準時
     *
     * @return integer
     * @deprecated
     */
    protected function ___gmtTime(): int
    {
        return Date::gmtTime();
    }

    /**
     * ゲイン时间
     *
     * @return integer
     * @deprecated
     */
    protected function ___time(): int
    {
        return Date::time();
    }

    /**
     * ゲイン格式
     *
     * @return string
     */
    protected function ___contentType(): string
    {
        return $this->contentType ?? 'text/html';
    }

    /**
     * ソフトウェア名
     *
     * @return string
     */
    protected function ___software(): string
    {
        [$software, $version] = explode(' ', $this->generator);
        return $software;
    }

    /**
     * ソフトウェアバージョン
     *
     * @return string
     */
    protected function ___version(): string
    {
        [$software, $version] = explode(' ', $this->generator);
        $pos = strpos($version, '/');

        // fix for old version
        if ($pos !== false) {
            $version = substr($version, 0, $pos);
        }

        return $version;
    }

    /**
     * アップロード可能なファイルタイプ
     *
     * @return array
     */
    protected function ___allowedAttachmentTypes(): array
    {
        $attachmentTypesResult = [];

        if (null != $this->attachmentTypes) {
            $attachmentTypes = str_replace(
                ['@image@', '@media@', '@doc@'],
                [
                    'gif,jpg,jpeg,png,tiff,bmp,webp', 'mp3,mp4,mov,wmv,wma,rmvb,rm,avi,flv,ogg,oga,ogv',
                    'txt,doc,docx,xls,xlsx,ppt,pptx,zip,rar,pdf'
                ],
                $this->attachmentTypes
            );

            $attachmentTypesResult = array_unique(array_map('trim', preg_split("/(,|\.)/", $attachmentTypes)));
        }

        return $attachmentTypesResult;
    }
}
