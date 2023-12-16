<?php

namespace Utils;

use Typecho\Common;
use Typecho\Db;
use Typecho\I18n;
use Typecho\Plugin;
use Typecho\Widget;
use Widget\Base\Options as BaseOptions;
use Widget\Options;
use Widget\Plugins\Edit;
use Widget\Security;
use Widget\Service;

/**
 * プラグインヘルパーはデフォルトですべてのtypechoリリース中.
 * だから、安心してその機能を使うことができる, ユーザーのシステムへのプラグインのインストールを容易にするため.
 *
 * @package Helper
 * @author qining
 * @version 1.0.0
 * @link http://typecho.org
 */
class Helper
{
    /**
     * ゲインSecurityボーイフレンド
     *
     * @return Security
     */
    public static function security(): Security
    {
        return Security::alloc();
    }

    /**
     * 基礎IDゲイン单个Widgetボーイフレンド
     *
     * @param string $table テーブル名, アジュバント contents, comments, metas, users
     * @param int $pkId
     * @return Widget|null
     */
    public static function widgetById(string $table, int $pkId): ?Widget
    {
        $table = ucfirst($table);
        if (!in_array($table, ['Contents', 'Comments', 'Metas', 'Users'])) {
            return null;
        }

        $keys = [
            'Contents' => 'cid',
            'Comments' => 'coid',
            'Metas'    => 'mid',
            'Users'    => 'uid'
        ];

        $className = '\Widget\Base\\' . $table;

        $key = $keys[$table];
        $db = Db::get();
        $widget = Widget::widget($className . '@' . $pkId);

        $db->fetchRow(
            $widget->select()->where("{$key} = ?", $pkId)->limit(1),
            [$widget, 'push']
        );

        return $widget;
    }

    /**
     * 非同期サービスをリクエストする
     *
     * @param $method
     * @param $params
     */
    public static function requestService($method, $params)
    {
        Service::alloc()->requestService($method, $params);
    }

    /**
     * プラグインの強制削除
     *
     * @param string $pluginName プラグイン名
     */
    public static function removePlugin(string $pluginName)
    {
        try {
            /** ゲイン插件入口 */
            [$pluginFileName, $className] = Plugin::portal(
                $pluginName,
                __TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__
            );

            /** ゲイン已启用插件 */
            $plugins = Plugin::export();
            $activatedPlugins = $plugins['activated'];

            /** プラグインをロードする */
            require_once $pluginFileName;

            /** インスタンス化が成功したかどうかの判定 */
            if (
                !isset($activatedPlugins[$pluginName]) || !class_exists($className)
                || !method_exists($className, 'deactivate')
            ) {
                throw new Widget\Exception(_t('プラグインを無効にできない'), 500);
            }

            call_user_func([$className, 'deactivate']);
        } catch (\Exception $e) {
            //nothing to do
        }

        $db = Db::get();

        try {
            Plugin::deactivate($pluginName);
            $db->query($db->update('table.options')
                ->rows(['value' => serialize(Plugin::export())])
                ->where('name = ?', 'plugins'));
        } catch (Plugin\Exception $e) {
            //nothing to do
        }

        $db->query($db->delete('table.options')->where('name = ?', 'plugin:' . $pluginName));
    }

    /**
     * 言語項目のインポート
     *
     * @param string $domain
     */
    public static function lang(string $domain)
    {
        $currentLang = I18n::getLang();
        if ($currentLang) {
            $currentLang = basename($currentLang);
            $fileName = dirname(__FILE__) . '/' . $domain . '/lang/' . $currentLang;
            if (file_exists($fileName)) {
                I18n::addLang($fileName);
            }
        }
    }

    /**
     * ルートの追加
     *
     * @param string $name ルート名
     * @param string $url ルーティングパス
     * @param string $widget コンポーネント名
     * @param string|null $action コンポーネント・アクション
     * @param string|null $after ルート裏
     * @return integer
     */
    public static function addRoute(
        string $name,
        string $url,
        string $widget,
        ?string $action = null,
        ?string $after = null
    ): int {
        $routingTable = self::options()->routingTable;
        if (isset($routingTable[0])) {
            unset($routingTable[0]);
        }

        $pos = 0;
        foreach ($routingTable as $key => $val) {
            $pos++;

            if ($key == $after) {
                break;
            }
        }

        $pre = array_slice($routingTable, 0, $pos);
        $next = array_slice($routingTable, $pos);

        $routingTable = array_merge($pre, [
            $name => [
                'url'    => $url,
                'widget' => $widget,
                'action' => $action
            ]
        ], $next);
        self::options()->routingTable = $routingTable;

        return BaseOptions::alloc()->update(
            ['value' => serialize($routingTable)],
            Db::get()->sql()->where('name = ?', 'routingTable')
        );
    }

