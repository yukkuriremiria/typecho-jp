<?php

namespace Widget;

use Typecho\Common;
use Widget\Plugins\Config;
use Widget\Themes\Files;
use Widget\Users\Edit as UsersEdit;
use Widget\Contents\Attachment\Edit as AttachmentEdit;
use Widget\Contents\Post\Edit as PostEdit;
use Widget\Contents\Page\Edit as PageEdit;
use Widget\Contents\Post\Admin as PostAdmin;
use Widget\Comments\Admin as CommentsAdmin;
use Widget\Metas\Category\Admin as CategoryAdmin;
use Widget\Metas\Category\Edit as CategoryEdit;
use Widget\Metas\Tag\Admin as TagAdmin;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * バックエンドメニューの表示
 *
 * @package Widget
 */
class Menu extends Base
{
    /**
     * 現在のメニュータイトル
     * @var string
     */
    public $title;

    /**
     * 現在プロジェクトリンクを追加中
     * @var string
     */
    public $addLink;

    /**
     * 親メニューリスト
     *
     * @var array
     */
    private $menu = [];

    /**
     * 現在の親メニュー
     *
     * @var integer
     */
    private $currentParent = 1;

    /**
     * 現在のサブメニュー
     *
     * @var integer
     */
    private $currentChild = 0;

    /**
     * 現在のページ
     *
     * @var string
     */
    private $currentUrl;

