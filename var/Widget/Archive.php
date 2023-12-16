<?php

namespace Widget;

use Typecho\Common;
use Typecho\Config;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Db\Query;
use Typecho\Feed;
use Typecho\Router;
use Typecho\Widget\Exception as WidgetException;
use Typecho\Widget\Helper\PageNavigator;
use Typecho\Widget\Helper\PageNavigator\Classic;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Contents;
use Widget\Base\Metas;
use Widget\Comments\Ping;
use Widget\Comments\Recent;
use Widget\Contents\Attachment\Related;
use Widget\Contents\Related\Author;
use Widget\Metas\Category\Rows;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コンテンツの記事ベース・クラス
 * 定義済みcss類似
 * p.more:リンク先の段落についてもっと読む
 *
 * @package Widget
 */
class Archive extends Contents
{
    /**
     * 呼び出されたスタイル・ファイル
     *
     * @var string
     */
    private $themeFile;

    /**
     * スタイルカタログ
     *
     * @var string
     */
    private $themeDir;

    /**
     * ページングターゲット
     *
     * @var Query
     */
    private $countSql;

    /**
     * 全記事数
     *
     * @var integer
     */
    private $total = false;

    /**
     * 外部からの通話かどうかのフラグ
     *
     * @var boolean
     */
    private $invokeFromOutside = false;

    /**
     * アグリゲーターから呼び出されるかどうか
     *
     * @var boolean
     */
    private $invokeByFeed = false;

    /**
     * 現在のページ
     *
     * @var integer
     */
    private $currentPage;

    /**
     * ページネーション用コンテンツの生成
     *
     * @var array
     */
    private $pageRow = [];

    /**
     * アグリゲータオブジェクト
     *
     * @var Feed
     */
    private $feed;

    /**
     * RSS 2.0重合アドレス
     *
     * @var string
     */
    private $feedUrl;

    /**
     * RSS 1.0重合アドレス
     *
     * @var string
     */
    private $feedRssUrl;

    /**
     * ATOM 重合アドレス
     *
     * @var string
     */
    private $feedAtomUrl;

    /**
     * このページのキーワード
     *
     * @var string
     */
    private $keywords;

    /**
     * このページの説明
     *
     * @var string
     */
    private $description;

    /**
     * 聚合類似型
     *
     * @var string
     */
    private $feedType;

    /**
     * 聚合類似型
     *
     * @var string
     */
    private $feedContentType;

    /**
     * 対すfeedアドレス
     *
     * @var string
     */
    private $currentFeedUrl;

    /**
     * ファイルタイトル
     *
     * @var string
     */
    private $archiveTitle = null;

    /**
     * 归档アドレス
     *
     * @var string|null
     */
    private $archiveUrl = null;

    /**
     * 归档類似型
     *
     * @var string
     */
    private $archiveType = 'index';

    /**
     * 単一のアーカイブか
     *
     * @var string
     */
    private $archiveSingle = false;

    /**
     * カスタム・ホームページ, 主にカスタム・ホームページをマークするため
     *
     * (default value: false)
     *
     * @var boolean
     * @access private
     */
    private $makeSinglePageAsFrontPage = false;

    /**
     * ファイル略称
     *
     * @access private
     * @var string
     */
    private $archiveSlug;

    /**
     * ページング・オブジェクトの設定
     *
     * @access private
     * @var PageNavigator
     */
    private $pageNav;

    /**
     * @param Config $parameter
     * @throws \Exception
     */
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault([
            'pageSize'       => $this->options->pageSize,
            'type'           => null,
            'checkPermalink' => true,
            'preview'        => false
        ]);

        /** ルーティングコールか外線通話かを判断するために使用される。 */
        if (null == $parameter->type) {
            $parameter->type = Router::$current;
        } else {
            $this->invokeFromOutside = true;
        }

        /** を決定するために使用される。feed各論 */
        if ($parameter->isFeed) {
            $this->invokeByFeed = true;
        }

        /** スキンパスの初期化 */
        $this->themeDir = rtrim($this->options->themeFile($this->options->theme), '/') . '/';