    /**
     * ゲインOptionsボーイフレンド
     *
     * @return Options
     */
    public static function options(): Options
    {
        return Options::alloc();
    }

    /**
     * ルート削除
     *
     * @param string $name ルート名
     * @return integer
     */
    public static function removeRoute(string $name): int
    {
        $routingTable = self::options()->routingTable;
        if (isset($routingTable[0])) {
            unset($routingTable[0]);
        }

        unset($routingTable[$name]);
        self::options()->routingTable = $routingTable;

        $db = Db::get();
        return BaseOptions::alloc()->update(
            ['value' => serialize($routingTable)],
            $db->sql()->where('name = ?', 'routingTable')
        );
    }

    /**
     * 増加actionエクステンション
     *
     * @param string $actionName 需要エクステンション的actionな
     * @param string $widgetName 需要エクステンション的widgetな
     * @return integer
     */
    public static function addAction(string $actionName, string $widgetName): int
    {
        $actionTable = unserialize(self::options()->actionTable);
        $actionTable = empty($actionTable) ? [] : $actionTable;
        $actionTable[$actionName] = $widgetName;

        return BaseOptions::alloc()->update(
            ['value' => (self::options()->actionTable = serialize($actionTable))],
            Db::get()->sql()->where('name = ?', 'actionTable')
        );
    }

    /**
     * 除去actionエクステンション
     *
     * @param string $actionName
     * @return int
     */
    public static function removeAction(string $actionName): int
    {
        $actionTable = unserialize(self::options()->actionTable);
        $actionTable = empty($actionTable) ? [] : $actionTable;

        if (isset($actionTable[$actionName])) {
            unset($actionTable[$actionName]);
            reset($actionTable);
        }

        return BaseOptions::alloc()->update(
            ['value' => (self::options()->actionTable = serialize($actionTable))],
            Db::get()->sql()->where('name = ?', 'actionTable')
        );
    }

    /**
     * 増加一个菜单
     *
     * @param string $menuName メニュー名
     * @return integer
     */
    public static function addMenu(string $menuName): int
    {
        $panelTable = unserialize(self::options()->panelTable);
        $panelTable['parent'] = empty($panelTable['parent']) ? [] : $panelTable['parent'];
        $panelTable['parent'][] = $menuName;

        BaseOptions::alloc()->update(
            ['value' => (self::options()->panelTable = serialize($panelTable))],
            Db::get()->sql()->where('name = ?', 'panelTable')
        );

        end($panelTable['parent']);
        return key($panelTable['parent']) + 10;
    }

    /**
     * メニューの削除
     *
     * @param string $menuName メニュー名
     * @return integer
     */
    public static function removeMenu(string $menuName): int
    {
        $panelTable = unserialize(self::options()->panelTable);
        $panelTable['parent'] = empty($panelTable['parent']) ? [] : $panelTable['parent'];

        if (false !== ($index = array_search($menuName, $panelTable['parent']))) {
            unset($panelTable['parent'][$index]);
        }

        BaseOptions::alloc()->update(
            ['value' => (self::options()->panelTable = serialize($panelTable))],
            Db::get()->sql()->where('name = ?', 'panelTable')
        );

        return $index + 10;
    }

    /**
     * 増加一个面板
     *
     * @param integer $index メニューインデックス
     * @param string $fileName 文件な
     * @param string $title パネルタイトル
     * @param string $subTitle パネルサブタイトル
     * @param string $level アクセス権
     * @param boolean $hidden 隠すべきか否か
     * @param string $addLink プロジェクトへのリンクを追加する, はページタイトルの後に表示される
     * @return integer
     */
    public static function addPanel(
        int $index,
        string $fileName,
        string $title,
        string $subTitle,
        string $level,
        bool $hidden = false,
        string $addLink = ''
    ): int {
        $panelTable = unserialize(self::options()->panelTable);
        $panelTable['child'] = empty($panelTable['child']) ? [] : $panelTable['child'];
        $panelTable['child'][$index] = empty($panelTable['child'][$index]) ? [] : $panelTable['child'][$index];
        $fileName = urlencode(trim($fileName, '/'));
        $panelTable['child'][$index][]
            = [$title, $subTitle, 'extending.php?panel=' . $fileName, $level, $hidden, $addLink];

        $panelTable['file'] = empty($panelTable['file']) ? [] : $panelTable['file'];
        $panelTable['file'][] = $fileName;
        $panelTable['file'] = array_unique($panelTable['file']);

        BaseOptions::alloc()->update(
            ['value' => (self::options()->panelTable = serialize($panelTable))],
            Db::get()->sql()->where('name = ?', 'panelTable')
        );

        end($panelTable['child'][$index]);
        return key($panelTable['child'][$index]);
    }