    /**
     * 実行可能関数,初期設定メニュー
     */
    public function execute()
    {
        $parentNodes = [null, _t('コンソール'), _t('書く'), _t('経営上'), _t('セットアップ')];

        $childNodes = [
            [
                [_t('サインイン'), _t('サインイン到%s', $this->options->title), 'login.php', 'visitor'],
                [_t('在籍'), _t('在籍到%s', $this->options->title), 'register.php', 'visitor']
            ],
            [
                [_t('概要'), _t('サイト概要'), 'index.php', 'subscriber'],
                [_t('个人セットアップ'), _t('个人セットアップ'), 'profile.php', 'subscriber'],
                [_t('プラグイン'), _t('プラグイン経営上'), 'plugins.php', 'administrator'],
                [[Config::class, 'getMenuTitle'], [Config::class, 'getMenuTitle'], 'options-plugin.php?config=', 'administrator', true],
                [_t('外装状態'), _t('网站外装状態'), 'themes.php', 'administrator'],
                [[Files::class, 'getMenuTitle'], [Files::class, 'getMenuTitle'], 'theme-editor.php', 'administrator', true],
                [_t('セットアップ外装状態'), _t('セットアップ外装状態'), 'options-theme.php', 'administrator', true],
                [_t('バックアップ'), _t('バックアップ'), 'backup.php', 'administrator'],
                [_t('昂ずる'), _t('昂ずる程序'), 'upgrade.php', 'administrator', true],
                [_t('ウェルカム'), _t('ウェルカム使用'), 'welcome.php', 'subscriber', true]
            ],
            [
                [_t('書く文'), _t('書く新文'), 'write-post.php', 'contributor'],
                [[PostEdit::class, 'getMenuTitle'], [PostEdit::class, 'getMenuTitle'], 'write-post.php?cid=', 'contributor', true],
                [_t('ページ作成'), _t('新しいページを作成する'), 'write-page.php', 'editor'],
                [[PageEdit::class, 'getMenuTitle'], [PageEdit::class, 'getMenuTitle'], 'write-page.php?cid=', 'editor', true],
            ],
            [
                [_t('文'), _t('経営上文'), 'manage-posts.php', 'contributor', false, 'write-post.php'],
                [[PostAdmin::class, 'getMenuTitle'], [PostAdmin::class, 'getMenuTitle'], 'manage-posts.php?uid=', 'contributor', true],
                [_t('別ページ'), _t('経営上別ページ'), 'manage-pages.php', 'editor', false, 'write-page.php'],
                [_t('解説'), _t('経営上解説'), 'manage-comments.php', 'contributor'],
                [[CommentsAdmin::class, 'getMenuTitle'], [CommentsAdmin::class, 'getMenuTitle'], 'manage-comments.php?cid=', 'contributor', true],
                [_t('分類'), _t('経営上分類'), 'manage-categories.php', 'editor', false, 'category.php'],
                [_t('新增分類'), _t('新增分類'), 'category.php', 'editor', true],
                [[CategoryAdmin::class, 'getMenuTitle'], [CategoryAdmin::class, 'getMenuTitle'], 'manage-categories.php?parent=', 'editor', true, [CategoryAdmin::class, 'getAddLink']],
                [[CategoryEdit::class, 'getMenuTitle'], [CategoryEdit::class, 'getMenuTitle'], 'category.php?mid=', 'editor', true],
                [[CategoryEdit::class, 'getMenuTitle'], [CategoryEdit::class, 'getMenuTitle'], 'category.php?parent=', 'editor', true],
                [_t('タブ'), _t('経営上タブ'), 'manage-tags.php', 'editor'],
                [[TagAdmin::class, 'getMenuTitle'], [TagAdmin::class, 'getMenuTitle'], 'manage-tags.php?mid=', 'editor', true],
                [_t('書類'), _t('経営上書類'), 'manage-medias.php', 'editor'],
                [[AttachmentEdit::class, 'getMenuTitle'], [AttachmentEdit::class, 'getMenuTitle'], 'media.php?cid=', 'contributor', true],
                [_t('ユーザー'), _t('経営上ユーザー'), 'manage-users.php', 'administrator', false, 'user.php'],
                [_t('新增ユーザー'), _t('新增ユーザー'), 'user.php', 'administrator', true],
                [[UsersEdit::class, 'getMenuTitle'], [UsersEdit::class, 'getMenuTitle'], 'user.php?uid=', 'administrator', true],
            ],
            [
                [_t('基本的'), _t('基本的セットアップ'), 'options-general.php', 'administrator'],
                [_t('解説'), _t('解説セットアップ'), 'options-discussion.php', 'administrator'],
                [_t('読む'), _t('読むセットアップ'), 'options-reading.php', 'administrator'],
                [_t('パーマリンク'), _t('パーマリンクセットアップ'), 'options-permalink.php', 'administrator'],
            ]
        ];

        /** 拡張メニュー */
        $panelTable = unserialize($this->options->panelTable);
        $extendingParentMenu = empty($panelTable['parent']) ? [] : $panelTable['parent'];
        $extendingChildMenu = empty($panelTable['child']) ? [] : $panelTable['child'];
        $currentUrl = $this->request->getRequestUrl();
        $adminUrl = $this->options->adminUrl;
        $menu = [];
        $defaultChildNode = [null, null, null, 'administrator', false, null];

        $currentUrlParts = parse_url($currentUrl);
        $currentUrlParams = [];
        if (!empty($currentUrlParts['query'])) {
            parse_str($currentUrlParts['query'], $currentUrlParams);
        }

        if ('/' == $currentUrlParts['path'][strlen($currentUrlParts['path']) - 1]) {
            $currentUrlParts['path'] .= 'index.php';
        }

        foreach ($extendingParentMenu as $key => $val) {
            $parentNodes[10 + $key] = $val;
        }

        foreach ($extendingChildMenu as $key => $val) {
            $childNodes[$key] = array_merge($childNodes[$key] ?? [], $val);
        }

        foreach ($parentNodes as $key => $parentNode) {
            // this is a simple struct than before
            $children = [];
            $showedChildrenCount = 0;
            $firstUrl = null;

            foreach ($childNodes[$key] as $inKey => $childNode) {
                // magic merge
                $childNode += $defaultChildNode;
                [$name, $title, $url, $access] = $childNode;

                $hidden = $childNode[4] ?? false;
                $addLink = $childNode[5] ?? null;

                // 最もオリジナルなものの保存hiddenインフォメーション
                $orgHidden = $hidden;

                // parse url
                $url = Common::url($url, $adminUrl);

                // compare url
                $urlParts = parse_url($url);
                $urlParams = [];
                if (!empty($urlParts['query'])) {
                    parse_str($urlParts['query'], $urlParams);
                }

                $validate = true;
                if ($urlParts['path'] != $currentUrlParts['path']) {
                    $validate = false;
                } else {
                    foreach ($urlParams as $paramName => $paramValue) {
                        if (!isset($currentUrlParams[$paramName])) {
                            $validate = false;
                            break;
                        }
                    }
                }

                if (
                    $validate
                    && basename($urlParts['path']) == 'extending.php'
                    && !empty($currentUrlParams['panel']) && !empty($urlParams['panel'])
                    && $urlParams['panel'] != $currentUrlParams['panel']
                ) {
                    $validate = false;
                }

                if ($hidden && $validate) {
                    $hidden = false;
                }

                if (!$hidden && !$this->user->pass($access, true)) {
                    $hidden = true;
                }

                if (!$hidden) {
                    $showedChildrenCount++;

                    if (empty($firstUrl)) {
                        $firstUrl = $url;
                    }

                    if (is_array($name)) {
                        [$widget, $method] = $name;
                        $name = self::widget($widget)->$method();
                    }

                    if (is_array($title)) {
                        [$widget, $method] = $title;
                        $title = self::widget($widget)->$method();
                    }

                    if (is_array($addLink)) {
                        [$widget, $method] = $addLink;
                        $addLink = self::widget($widget)->$method();
                    }
                }

                if ($validate) {
                    if ('visitor' != $access) {
                        $this->user->pass($access);
                    }

                    $this->currentParent = $key;
                    $this->currentChild = $inKey;
                    $this->title = $title;
                    $this->addLink = $addLink ? Common::url($addLink, $adminUrl) : null;
                }

                $children[$inKey] = [
                    $name,
                    $title,
                    $url,
                    $access,
                    $hidden,
                    $addLink,
                    $orgHidden
                ];
            }

            $menu[$key] = [$parentNode, $showedChildrenCount > 0, $firstUrl, $children];
        }

        $this->menu = $menu;
        $this->currentUrl = Common::safeUrl($currentUrl);
    }