        /** 扱うfeedパラダイム **/
        if ('feed' == $parameter->type) {
            $this->currentFeedUrl = '';

            /** 判断聚合類似型 */
            switch (true) {
                case 0 === strpos($this->request->feed, '/rss/') || '/rss' == $this->request->feed:
                    /** もしそうならRSS1規範 */
                    $this->request->feed = substr($this->request->feed, 4);
                    $this->feedType = Feed::RSS1;
                    $this->currentFeedUrl = $this->options->feedRssUrl;
                    $this->feedContentType = 'application/rdf+xml';
                    break;
                case 0 === strpos($this->request->feed, '/atom/') || '/atom' == $this->request->feed:
                    /** もしそうならATOM規範 */
                    $this->request->feed = substr($this->request->feed, 5);
                    $this->feedType = Feed::ATOM1;
                    $this->currentFeedUrl = $this->options->feedAtomUrl;
                    $this->feedContentType = 'application/atom+xml';
                    break;
                default:
                    $this->feedType = Feed::RSS2;
                    $this->currentFeedUrl = $this->options->feedUrl;
                    $this->feedContentType = 'application/rss+xml';
                    break;
            }

            $feedQuery = $this->request->feed;
            //$parameter->type = Router::$current;
            //$this->request->setParams($params);

            if ('/comments/' == $feedQuery || '/comments' == $feedQuery) {
                /** のために作られた。feed応用hack */
                $parameter->type = 'comments';
                $this->options->feedUrl = $this->options->commentsFeedUrl;
                $this->options->feedRssUrl = $this->options->commentsFeedRssUrl;
                $this->options->feedAtomUrl = $this->options->commentsFeedAtomUrl;
            } else {
                $matched = Router::match($this->request->feed, 'pageSize=10&isFeed=1');
                if ($matched instanceof Archive) {
                    $this->import($matched);
                } else {
                    throw new WidgetException(_t('重合ページが存在しない'), 404);
                }
            }

            /** アグリゲーターの初期化 */
            $this->setFeed(new Feed(Common::VERSION, $this->feedType, $this->options->charset, _t('zh-CN')));

            /** デフォルト出力10定型 **/
            $parameter->pageSize = 10;
        }
    }

    /**
     * タイトル追加
     * @param string $archiveTitle キャプション
     */
    public function addArchiveTitle(string $archiveTitle)
    {
        $current = $this->getArchiveTitle();
        $current[] = $archiveTitle;
        $this->setArchiveTitle($current);
    }

    /**
     * @return string
     */
    public function getArchiveTitle(): ?string
    {
        return $this->archiveTitle;
    }

    /**
     * @param string $archiveTitle the $archiveTitle to set
     */
    public function setArchiveTitle(string $archiveTitle)
    {
        $this->archiveTitle = $archiveTitle;
    }

    /**
     * ページング・オブジェクトの取得
     * @return array
     */
    public function getPageRow(): array
    {
        return $this->pageRow;
    }

    /**
     * ページング・オブジェクトの設定
     * @param array $pageRow
     */
    public function setPageRow(array $pageRow)
    {
        $this->pageRow = $pageRow;
    }

    /**
     * @return string|null
     */
    public function getArchiveSlug(): ?string
    {
        return $this->archiveSlug;
    }

    /**
     * @param string $archiveSlug the $archiveSlug to set
     */
    public function setArchiveSlug(string $archiveSlug)
    {
        $this->archiveSlug = $archiveSlug;
    }

    /**
     * @return string|null
     */
    public function getArchiveSingle(): ?string
    {
        return $this->archiveSingle;
    }

    /**
     * @param string $archiveSingle the $archiveSingle to set
     */
    public function setArchiveSingle(string $archiveSingle)
    {
        $this->archiveSingle = $archiveSingle;
    }

    /**
     * @return string|null
     */
    public function getArchiveType(): ?string
    {
        return $this->archiveType;
    }

    /**
     * @param string $archiveType the $archiveType to set
     */
    public function setArchiveType(string $archiveType)
    {
        $this->archiveType = $archiveType;
    }

    /**
     * @return string|null
     */
    public function getArchiveUrl(): ?string
    {
        return $this->archiveUrl;
    }

    /**
     * @param string|null $archiveUrl
     */
    public function setArchiveUrl(?string $archiveUrl): void
    {
        $this->archiveUrl = $archiveUrl;
    }

    /**
     * @return string|null
     */
    public function getFeedType(): ?string
    {
        return $this->feedType;
    }

    /**
     * @param string $feedType the $feedType to set
     */
    public function setFeedType(string $feedType)
    {
        $this->feedType = $feedType;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description the $description to set
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    /**
     * @param string $keywords the $keywords to set
     */
    public function setKeywords(string $keywords)
    {
        $this->keywords = $keywords;
    }

    /**
     * @return string
     */
    public function getFeedAtomUrl(): string
    {
        return $this->feedAtomUrl;
    }

    /**
     * @param string $feedAtomUrl the $feedAtomUrl to set
     */
    public function setFeedAtomUrl(string $feedAtomUrl)
    {
        $this->feedAtomUrl = $feedAtomUrl;
    }

    /**
     * @return string
     */
    public function getFeedRssUrl(): string
    {
        return $this->feedRssUrl;
    }

    /**
     * @param string $feedRssUrl the $feedRssUrl to set
     */
    public function setFeedRssUrl(string $feedRssUrl)
    {
        $this->feedRssUrl = $feedRssUrl;
    }

    /**
     * @return string
     */
    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    /**
     * @param string $feedUrl the $feedUrl to set
     */
    public function setFeedUrl(string $feedUrl)
    {
        $this->feedUrl = $feedUrl;
    }

    /**
     * @return Feed
     */
    public function getFeed(): Feed
    {
        return $this->feed;
    }

    /**
     * @param Feed $feed the $feed to set
     */
    public function setFeed(Feed $feed)
    {
        $this->feed = $feed;
    }

    /**
     * @return Query|null
     */
    public function getCountSql(): ?Query
    {
        return $this->countSql;
    }

    /**
     * @param Query $countSql the $countSql to set
     */
    public function setCountSql($countSql)
    {
        $this->countSql = $countSql;
    }

    /**
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * _currentPage
     *
     * @return int
     */
    public function ____currentPage(): int
    {
        return $this->getCurrentPage();
    }

    /**
     * ページ取得
     *
     * @return integer
     */
    public function getTotalPage(): int
    {
        return ceil($this->getTotal() / $this->parameter->pageSize);
    }

    /**
     * @return int
     * @throws Db\Exception
     */
    public function getTotal(): int
    {
        if (false === $this->total) {
            $this->total = $this->size($this->countSql);
        }

        return $this->total;
    }

    /**
     * @param int $total the $total to set
     */
    public function setTotal(int $total)
    {
        $this->total = $total;
    }

    /**
     * @return string|null
     */
    public function getThemeFile(): ?string
    {
        return $this->themeFile;
    }

    /**
     * @param string $themeFile the $themeFile to set
     */
    public function setThemeFile(string $themeFile)
    {
        $this->themeFile = $themeFile;
    }

    /**
     * @return string|null
     */
    public function getThemeDir(): ?string
    {
        return $this->themeDir;
    }

    /**
     * @param string $themeDir the $themeDir to set
     */
    public function setThemeDir(string $themeDir)
    {
        $this->themeDir = $themeDir;
    }

    /**
     * 実行可能関数
     */
    public function execute()
    {
        /** データの重複取得を避ける */
        if ($this->have()) {
            return;
        }

        $handles = [
            'index'              => 'indexHandle',
            'index_page'         => 'indexHandle',
            'archive'            => 'archiveEmptyHandle',
            'archive_page'       => 'archiveEmptyHandle',
            404                  => 'error404Handle',
            'single'             => 'singleHandle',
            'page'               => 'singleHandle',
            'post'               => 'singleHandle',
            'attachment'         => 'singleHandle',
            'comment_page'       => 'singleHandle',
            'category'           => 'categoryHandle',
            'category_page'      => 'categoryHandle',
            'tag'                => 'tagHandle',
            'tag_page'           => 'tagHandle',
            'author'             => 'authorHandle',
            'author_page'        => 'authorHandle',
            'archive_year'       => 'dateHandle',
            'archive_year_page'  => 'dateHandle',
            'archive_month'      => 'dateHandle',
            'archive_month_page' => 'dateHandle',
            'archive_day'        => 'dateHandle',
            'archive_day_page'   => 'dateHandle',
            'search'             => 'searchHandle',
            'search_page'        => 'searchHandle'
        ];

        /** 扱う搜索结果跳转 */
        if (isset($this->request->s)) {
            $filterKeywords = $this->request->filter('search')->get('s');

            /** 検索ページにジャンプ */
            if (null != $filterKeywords) {
                $this->response->redirect(
                    Router::url('search', ['keywords' => urlencode($filterKeywords)], $this->options->index)
                );
            }
        }

        /** カスタマイズされたホームページ機能 */
        $frontPage = $this->options->frontPage;
        if (!$this->invokeByFeed && ('index' == $this->parameter->type || 'index_page' == $this->parameter->type)) {
            //ページを表示する
            if (0 === strpos($frontPage, 'page:')) {
                // するhack
                $this->request->setParam('cid', intval(substr($frontPage, 5)));
                $this->parameter->type = 'page';
                $this->makeSinglePageAsFrontPage = true;
            } elseif (0 === strpos($frontPage, 'file:')) {
                // ファイルを表示する
                $this->setThemeFile(substr($frontPage, 5));
                return;
            }
        }

        if ('recent' != $frontPage && $this->options->frontArchive) {
            $handles['archive'] = 'indexHandle';
            $handles['archive_page'] = 'indexHandle';
            $this->archiveType = 'front';
        }

        /** ページング変数の初期化 */
        $this->currentPage = $this->request->filter('int')->page ?? 1;
        $hasPushed = false;

        /** select初期化 */
        $select = self::pluginHandle()->trigger($selectPlugged)->select($this);

        /** 時限リリース機能 */
        if (!$selectPlugged) {
            if ($this->parameter->preview) {
                $select = $this->select();
            } else {
                if ('post' == $this->parameter->type || 'page' == $this->parameter->type) {
                    if ($this->user->hasLogin()) {
                        $select = $this->select()->where(
                            'table.contents.status = ? OR table.contents.status = ? 
                                OR (table.contents.status = ? AND table.contents.authorId = ?)',
                            'publish',
                            'hidden',
                            'private',
                            $this->user->uid
                        );
                    } else {
                        $select = $this->select()->where(
                            'table.contents.status = ? OR table.contents.status = ?',
                            'publish',
                            'hidden'
                        );
                    }
                } else {
                    if ($this->user->hasLogin()) {
                        $select = $this->select()->where(
                            'table.contents.status = ? OR (table.contents.status = ? AND table.contents.authorId = ?)',
                            'publish',
                            'private',
                            $this->user->uid
                        );
                    } else {
                        $select = $this->select()->where('table.contents.status = ?', 'publish');
                    }
                }
                $select->where('table.contents.created < ?', $this->options->time);
            }
        }

        /** handle初期化 */
        self::pluginHandle()->handleInit($this, $select);

        /** 初期化其它变量 */
        $this->feedUrl = $this->options->feedUrl;
        $this->feedRssUrl = $this->options->feedRssUrl;
        $this->feedAtomUrl = $this->options->feedAtomUrl;
        $this->keywords = $this->options->keywords;
        $this->description = $this->options->description;
        $this->archiveUrl = $this->options->siteUrl;

        if (isset($handles[$this->parameter->type])) {
            $handle = $handles[$this->parameter->type];
            $this->{$handle}($select, $hasPushed);
        } else {
            $hasPushed = self::pluginHandle()->handle($this->parameter->type, $this, $select);
        }

        /** 初期化皮肤函数 */
        $functionsFile = $this->themeDir . 'functions.php';
        if (
            (!$this->invokeFromOutside || $this->parameter->type == 404 || $this->parameter->preview)
            && file_exists($functionsFile)
        ) {
            require_once $functionsFile;
            if (function_exists('themeInit')) {
                themeInit($this);
            }
        }

        /** もし早くからプレスされていた場合は、そのまま返却される。 */
        if ($hasPushed) {
            return;
        }

        /** 出力記事のみ */
        $this->countSql = clone $select;

        $select->order('table.contents.created', Db::SORT_DESC)
            ->page($this->currentPage, $this->parameter->pageSize);
        $this->query($select);

        /** 扱う超出分页な情况 */
        if ($this->currentPage > 1 && !$this->have()) {
            throw new WidgetException(_t('请求なアドレス不存在'), 404);
        }
    }

    /**
     * におもselect
     *
     * @return Query
     * @throws Db\Exception
     */
    public function select(): Query
    {
        if ($this->invokeByFeed) {
            // 右feed制約を追加する出力
            return parent::select()->where('table.contents.allowFeed = ?', 1)
                ->where("table.contents.password IS NULL OR table.contents.password = ''");
        } else {
            return parent::select();
        }
    }

    /**
     * 記事内容のエクスポート
     *
     * @param string $more 記事インターセプト接尾辞
     */
    public function content($more = null)
    {
        parent::content($this->is('single') ? false : $more);
    }

    /**
     * 出力ページング
     *
     * @param string $prev 前のテキスト
     * @param string $next 次の文章
     * @param int $splitPage セグメンテーションの範囲
     * @param string $splitWord 分割文字
     * @param string|array $template 設定情報を表示する
     * @throws Db\Exception|WidgetException
     */
    public function pageNav(
        string $prev = '&laquo;',
        string $next = '&raquo;',
        int $splitPage = 3,
        string $splitWord = '...',
        $template = ''
    ) {
        if ($this->have()) {
            $hasNav = false;
            $default = [
                'wrapTag'   => 'ol',
                'wrapClass' => 'page-navigator'
            ];

            if (is_string($template)) {
                parse_str($template, $config);
            } else {
                $config = $template ?: [];
            }

            $template = array_merge($default, $config);
            $total = $this->getTotal();
            $query = Router::url(
                $this->parameter->type .
                (false === strpos($this->parameter->type, '_page') ? '_page' : null),
                $this->pageRow,
                $this->options->index
            );

            self::pluginHandle()->trigger($hasNav)->pageNav(
                $this->currentPage,
                $total,
                $this->parameter->pageSize,
                $prev,
                $next,
                $splitPage,
                $splitWord,
                $template,
                $query
            );

            if (!$hasNav && $total > $this->parameter->pageSize) {
                /** ボックス・ページングの使用 */
                $nav = new Box(
                    $total,
                    $this->currentPage,
                    $this->parameter->pageSize,
                    $query
                );

                echo '<' . $template['wrapTag'] . (empty($template['wrapClass'])
                        ? '' : ' class="' . $template['wrapClass'] . '"') . '>';
                $nav->render($prev, $next, $splitPage, $splitWord, $template);
                echo '</' . $template['wrapTag'] . '>';
            }
        }
    }

    /**
     * 前のページ
     *
     * @param string $word 链接キャプション
     * @param string $page ウェブリンク
     * @throws Db\Exception|WidgetException
     */
    public function pageLink(string $word = '&laquo; Previous Entries', string $page = 'prev')
    {
        if ($this->have()) {
            if (empty($this->pageNav)) {
                $query = Router::url(
                    $this->parameter->type .
                    (false === strpos($this->parameter->type, '_page') ? '_page' : null),
                    $this->pageRow,
                    $this->options->index
                );

                /** ボックス・ページングの使用 */
                $this->pageNav = new Classic(
                    $this->getTotal(),
                    $this->currentPage,
                    $this->parameter->pageSize,
                    $query
                );
            }

            $this->pageNav->{$page}($word);
        }
    }

    /**
     * 获取解説归档右象
     *
     * @access public
     * @return \Widget\Comments\Archive
     */
    public function comments(): \Widget\Comments\Archive
    {
        $parameter = [
            'parentId'      => $this->hidden ? 0 : $this->cid,
            'parentContent' => $this->row,
            'respondId'     => $this->respondId,
            'commentPage'   => $this->request->filter('int')->commentPage,
            'allowComment'  => $this->allow('comment')
        ];

        return \Widget\Comments\Archive::alloc($parameter);
    }

    /**
     * 获取回响归档右象
     *
     * @return Ping
     */
    public function pings(): Ping
    {
        return Ping::alloc([
            'parentId'      => $this->hidden ? 0 : $this->cid,
            'parentContent' => $this->row,
            'allowPing'     => $this->allow('ping')
        ]);
    }

    /**
     * 获取附件右象
     *
     * @param integer $limit 最大数
     * @param integer $offset 考え直す
     * @return Related
     */
    public function attachments(int $limit = 0, int $offset = 0): Related
    {
        return Related::allocWithAlias($this->cid . '-' . uniqid(), [
            'parentId' => $this->cid,
            'limit'    => $limit,
            'offset'   => $offset
        ]);
    }

    /**
     * 显示下一个内容なキャプション链接
     *
     * @param string $format フォーマッティング
     * @param string|null $default 次がない場合,表示されるデフォルトテキスト
     * @param array $custom カスタマイズスタイル
     */
    public function theNext(string $format = '%s', ?string $default = null, array $custom = [])
    {
        $content = $this->db->fetchRow($this->select()->where(
            'table.contents.created > ? AND table.contents.created < ?',
            $this->created,
            $this->options->time
        )
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $this->type)
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->order('table.contents.created', Db::SORT_ASC)
            ->limit(1));

        if ($content) {
            $content = $this->filter($content);
            $default = [
                'title'    => null,
                'tagClass' => null
            ];
            $custom = array_merge($default, $custom);
            extract($custom);

            $linkText = empty($title) ? $content['title'] : $title;
            $linkClass = empty($tagClass) ? '' : 'class="' . $tagClass . '" ';
            $link = '<a ' . $linkClass . 'href="' . $content['permalink']
                . '" title="' . $content['title'] . '">' . $linkText . '</a>';

            printf($format, $link);
        } else {
            echo $default;
        }
    }

    /**
     * 显示上一个内容なキャプション链接
     *
     * @access public
     * @param string $format フォーマッティング
     * @param string $default 前歴がない場合,表示されるデフォルトテキスト
     * @param array $custom カスタマイズスタイル
     * @return void
     */
    public function thePrev($format = '%s', $default = null, $custom = [])
    {
        $content = $this->db->fetchRow($this->select()->where('table.contents.created < ?', $this->created)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $this->type)
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->order('table.contents.created', Db::SORT_DESC)
            ->limit(1));

        if ($content) {
            $content = $this->filter($content);
            $default = [
                'title'    => null,
                'tagClass' => null
            ];
            $custom = array_merge($default, $custom);
            extract($custom);

            $linkText = empty($title) ? $content['title'] : $title;
            $linkClass = empty($tagClass) ? '' : 'class="' . $tagClass . '" ';
            $link = '<a ' . $linkClass . 'href="' . $content['permalink'] . '" title="' . $content['title'] . '">' . $linkText . '</a>';

            printf($format, $link);
        } else {
            echo $default;
        }
    }

    /**
     * 関連するコンテンツ・コンポーネントを取得する
     *
     * @param integer $limit 出力数
     * @param string|null $type 关联類似型
     * @return Contents
     */
    public function related(int $limit = 5, ?string $type = null): Contents
    {
        $type = strtolower($type ?? '');

        switch ($type) {
            case 'author':
                /** アクセス権が禁止に設定されている場合,原則tagはNULLに設定される */
                return Author::alloc(
                    ['cid' => $this->cid, 'type' => $this->type, 'author' => $this->author->uid, 'limit' => $limit]
                );
            default:
                /** アクセス権が禁止に設定されている場合,原則tagはNULLに設定される */
                return \Widget\Contents\Related::alloc(
                    ['cid' => $this->cid, 'type' => $this->type, 'tags' => $this->tags, 'limit' => $limit]
                );
        }
    }

    /**
     * 出力ヘッダーのメタデータ
     *
     * @param string|null $rule 规原則
     */
    public function header(?string $rule = null)
    {
        $rules = [];
        $allows = [
            'description'  => htmlspecialchars($this->description ?? ''),
            'keywords'     => htmlspecialchars($this->keywords ?? ''),
            'generator'    => $this->options->generator,
            'template'     => $this->options->theme,
            'pingback'     => $this->options->xmlRpcUrl,
            'xmlrpc'       => $this->options->xmlRpcUrl . '?rsd',
            'wlw'          => $this->options->xmlRpcUrl . '?wlw',
            'rss2'         => $this->feedUrl,
            'rss1'         => $this->feedRssUrl,
            'commentReply' => 1,
            'antiSpam'     => 1,
            'atom'         => $this->feedAtomUrl
        ];

        /** ヘッダーが集約を出力するかどうか */
        $allowFeed = !$this->is('single') || $this->allow('feed') || $this->makeSinglePageAsFrontPage;

        if (!empty($rule)) {
            parse_str($rule, $rules);
            $allows = array_merge($allows, $rules);
        }

        $allows = self::pluginHandle()->headerOptions($allows, $this);
        $title = (empty($this->archiveTitle) ? '' : $this->archiveTitle . ' &raquo; ') . $this->options->title;

        $header = '';
        if (!empty($allows['description'])) {
            $header .= '<meta name="description" content="' . $allows['description'] . '" />' . "\n";
        }

        if (!empty($allows['keywords'])) {
            $header .= '<meta name="keywords" content="' . $allows['keywords'] . '" />' . "\n";
        }

        if (!empty($allows['generator'])) {
            $header .= '<meta name="generator" content="' . $allows['generator'] . '" />' . "\n";
        }

        if (!empty($allows['template'])) {
            $header .= '<meta name="template" content="' . $allows['template'] . '" />' . "\n";
        }

        if (!empty($allows['pingback']) && 2 == $this->options->allowXmlRpc) {
            $header .= '<link rel="pingback" href="' . $allows['pingback'] . '" />' . "\n";
        }

        if (!empty($allows['xmlrpc']) && 0 < $this->options->allowXmlRpc) {
            $header .= '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="'
                . $allows['xmlrpc'] . '" />' . "\n";
        }

        if (!empty($allows['wlw']) && 0 < $this->options->allowXmlRpc) {
            $header .= '<link rel="wlwmanifest" type="application/wlwmanifest+xml" href="'
                . $allows['wlw'] . '" />' . "\n";
        }

        if (!empty($allows['rss2']) && $allowFeed) {
            $header .= '<link rel="alternate" type="application/rss+xml" title="'
                . $title . ' &raquo; RSS 2.0" href="' . $allows['rss2'] . '" />' . "\n";
        }

        if (!empty($allows['rss1']) && $allowFeed) {
            $header .= '<link rel="alternate" type="application/rdf+xml" title="'
                . $title . ' &raquo; RSS 1.0" href="' . $allows['rss1'] . '" />' . "\n";
        }

        if (!empty($allows['atom']) && $allowFeed) {
            $header .= '<link rel="alternate" type="application/atom+xml" title="'
                . $title . ' &raquo; ATOM 1.0" href="' . $allows['atom'] . '" />' . "\n";
        }

        if ($this->options->commentsThreaded && $this->is('single')) {
            if ('' != $allows['commentReply']) {
                if (1 == $allows['commentReply']) {
                    $header .= "<script type=\"text/javascript\">
(function () {
    window.TypechoComment = {
        dom : function (id) {
            return document.getElementById(id);
        },
    
        create : function (tag, attr) {
            var el = document.createElement(tag);
        
            for (var key in attr) {
                el.setAttribute(key, attr[key]);
            }
        
            return el;
        },

        reply : function (cid, coid) {
            var comment = this.dom(cid), parent = comment.parentNode,
                response = this.dom('" . $this->respondId . "'), input = this.dom('comment-parent'),
                form = 'form' == response.tagName ? response : response.getElementsByTagName('form')[0],
                textarea = response.getElementsByTagName('textarea')[0];

            if (null == input) {
                input = this.create('input', {
                    'type' : 'hidden',
                    'name' : 'parent',
                    'id'   : 'comment-parent'
                });

                form.appendChild(input);
            }

            input.setAttribute('value', coid);

            if (null == this.dom('comment-form-place-holder')) {
                var holder = this.create('div', {
                    'id' : 'comment-form-place-holder'
                });

                response.parentNode.insertBefore(holder, response);
            }

            comment.appendChild(response);
            this.dom('cancel-comment-reply-link').style.display = '';

            if (null != textarea && 'text' == textarea.name) {
                textarea.focus();
            }

            return false;
        },

        cancelReply : function () {
            var response = this.dom('{$this->respondId}'),
            holder = this.dom('comment-form-place-holder'), input = this.dom('comment-parent');

            if (null != input) {
                input.parentNode.removeChild(input);
            }

            if (null == holder) {
                return true;
            }

            this.dom('cancel-comment-reply-link').style.display = 'none';
            holder.parentNode.insertBefore(response, holder);
            return false;
        }
    };
})();
</script>
";
                } else {
                    $header .= '<script src="' . $allows['commentReply'] . '" type="text/javascript"></script>';
                }
            }
        }

        /** アンチスパム設定 */
        if ($this->options->commentsAntiSpam && $this->is('single')) {
            if ('' != $allows['antiSpam']) {
                if (1 == $allows['antiSpam']) {
                    $header .= "<script type=\"text/javascript\">
(function () {
    var event = document.addEventListener ? {
        add: 'addEventListener',
        triggers: ['scroll', 'mousemove', 'keyup', 'touchstart'],
        load: 'DOMContentLoaded'
    } : {
        add: 'attachEvent',
        triggers: ['onfocus', 'onmousemove', 'onkeyup', 'ontouchstart'],
        load: 'onload'
    }, added = false;

    document[event.add](event.load, function () {
        var r = document.getElementById('{$this->respondId}'),
            input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_';
        input.value = " . Common::shuffleScriptVar($this->security->getToken($this->request->getRequestUrl())) . "

        if (null != r) {
            var forms = r.getElementsByTagName('form');
            if (forms.length > 0) {
                function append() {
                    if (!added) {
                        forms[0].appendChild(input);
                        added = true;
                    }
                }
            
                for (var i = 0; i < event.triggers.length; i ++) {
                    var trigger = event.triggers[i];
                    document[event.add](trigger, append);
                    window[event.add](trigger, append);
                }
            }
        }
    });
})();
</script>";
                } else {
                    $header .= '<script src="' . $allows['antiSpam'] . '" type="text/javascript"></script>';
                }
            }
        }

        /** 輸出header */
        echo $header;

        /** プラグイン対応 */
        self::pluginHandle()->header($header, $this);
    }

    /**
     * フッターのカスタマイズをサポート
     */
    public function footer()
    {
        self::pluginHandle()->footer($this);
    }

    /**
     * 輸出cookieニーモニックエイリアス
     *
     * @param string $cookieName 減少cookieな
     * @param boolean $return 戻るかどうか
     * @return string|void
     */
    public function remember(string $cookieName, bool $return = false)
    {
        $cookieName = strtolower($cookieName);
        if (!in_array($cookieName, ['author', 'mail', 'url'])) {
            return '';
        }

        $value = Cookie::get('__typecho_remember_' . $cookieName);
        if ($return) {
            return $value;
        } else {
            echo htmlspecialchars($value ?? '');
        }
    }

    /**
     * 輸出ファイルタイトル
     *
     * @param mixed $defines
     * @param string $before
     * @param string $end
     */
    public function archiveTitle($defines = null, string $before = ' &raquo; ', string $end = '')
    {
        if ($this->archiveTitle) {
            $define = '%s';
            if (is_array($defines) && !empty($defines[$this->archiveType])) {
                $define = $defines[$this->archiveType];
            }

            echo $before . sprintf($define, $this->archiveTitle) . $end;
        }
    }

    /**
     * 輸出关键字
     *
     * @param string $split
     * @param string $default
     */
    public function keywords(string $split = ',', string $default = '')
    {
        echo empty($this->keywords) ? $default : str_replace(',', $split, htmlspecialchars($this->keywords ?? ''));
    }

    /**
     * テーマファイルの取得
     *
     * @param string $fileName テーマペーパー
     */
    public function need(string $fileName)
    {
        require $this->themeDir . $fileName;
    }

    /**
     * 輸出视图
     * @throws WidgetException
     */
    public function render()
    {
        /** 扱う静态链接跳转 */
        $this->checkPermalink();

        /** 増加Pingback */
        if (2 == $this->options->allowXmlRpc) {
            $this->response->setHeader('X-Pingback', $this->options->xmlRpcUrl);
        }
        $validated = false;

        //~ カスタマイズされたテンプレート
        if (!empty($this->themeFile)) {
            if (file_exists($this->themeDir . $this->themeFile)) {
                $validated = true;
            }
        }

        if (!$validated && !empty($this->archiveType)) {
            //~ 最初に特定のパスを見つける, 例えば category/default.php
            if (!$validated && !empty($this->archiveSlug)) {
                $themeFile = $this->archiveType . '/' . $this->archiveSlug . '.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $validated = true;
                }
            }

            //~ 然后找归档類似型路径, 例えば category.php
            if (!$validated) {
                $themeFile = $this->archiveType . '.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $validated = true;
                }
            }

            //针右attachmentなhook
            if (!$validated && 'attachment' == $this->archiveType) {
                if (file_exists($this->themeDir . 'page.php')) {
                    $this->themeFile = 'page.php';
                    $validated = true;
                } elseif (file_exists($this->themeDir . 'post.php')) {
                    $this->themeFile = 'post.php';
                    $validated = true;
                }
            }

            //~ 最後にアーカイブパスを見つける, 例えば archive.php または single.php
            if (!$validated && 'index' != $this->archiveType && 'front' != $this->archiveType) {
                $themeFile = $this->archiveSingle ? 'single.php' : 'archive.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $validated = true;
                }
            }

            if (!$validated) {
                $themeFile = 'index.php';
                if (file_exists($this->themeDir . $themeFile)) {
                    $this->themeFile = $themeFile;
                    $validated = true;
                }
            }
        }

        /** ファイルが存在しない */
        if (!$validated) {
            throw new WidgetException(_t('ファイルが存在しない'), 500);
        }

        /** プラグインコネクター */
        self::pluginHandle()->beforeRender($this);

        /** 輸出模板 */
        require_once $this->themeDir . $this->themeFile;

        /** プラグインコネクター */
        self::pluginHandle()->afterRender($this);
    }

    /**
     * 輸出feed
     *
     * @throws WidgetException
     */
    public function feed()
    {
        if ($this->feedType == Feed::RSS1) {
            $feedUrl = $this->feedRssUrl;
        } elseif ($this->feedType == Feed::ATOM1) {
            $feedUrl = $this->feedAtomUrl;
        } else {
            $feedUrl = $this->feedUrl;
        }

        $this->checkPermalink($feedUrl);

        $this->feed->setSubTitle($this->description);
        $this->feed->setFeedUrl($feedUrl);
        $this->feed->setBaseUrl($this->archiveUrl);

        if ($this->is('single') || 'comments' == $this->parameter->type) {
            $this->feed->setTitle(_t(
                '%s な解説',
                $this->options->title . ($this->archiveTitle ? ' - ' . $this->archiveTitle : null)
            ));

            if ('comments' == $this->parameter->type) {
                $comments = Recent::alloc('pageSize=10');
            } else {
                $comments = Recent::alloc('pageSize=10&parentId=' . $this->cid);
            }

            while ($comments->next()) {
                $suffix = self::pluginHandle()->trigger($plugged)->commentFeedItem($this->feedType, $comments);
                if (!$plugged) {
                    $suffix = null;
                }

                $this->feed->addItem([
                    'title'   => $comments->author,
                    'content' => $comments->content,
                    'date'    => $comments->created,
                    'link'    => $comments->permalink,
                    'author'  => (object)[
                        'screenName' => $comments->author,
                        'url'        => $comments->url,
                        'mail'       => $comments->mail
                    ],
                    'excerpt' => strip_tags($comments->content),
                    'suffix'  => $suffix
                ]);
            }
        } else {
            $this->feed->setTitle($this->options->title . ($this->archiveTitle ? ' - ' . $this->archiveTitle : null));

            while ($this->next()) {
                $suffix = self::pluginHandle()->trigger($plugged)->feedItem($this->feedType, $this);
                if (!$plugged) {
                    $suffix = null;
                }

                $feedUrl = '';
                if (Feed::RSS2 == $this->feedType) {
                    $feedUrl = $this->feedUrl;
                } elseif (Feed::RSS1 == $this->feedType) {
                    $feedUrl = $this->feedRssUrl;
                } elseif (Feed::ATOM1 == $this->feedType) {
                    $feedUrl = $this->feedAtomUrl;
                }

                $this->feed->addItem([
                    'title'           => $this->title,
                    'content'         => $this->options->feedFullText ? $this->content
                        : (false !== strpos($this->text, '<!--more-->') ? $this->excerpt .
                            "<p class=\"more\"><a href=\"{$this->permalink}\" title=\"{$this->title}\">[...]</a></p>"
                            : $this->content),
                    'date'            => $this->created,
                    'link'            => $this->permalink,
                    'author'          => $this->author,
                    'excerpt'         => $this->___description(),
                    'comments'        => $this->commentsNum,
                    'commentsFeedUrl' => $feedUrl,
                    'suffix'          => $suffix
                ]);
            }
        }

        $this->response->setContentType($this->feedContentType);
        echo (string) $this->feed;
    }

    /**
     * 判断归档類似型和な
     *
     * @access public
     * @param string $archiveType 归档類似型
     * @param string|null $archiveSlug 归档な
     * @return boolean
     */
    public function is(string $archiveType, ?string $archiveSlug = null)
    {
        return ($archiveType == $this->archiveType ||
                (($this->archiveSingle ? 'single' : 'archive') == $archiveType && 'index' != $this->archiveType) ||
                ('index' == $archiveType && $this->makeSinglePageAsFrontPage))
            && (empty($archiveSlug) || $archiveSlug == $this->archiveSlug);
    }

    /**
     * お問い合わせ
     *
     * @param mixed $select 查询右象
     * @throws Db\Exception
     */
    public function query($select)
    {
        self::pluginHandle()->trigger($queryPlugged)->query($this, $select);
        if (!$queryPlugged) {
            $this->db->fetchAll($select, [$this, 'push']);
        }
    }

    /**
     * 解説アドレス
     *
     * @return string
     */
    protected function ___commentUrl(): string
    {
        /** 生成反馈アドレス */
        /** 解説 */
        $commentUrl = parent::___commentUrl();

        //非依存jsな父级解説
        $reply = $this->request->filter('int')->replyTo;
        if ($reply && $this->is('single')) {
            $commentUrl .= '?parent=' . $reply;
        }

        return $commentUrl;
    }

    /**
     * 导入右象
     *
     * @param Archive $widget 需要导入な右象
     */
    private function import(Archive $widget)
    {
        $currentProperties = get_object_vars($this);

        foreach ($currentProperties as $name => $value) {
            if (false !== strpos('|request|response|parameter|feed|feedType|currentFeedUrl|', '|' . $name . '|')) {
                continue;
            }

            if (isset($widget->{$name})) {
                $this->{$name} = $widget->{$name};
            } else {
                $method = ucfirst($name);
                $setMethod = 'set' . $method;
                $getMethod = 'get' . $method;

                if (
                    method_exists($this, $setMethod)
                    && method_exists($widget, $getMethod)
                ) {
                    $value = $widget->{$getMethod}();

                    if ($value !== null) {
                        $this->{$setMethod}($widget->{$getMethod}());
                    }
                }
            }
        }
    }

    /**
     * リンクが正しいことを確認する
     *
     * @param string|null $permalink
     */
    private function checkPermalink(?string $permalink = null)
    {
        if (!isset($permalink)) {
            $type = $this->parameter->type;

            if (
                in_array($type, ['index', 'comment_page', 404])
                || $this->makeSinglePageAsFrontPage    // 自定义首页不扱う
                || !$this->parameter->checkPermalink
            ) { // 強制閉鎖
                return;
            }

            if ($this->archiveSingle) {
                $permalink = $this->permalink;
            } else {
                $value = array_merge($this->pageRow, [
                    'page' => $this->currentPage
                ]);

                $path = Router::url($type, $value);
                $permalink = Common::url($path, $this->options->index);
            }
        }

        $requestUrl = $this->request->getRequestUrl();

        $src = parse_url($permalink);
        $target = parse_url($requestUrl);

        if ($src['host'] != $target['host'] || urldecode($src['path']) != urldecode($target['path'])) {
            $this->response->redirect($permalink, true);
        }
    }

    /**
     * 扱うindex
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     */
    private function indexHandle(Query $select, bool &$hasPushed)
    {
        $select->where('table.contents.type = ?', 'post');

        /** プラグインインターフェース */
        self::pluginHandle()->indexHandle($this, $select);
    }

    /**
     * 默认な非首页归档扱う
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @throws WidgetException
     */
    private function archiveEmptyHandle(Query $select, bool &$hasPushed)
    {
        throw new WidgetException(_t('请求なアドレス不存在'), 404);
    }

    /**
     * 404页面扱う
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     */
    private function error404Handle(Query $select, bool &$hasPushed)
    {
        /** セットアップheader */
        $this->response->setStatus(404);

        /** セットアップキャプション */
        $this->archiveTitle = _t('ページが見つかりません');

        /** セットアップ归档類似型 */
        $this->archiveType = 'archive';

        /** セットアップファイル略称 */
        $this->archiveSlug = 404;

        /** セットアップ归档模板 */
        $this->themeFile = '404.php';

        /** セットアップ单一归档類似型 */
        $this->archiveSingle = false;

        $hasPushed = true;

        /** プラグインインターフェース */
        self::pluginHandle()->error404Handle($this, $select);
    }

    /**
     * 独立页扱う
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @throws WidgetException|Db\Exception
     */
    private function singleHandle(Query $select, bool &$hasPushed)
    {
        if ('comment_page' == $this->parameter->type) {
            $params = [];
            $matched = Router::match($this->request->permalink);

            if ($matched && $matched instanceof Archive && $matched->is('single')) {
                $this->import($matched);
                $hasPushed = true;
                return;
            }
        }

        /** 将这两个セットアップ提前是为了保证在各論queryなpluginを使用することができます。is判断初步归档類似型 */
        /** より細かい判断が必要な場合，原則可以使用singleHandle実現するために */
        $this->archiveSingle = true;

        /** 默认归档類似型 */
        $this->archiveType = 'single';

        /** 匹配類似型 */

        if ('single' != $this->parameter->type) {
            $select->where('table.contents.type = ?', $this->parameter->type);
        }

        /** もしそうなら单篇文章或独立页面 */
        if (isset($this->request->cid)) {
            $select->where('table.contents.cid = ?', $this->request->filter('int')->cid);
        }

        /** 略称の一致 */
        if (isset($this->request->slug) && !$this->parameter->preview) {
            $select->where('table.contents.slug = ?', $this->request->slug);
        }

        /** マッチング時間 */
        if (isset($this->request->year) && !$this->parameter->preview) {
            $year = $this->request->filter('int')->year;

            $fromMonth = 1;
            $toMonth = 12;

            $fromDay = 1;
            $toDay = 31;

            if (isset($this->request->month)) {
                $fromMonth = $this->request->filter('int')->month;
                $toMonth = $fromMonth;

                $fromDay = 1;
                $toDay = date('t', mktime(0, 0, 0, $toMonth, 1, $year));

                if (isset($this->request->day)) {
                    $fromDay = $this->request->filter('int')->day;
                    $toDay = $fromDay;
                }
            }

            /** はじめにGMT时间なunixタイムスタンプ */
            $from = mktime(0, 0, 0, $fromMonth, $fromDay, $year)
                - $this->options->timezone + $this->options->serverTimezone;
            $to = mktime(23, 59, 59, $toMonth, $toDay, $year)
                - $this->options->timezone + $this->options->serverTimezone;
            $select->where('table.contents.created >= ? AND table.contents.created < ?', $from, $to);
        }

        /** パスワードの保存先cookie */
        $isPasswordPosted = false;

        if (
            $this->request->isPost()
            && isset($this->request->protectPassword)
            && !$this->parameter->preview
        ) {
            $this->security->protect();
            Cookie::set(
                'protectPassword_' . $this->request->filter('int')->protectCID,
                $this->request->protectPassword
            );

            $isPasswordPosted = true;
        }

        /** 匹配類似型 */
        $select->limit(1);
        $this->query($select);

        if (
            !$this->have()
            || (isset($this->request->category)
                && $this->category != $this->request->category && !$this->parameter->preview)
            || (isset($this->request->directory)
                && $this->request->directory != implode('/', $this->directory) && !$this->parameter->preview)
        ) {
            if (!$this->invokeFromOutside) {
                /** 右没有索引情况下な判断 */
                throw new WidgetException(_t('请求なアドレス不存在'), 404);
            } else {
                $hasPushed = true;
                return;
            }
        }

        /** パスワードフォーム判定ロジック */
        if ($isPasswordPosted && $this->hidden) {
            throw new WidgetException(_t('右不起,您输入な密码错误'), 403);
        }

        /** セットアップ模板 */
        if ($this->template) {
            /** 应用カスタマイズされたテンプレート */
            $this->themeFile = $this->template;
        }

        /** セットアップ始終部feed */
        /** RSS 2.0 */

        //右自定义首页使用全局变量
        if (!$this->makeSinglePageAsFrontPage) {
            $this->feedUrl = $this->row['feedUrl'];

            /** RSS 1.0 */
            $this->feedRssUrl = $this->row['feedRssUrl'];

            /** ATOM 1.0 */
            $this->feedAtomUrl = $this->row['feedAtomUrl'];

            /** セットアップキャプション */
            $this->archiveTitle = $this->title;

            /** セットアップ关键词 */
            $this->keywords = implode(',', array_column($this->tags, 'name'));

            /** セットアップ描述 */
            $this->description = $this->___description();
        }

        /** セットアップ归档類似型 */
        [$this->archiveType] = explode('_', $this->type);

        /** セットアップファイル略称 */
        $this->archiveSlug = ('post' == $this->type || 'attachment' == $this->type) ? $this->cid : $this->slug;

        /** セットアップ归档アドレス */
        $this->archiveUrl = $this->permalink;

        /** セットアップ403始終 */
        if ($this->hidden) {
            $this->response->setStatus(403);
        }

        $hasPushed = true;

        /** プラグインインターフェース */
        self::pluginHandle()->singleHandle($this, $select);
    }

    /**
     * 扱う分類似
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @throws WidgetException|Db\Exception
     */
    private function categoryHandle(Query $select, bool &$hasPushed)
    {
        /** もしそうなら分類似 */
        $categorySelect = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->limit(1);

        if (isset($this->request->mid)) {
            $categorySelect->where('mid = ?', $this->request->filter('int')->mid);
        }

        if (isset($this->request->slug)) {
            $categorySelect->where('slug = ?', $this->request->slug);
        }

        if (isset($this->request->directory)) {
            $directory = explode('/', $this->request->directory);
            $categorySelect->where('slug = ?', $directory[count($directory) - 1]);
        }

        $category = $this->db->fetchRow($categorySelect);
        if (empty($category)) {
            throw new WidgetException(_t('分類似不存在'), 404);
        }

        $categoryListWidget = Rows::alloc('current=' . $category['mid']);
        $category = $categoryListWidget->filter($category);

        if (isset($directory) && ($this->request->directory != implode('/', $category['directory']))) {
            throw new WidgetException(_t('父级分類似不存在'), 404);
        }

        $children = $categoryListWidget->getAllChildren($category['mid']);
        $children[] = $category['mid'];

        /** fix sql92 by 70 */
        $select->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid IN ?', $children)
            ->where('table.contents.type = ?', 'post')
            ->group('table.contents.cid');

        /** セットアップ分页 */
        $this->pageRow = array_merge($category, [
            'slug'      => urlencode($category['slug']),
            'directory' => implode('/', array_map('urlencode', $category['directory']))
        ]);

        /** セットアップ关键词 */
        $this->keywords = $category['name'];

        /** セットアップ描述 */
        $this->description = $category['description'];

        /** セットアップ始終部feed */
        /** RSS 2.0 */
        $this->feedUrl = $category['feedUrl'];

        /** RSS 1.0 */
        $this->feedRssUrl = $category['feedRssUrl'];

        /** ATOM 1.0 */
        $this->feedAtomUrl = $category['feedAtomUrl'];

        /** セットアップキャプション */
        $this->archiveTitle = $category['name'];

        /** セットアップ归档類似型 */
        $this->archiveType = 'category';

        /** セットアップファイル略称 */
        $this->archiveSlug = $category['slug'];

        /** セットアップ归档アドレス */
        $this->archiveUrl = $category['permalink'];

        /** プラグインインターフェース */
        self::pluginHandle()->categoryHandle($this, $select);
    }

    /**
     * 扱う标签
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @throws WidgetException|Db\Exception
     */
    private function tagHandle(Query $select, bool &$hasPushed)
    {
        $tagSelect = $this->db->select()->from('table.metas')
            ->where('type = ?', 'tag')->limit(1);

        if (isset($this->request->mid)) {
            $tagSelect->where('mid = ?', $this->request->filter('int')->mid);
        }

        if (isset($this->request->slug)) {
            $tagSelect->where('slug = ?', $this->request->slug);
        }

        /** もしそうなら标签 */
        $tag = $this->db->fetchRow(
            $tagSelect,
            [Metas::alloc(), 'filter']
        );

        if (!$tag) {
            throw new WidgetException(_t('ラベルが存在しない'), 404);
        }

        /** fix sql92 by 70 */
        $select->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $tag['mid'])
            ->where('table.contents.type = ?', 'post');

        /** セットアップ分页 */
        $this->pageRow = array_merge($tag, [
            'slug' => urlencode($tag['slug'])
        ]);

        /** セットアップ关键词 */
        $this->keywords = $tag['name'];

        /** セットアップ描述 */
        $this->description = $tag['description'];

        /** セットアップ始終部feed */
        /** RSS 2.0 */
        $this->feedUrl = $tag['feedUrl'];

        /** RSS 1.0 */
        $this->feedRssUrl = $tag['feedRssUrl'];

        /** ATOM 1.0 */
        $this->feedAtomUrl = $tag['feedAtomUrl'];

        /** セットアップキャプション */
        $this->archiveTitle = $tag['name'];

        /** セットアップ归档類似型 */
        $this->archiveType = 'tag';

        /** セットアップファイル略称 */
        $this->archiveSlug = $tag['slug'];

        /** セットアップ归档アドレス */
        $this->archiveUrl = $tag['permalink'];

        /** プラグインインターフェース */
        self::pluginHandle()->tagHandle($this, $select);
    }

    /**
     * 扱う作者
     *
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @throws WidgetException|Db\Exception
     */
    private function authorHandle(Query $select, bool &$hasPushed)
    {
        $uid = $this->request->filter('int')->uid;

        $author = $this->db->fetchRow(
            $this->db->select()->from('table.users')
            ->where('uid = ?', $uid),
            [User::alloc(), 'filter']
        );

        if (!$author) {
            throw new WidgetException(_t('作者は存在しない。'), 404);
        }

        $select->where('table.contents.authorId = ?', $uid)
            ->where('table.contents.type = ?', 'post');

        /** セットアップ分页 */
        $this->pageRow = $author;

        /** セットアップ关键词 */
        $this->keywords = $author['screenName'];

        /** セットアップ描述 */
        $this->description = $author['screenName'];

        /** セットアップ始終部feed */
        /** RSS 2.0 */
        $this->feedUrl = $author['feedUrl'];

        /** RSS 1.0 */
        $this->feedRssUrl = $author['feedRssUrl'];

        /** ATOM 1.0 */
        $this->feedAtomUrl = $author['feedAtomUrl'];

        /** セットアップキャプション */
        $this->archiveTitle = $author['screenName'];

        /** セットアップ归档類似型 */
        $this->archiveType = 'author';

        /** セットアップファイル略称 */
        $this->archiveSlug = $author['uid'];

        /** セットアップ归档アドレス */
        $this->archiveUrl = $author['permalink'];

        /** プラグインインターフェース */
        self::pluginHandle()->authorHandle($this, $select);
    }

    /**
     * 扱う日付期
     *
     * @access private
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @return void
     */
    private function dateHandle(Query $select, &$hasPushed)
    {
        /** もしそうなら按日付期归档 */
        $year = $this->request->filter('int')->year;
        $month = $this->request->filter('int')->month;
        $day = $this->request->filter('int')->day;

        if (!empty($year) && !empty($month) && !empty($day)) {

            /** 毎日付提出する場合 */
            $from = mktime(0, 0, 0, $month, $day, $year);
            $to = mktime(23, 59, 59, $month, $day, $year);

            /** ファイル略称 */
            $this->archiveSlug = 'day';

            /** セットアップキャプション */
            $this->archiveTitle = _t('%dニャン姓%d月%d日付', $year, $month, $day);
        } elseif (!empty($year) && !empty($month)) {

            /** 月単位で提出する場合 */
            $from = mktime(0, 0, 0, $month, 1, $year);
            $to = mktime(23, 59, 59, $month, date('t', $from), $year);

            /** ファイル略称 */
            $this->archiveSlug = 'month';

            /** セットアップキャプション */
            $this->archiveTitle = _t('%dニャン姓%d月', $year, $month);
        } elseif (!empty($year)) {

            /** 如果按ニャン姓归档 */
            $from = mktime(0, 0, 0, 1, 1, $year);
            $to = mktime(23, 59, 59, 12, 31, $year);

            /** ファイル略称 */
            $this->archiveSlug = 'year';

            /** セットアップキャプション */
            $this->archiveTitle = _t('%dニャン姓', $year);
        }

        $select->where('table.contents.created >= ?', $from - $this->options->timezone + $this->options->serverTimezone)
            ->where('table.contents.created <= ?', $to - $this->options->timezone + $this->options->serverTimezone)
            ->where('table.contents.type = ?', 'post');

        /** セットアップ归档類似型 */
        $this->archiveType = 'date';

        /** セットアップ始終部feed */
        $value = [
            'year' => $year,
            'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
            'day' => str_pad($day, 2, '0', STR_PAD_LEFT)
        ];

        /** セットアップ分页 */
        $this->pageRow = $value;

        /** 获取対す路由,ページめくりのフィルタリング */
        $currentRoute = str_replace('_page', '', $this->parameter->type);

        /** RSS 2.0 */
        $this->feedUrl = Router::url($currentRoute, $value, $this->options->feedUrl);

        /** RSS 1.0 */
        $this->feedRssUrl = Router::url($currentRoute, $value, $this->options->feedRssUrl);

        /** ATOM 1.0 */
        $this->feedAtomUrl = Router::url($currentRoute, $value, $this->options->feedAtomUrl);

        /** セットアップ归档アドレス */
        $this->archiveUrl = Router::url($currentRoute, $value, $this->options->index);

        /** プラグインインターフェース */
        self::pluginHandle()->dateHandle($this, $select);
    }

    /**
     * 扱う搜索
     *
     * @access private
     * @param Query $select 查询右象
     * @param boolean $hasPushed 列に押し込まれたか
     * @return void
     */
    private function searchHandle(Query $select, &$hasPushed)
    {
        /** カスタム検索エンジンのインターフェイスを追加 */
        //~ fix issue 40
        $keywords = $this->request->filter('url', 'search')->keywords;
        self::pluginHandle()->trigger($hasPushed)->search($keywords, $this);

        if (!$hasPushed) {
            $searchQuery = '%' . str_replace(' ', '%', $keywords) . '%';

            /** 検索しても、プライバシー項目保護アーカイブに行き着かない */
            if ($this->user->hasLogin()) {
                //~ fix issue 941
                $select->where("table.contents.password IS NULL
                 OR table.contents.password = '' OR table.contents.authorId = ?", $this->user->uid);
            } else {
                $select->where("table.contents.password IS NULL OR table.contents.password = ''");
            }

            $op = $this->db->getAdapter()->getDriver() == 'pgsql' ? 'ILIKE' : 'LIKE';

            $select->where("table.contents.title {$op} ? OR table.contents.text {$op} ?", $searchQuery, $searchQuery)
                ->where('table.contents.type = ?', 'post');
        }

        /** セットアップ关键词 */
        $this->keywords = $keywords;

        /** セットアップ分页 */
        $this->pageRow = ['keywords' => urlencode($keywords)];

        /** セットアップ始終部feed */
        /** RSS 2.0 */
        $this->feedUrl = Router::url('search', ['keywords' => $keywords], $this->options->feedUrl);

        /** RSS 1.0 */
        $this->feedRssUrl = Router::url('search', ['keywords' => $keywords], $this->options->feedAtomUrl);

        /** ATOM 1.0 */
        $this->feedAtomUrl = Router::url('search', ['keywords' => $keywords], $this->options->feedAtomUrl);

        /** セットアップキャプション */
        $this->archiveTitle = $keywords;

        /** セットアップ归档類似型 */
        $this->archiveType = 'search';

        /** セットアップファイル略称 */
        $this->archiveSlug = $keywords;

        /** セットアップ归档アドレス */
        $this->archiveUrl = Router::url('search', ['keywords' => $keywords], $this->options->index);

        /** プラグインインターフェース */
        self::pluginHandle()->searchHandle($this, $select);
    }
}