    /**
     * パネルを取り外す
     *
     * @param integer $index メニューインデックス
     * @param string $fileName 文件な
     * @return integer
     */
    public static function removePanel(int $index, string $fileName): int
    {
        $panelTable = unserialize(self::options()->panelTable);
        $panelTable['child'] = empty($panelTable['child']) ? [] : $panelTable['child'];
        $panelTable['child'][$index] = empty($panelTable['child'][$index]) ? [] : $panelTable['child'][$index];
        $panelTable['file'] = empty($panelTable['file']) ? [] : $panelTable['file'];
        $fileName = urlencode(trim($fileName, '/'));

        if (false !== ($key = array_search($fileName, $panelTable['file']))) {
            unset($panelTable['file'][$key]);
        }

        $return = 0;
        foreach ($panelTable['child'][$index] as $key => $val) {
            if ($val[2] == 'extending.php?panel=' . $fileName) {
                unset($panelTable['child'][$index][$key]);
                $return = $key;
            }
        }

        BaseOptions::alloc()->update(
            ['value' => (self::options()->panelTable = serialize($panelTable))],
            Db::get()->sql()->where('name = ?', 'panelTable')
        );
        return $return;
    }

    /**
     * ゲイン面板url
     *
     * @param string $fileName
     * @return string
     */
    public static function url(string $fileName): string
    {
        return Common::url('extending.php?panel=' . (trim($fileName, '/')), self::options()->adminUrl);
    }

    /**
     * プラグイン変数の手動設定
     *
     * @param mixed $pluginName プラグイン名
     * @param array $settings 変数のキーと値のペア
     * @param bool $isPersonal . (default: false) プライベート変数かどうか
     */
    public static function configPlugin($pluginName, array $settings, bool $isPersonal = false)
    {
        if (empty($settings)) {
            return;
        }

        Edit::configPlugin($pluginName, $settings, $isPersonal);
    }

    /**
     * コメント返信ボタン
     *
     * @access public
     * @param string $theId コメント要素id
     * @param integer $coid 解説id
     * @param string $word ボタンテキスト
     * @param string $formId けいしきid
     * @param integer $style スタイルタイプ
     * @return void
     */
    public static function replyLink(
        string $theId,
        int $coid,
        string $word = 'Reply',
        string $formId = 'respond',
        int $style = 2
    ) {
        if (self::options()->commentsThreaded) {
            echo '<a href="#' . $formId . '" rel="nofollow" onclick="return typechoAddCommentReply(\'' .
                $theId . '\', ' . $coid . ', \'' . $formId . '\', ' . $style . ');">' . $word . '</a>';
        }
    }

    /**
     * 解説取消按钮
     *
     * @param string $word ボタンテキスト
     * @param string $formId けいしきid
     */
    public static function cancelCommentReplyLink(string $word = 'Cancel', string $formId = 'respond')
    {
        if (self::options()->commentsThreaded) {
            echo '<a href="#' . $formId . '" rel="nofollow" onclick="return typechoCancelCommentReply(\'' .
                $formId . '\');">' . $word . '</a>';
        }
    }

    /**
     * 解説回复jsスクリプト
     */
    public static function threadedCommentsScript()
    {
        if (self::options()->commentsThreaded) {
            echo
            <<<EOF
<script type="text/javascript">
var typechoAddCommentReply = function (cid, coid, cfid, style) {
    var _ce = document.getElementById(cid), _cp = _ce.parentNode;
    var _cf = document.getElementById(cfid);

    var _pi = document.getElementById('comment-parent');
    if (null == _pi) {
        _pi = document.createElement('input');
        _pi.setAttribute('type', 'hidden');
        _pi.setAttribute('name', 'parent');
        _pi.setAttribute('id', 'comment-parent');

        var _form = 'form' == _cf.tagName ? _cf : _cf.getElementsByTagName('form')[0];

        _form.appendChild(_pi);
    }
    _pi.setAttribute('value', coid);

    if (null == document.getElementById('comment-form-place-holder')) {
        var _cfh = document.createElement('div');
        _cfh.setAttribute('id', 'comment-form-place-holder');
        _cf.parentNode.insertBefore(_cfh, _cf);
    }

    1 == style ? (null == _ce.nextSibling ? _cp.appendChild(_cf)
    : _cp.insertBefore(_cf, _ce.nextSibling)) : _ce.appendChild(_cf);

    return false;
};

var typechoCancelCommentReply = function (cfid) {
    var _cf = document.getElementById(cfid),
    _cfh = document.getElementById('comment-form-place-holder');

    var _pi = document.getElementById('comment-parent');
    if (null != _pi) {
        _pi.parentNode.removeChild(_pi);
    }

    if (null == _cfh) {
        return true;
    }

    _cfh.parentNode.insertBefore(_cf, _cfh);
    return false;
};
</script>
EOF;
        }
    }
}