    /**
     * 現在のメニューを取得する
     *
     * @return array
     */
    public function getCurrentMenu(): ?array
    {
        return $this->currentParent > 0 ? $this->menu[$this->currentParent][3][$this->currentChild] : null;
    }

    /**
     * 親メニューをエクスポート
     */
    public function output($class = 'focus', $childClass = 'focus')
    {
        foreach ($this->menu as $key => $node) {
            if (!$node[1] || !$key) {
                continue;
            }

            echo "<ul class=\"root" . ($key == $this->currentParent ? ' ' . $class : null)
                . "\"><li class=\"parent\"><a href=\"{$node[2]}\">{$node[0]}</a>"
                . "</li><ul class=\"child\">";

            $last = 0;
            foreach ($node[3] as $inKey => $inNode) {
                if (!$inNode[4]) {
                    $last = $inKey;
                }
            }

            foreach ($node[3] as $inKey => $inNode) {
                if ($inNode[4]) {
                    continue;
                }

                $classes = [];
                if ($key == $this->currentParent && $inKey == $this->currentChild) {
                    $classes[] = $childClass;
                } elseif ($inNode[6]) {
                    continue;
                }

                if ($inKey == $last) {
                    $classes[] = 'last';
                }

                echo "<li" . (!empty($classes) ? ' class="' . implode(' ', $classes) . '"' : null) . "><a href=\""
                    . ($key == $this->currentParent && $inKey == $this->currentChild ? $this->currentUrl : $inNode[2])
                    . "\">{$inNode[0]}</a></li>";
            }

            echo "</ul></ul>";
        }
    }
}