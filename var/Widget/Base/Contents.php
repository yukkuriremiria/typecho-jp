<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Config;
use Typecho\Cookie;
use Typecho\Date;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Plugin;
use Typecho\Router;
use Typecho\Widget;
use Utils\AutoP;
use Utils\Markdown;
use Widget\Base;
use Widget\Metas\Category\Rows;
use Widget\Upload;
use Widget\Users\Author;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コンテンツ・ベース・クラス
 *
 * @property int $cid
 * @property string $title
 * @property string $slug
 * @property int $created
 * @property int $modified
 * @property string $text
 * @property int $order
 * @property int $authorId
 * @property string $template
 * @property string $type
 * @property string $status
 * @property string|null $password
 * @property int $commentsNum
 * @property bool $allowComment
 * @property bool $allowPing
 * @property bool $allowFeed
 * @property int $parent
 * @property int $parentId
 * @property-read Users $author
 * @property-read string $permalink
 * @property-read string $url
 * @property-read string $feedUrl
 * @property-read string $feedRssUrl
 * @property-read string $feedAtomUrl
 * @property-read bool $isMarkdown
 * @property-read bool $hidden
 * @property-read string $category
 * @property-read Date $date
 * @property-read string $dateWord
 * @property-read string[] $directory
 * @property-read array $tags
 * @property-read array $categories
 * @property-read string $description
 * @property-read string $excerpt
 * @property-read string $summary
 * @property-read string $content
 * @property-read Config $fields
 * @property-read Config $attachment
 * @property-read string $theId
 * @property-read string $respondId
 * @property-read string $commentUrl
 * @property-read string $trackbackUrl
 * @property-read string $responseUrl
 */
class Contents extends Base implements QueryInterface
{
    /**
     * クエリーオブジェクトの取得
     *
     * @return Query
     * @throws Exception
     */
    public function select(): Query
    {
        return $this->db->select(
            'table.contents.cid',
            'table.contents.title',
            'table.contents.slug',
            'table.contents.created',
            'table.contents.authorId',
            'table.contents.modified',
            'table.contents.type',
            'table.contents.status',
            'table.contents.text',
            'table.contents.commentsNum',
            'table.contents.order',
            'table.contents.template',
            'table.contents.password',
            'table.contents.allowComment',
            'table.contents.allowPing',
            'table.contents.allowFeed',
            'table.contents.parent'
        )->from('table.contents');
    }

    /**
     * コンテンツ挿入
     *
     * @param array $rows エレメント配列
     * @return integer
     * @throws Exception
     */
    public function insert(array $rows): int
    {
        /** 建築物の挿入構造 */
        $insertStruct = [
            'title'        => !isset($rows['title']) || strlen($rows['title']) === 0
                ? null : htmlspecialchars($rows['title']),
            'created'      => !isset($rows['created']) ? $this->options->time : $rows['created'],
            'modified'     => $this->options->time,
            'text'         => !isset($rows['text']) || strlen($rows['text']) === 0 ? null : $rows['text'],
            'order'        => empty($rows['order']) ? 0 : intval($rows['order']),
            'authorId'     => $rows['authorId'] ?? $this->user->uid,
            'template'     => empty($rows['template']) ? null : $rows['template'],
            'type'         => empty($rows['type']) ? 'post' : $rows['type'],
            'status'       => empty($rows['status']) ? 'publish' : $rows['status'],
            'password'     => !isset($rows['password']) || strlen($rows['password']) === 0 ? null : $rows['password'],
            'commentsNum'  => empty($rows['commentsNum']) ? 0 : $rows['commentsNum'],
            'allowComment' => !empty($rows['allowComment']) && 1 == $rows['allowComment'] ? 1 : 0,
            'allowPing'    => !empty($rows['allowPing']) && 1 == $rows['allowPing'] ? 1 : 0,
            'allowFeed'    => !empty($rows['allowFeed']) && 1 == $rows['allowFeed'] ? 1 : 0,
            'parent'       => empty($rows['parent']) ? 0 : intval($rows['parent'])
        ];

        if (!empty($rows['cid'])) {
            $insertStruct['cid'] = $rows['cid'];
        }

        /** 最初にデータを挿入する */
        $insertId = $this->db->query($this->db->insert('table.contents')->rows($insertStruct));

        /** 略称の更新 */
        if ($insertId > 0) {
            $this->applySlug(!isset($rows['slug']) || strlen($rows['slug']) === 0 ? null : $rows['slug'], $insertId);
        }

        return $insertId;
    }

    /**
     * コンテンツに略称を適用する
     *
     * @param string|null $slug 略称
     * @param mixed $cid エレメントid
     * @return string
     * @throws Exception
     */
    public function applySlug(?string $slug, $cid): string
    {
        if ($cid instanceof Query) {
            $cid = $this->db->fetchObject($cid->select('cid')
                ->from('table.contents')->limit(1))->cid;
        }

        /** 生成一个非空的略称 */
        $slug = Common::slugName($slug, $cid);
        $result = $slug;

        /** 草案のslugスペシャルハンドリング */
        $draft = $this->db->fetchObject($this->db->select('type', 'parent')
            ->from('table.contents')->where('cid = ?', $cid));

        if ('_draft' == substr($draft->type, - 6) && $draft->parent) {
            $result = '@' . $result;
        }


        /** データベースにすでに存在するかどうかを判断する */
        $count = 1;
        while (
            $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
                ->from('table.contents')->where('slug = ? AND cid <> ?', $result, $cid))->num > 0
        ) {
            $result = $slug . '-' . $count;
            $count++;
        }

        $this->db->query($this->db->update('table.contents')->rows(['slug' => $result])
            ->where('cid = ?', $cid));

        return $result;
    }

    /**
     * 更新エレメント
     *
     * @param array $rows エレメント配列
     * @param Query $condition 更新前提前提条件
     * @return integer
     * @throws Exception
     */
    public function update(array $rows, Query $condition): int
    {
        /** 最初に書き込みアクセスを検証する */
        if (!$this->isWriteable(clone $condition)) {
            return 0;
        }

        /** 刷新された体制作り */
        $preUpdateStruct = [
            'title'        => !isset($rows['title']) || strlen($rows['title']) === 0
                ? null : htmlspecialchars($rows['title']),
            'order'        => empty($rows['order']) ? 0 : intval($rows['order']),
            'text'         => !isset($rows['text']) || strlen($rows['text']) === 0 ? null : $rows['text'],
            'template'     => empty($rows['template']) ? null : $rows['template'],
            'type'         => empty($rows['type']) ? 'post' : $rows['type'],
            'status'       => empty($rows['status']) ? 'publish' : $rows['status'],
            'password'     => empty($rows['password']) ? null : $rows['password'],
            'allowComment' => !empty($rows['allowComment']) && 1 == $rows['allowComment'] ? 1 : 0,
            'allowPing'    => !empty($rows['allowPing']) && 1 == $rows['allowPing'] ? 1 : 0,
            'allowFeed'    => !empty($rows['allowFeed']) && 1 == $rows['allowFeed'] ? 1 : 0,
            'parent'       => empty($rows['parent']) ? 0 : intval($rows['parent'])
        ];

        $updateStruct = [];
        foreach ($rows as $key => $val) {
            if (array_key_exists($key, $preUpdateStruct)) {
                $updateStruct[$key] = $preUpdateStruct[$key];
            }
        }

        /** 更新作成時間 */
        if (isset($rows['created'])) {
            $updateStruct['created'] = $rows['created'];
        }

        $updateStruct['modified'] = $this->options->time;

        /** 最初にデータを挿入する */
        $updateCondition = clone $condition;
        $updateRows = $this->db->query($condition->update('table.contents')->rows($updateStruct));

        /** 略称の更新 */
        if ($updateRows > 0 && isset($rows['slug'])) {
            $this->applySlug(!isset($rows['slug']) || strlen($rows['slug']) === 0
                ? null : $rows['slug'], $updateCondition);
        }

        return $updateRows;
    }

    /**
     * エレメント是否可以被修改
     *
     * @param Query $condition 前提前提条件
     * @return bool
     * @throws Exception
     */
    public function isWriteable(Query $condition): bool
    {
        $post = $this->db->fetchRow($condition->select('authorId')->from('table.contents')->limit(1));
        return $post && ($this->user->pass('editor', true) || $post['authorId'] == $this->user->uid);
    }

    /**
     * 删除エレメント
     *
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.contents'));
    }

    /**
     * カスタムフィールドの削除
     *
     * @param integer $cid
     * @return integer
     * @throws Exception
     */
    public function deleteFields(int $cid): int
    {
        return $this->db->query($this->db->delete('table.fields')
            ->where('cid = ?', $cid));
    }

    /**
     * カスタムフィールドの保存
     *
     * @param array $fields
     * @param mixed $cid
     * @return void
     * @throws Exception
     */
    public function applyFields(array $fields, $cid)
    {
        $exists = array_flip(array_column($this->db->fetchAll($this->db->select('name')
            ->from('table.fields')->where('cid = ?', $cid)), 'name'));

        foreach ($fields as $name => $value) {
            $type = 'str';

            if (is_array($value) && 2 == count($value)) {
                $type = $value[0];
                $value = $value[1];
            } elseif (strpos($name, ':') > 0) {
                [$type, $name] = explode(':', $name, 2);
            }

            if (!$this->checkFieldName($name)) {
                continue;
            }

            $isFieldReadOnly = Contents::pluginHandle()->trigger($plugged)->isFieldReadOnly($name);
            if ($plugged && $isFieldReadOnly) {
                continue;
            }

            if (isset($exists[$name])) {
                unset($exists[$name]);
            }

            $this->setField($name, $type, $value, $cid);
        }

        foreach ($exists as $name => $value) {
            $this->db->query($this->db->delete('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * フィールド名の適合性チェック
     *
     * @param string $name
     * @return boolean
     */
    public function checkFieldName(string $name): bool
    {
        return preg_match("/^[_a-z][_a-z0-9]*$/i", $name);
    }

    /**
     * 個々のフィールドの設定
     *
     * @param string $name
     * @param string $type
     * @param mixed $value
     * @param integer $cid
     * @return integer|bool
     * @throws Exception
     */
    public function setField(string $name, string $type, $value, int $cid)
    {
        if (
            empty($name) || !$this->checkFieldName($name)
            || !in_array($type, ['str', 'int', 'float', 'json'])
        ) {
            return false;
        }

        if ($type === 'json') {
            $value = json_encode($value);
        }

        $exist = $this->db->fetchRow($this->db->select('cid')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        $rows = [
            'type'        => $type,
            'str_value'   => 'str' == $type || 'json' == $type ? $value : null,
            'int_value'   => 'int' == $type ? intval($value) : 0,
            'float_value' => 'float' == $type ? floatval($value) : 0
        ];

        if (empty($exist)) {
            $rows['cid'] = $cid;
            $rows['name'] = $name;

            return $this->db->query($this->db->insert('table.fields')->rows($rows));
        } else {
            return $this->db->query($this->db->update('table.fields')
                ->rows($rows)
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * プラスチックフィールドの追加
     *
     * @param string $name
     * @param integer $value
     * @param integer $cid
     * @return integer
     * @throws Exception
     */
    public function incrIntField(string $name, int $value, int $cid)
    {
        if (!$this->checkFieldName($name)) {
            return false;
        }

        $exist = $this->db->fetchRow($this->db->select('type')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));
        $value = intval($value);

        if (empty($exist)) {
            return $this->db->query($this->db->insert('table.fields')
                ->rows([
                    'cid'         => $cid,
                    'name'        => $name,
                    'type'        => 'int',
                    'str_value'   => null,
                    'int_value'   => $value,
                    'float_value' => 0
                ]));
        } else {
            $struct = [
                'str_value'   => null,
                'float_value' => null
            ];

            if ('int' != $exist['type']) {
                $struct['type'] = 'int';
            }

            return $this->db->query($this->db->update('table.fields')
                ->rows($struct)
                ->expression('int_value', 'int_value ' . ($value >= 0 ? '+' : '') . $value)
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * 按照前提前提条件计算エレメント数量
     *
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition
            ->select(['COUNT(DISTINCT table.contents.cid)' => 'num'])
            ->from('table.contents')
            ->cleanAttribute('group'))->num;
    }

    /**
     * 現在のカスタムテンプレートをすべて取得する
     *
     * @return array
     */
    public function getTemplates(): array
    {
        $files = glob($this->options->themeFile($this->options->theme, '*.php'));
        $result = [];

        foreach ($files as $file) {
            $info = Plugin::parseInfo($file);
            $file = basename($file);

            if ('index.php' != $file && 'custom' == $info['title']) {
                $result[$file] = $info['description'];
            }
        }

        return $result;
    }

    /**
     * 各行の値をスタックに押し込む
     *
     * @param array $value 1行あたりの価値
     * @return array
     */
    public function push(array $value): array
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    /**
     * 汎用フィルター
     *
     * @param array $value フィルタリングする行データ
     * @return array
     */
    public function filter(array $value): array
    {
        /** デフォルトのヌル値の処理 */
        $value['title'] = $value['title'] ?? '';
        $value['text'] = $value['text'] ?? '';
        $value['slug'] = $value['slug'] ?? '';

        /** すべてのカテゴリーを外す */
        $value['categories'] = $this->db->fetchAll($this->db
            ->select()->from('table.metas')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $value['cid'])
            ->where('table.metas.type = ?', 'category'), [Rows::alloc(), 'filter']);

        $value['category'] = '';
        $value['directory'] = [];

        /** という最初の分類を外す。slug前提前提条件 */
        if (!empty($value['categories'])) {
            /** カスタムソートの使用 */
            usort($value['categories'], function ($a, $b) {
                $field = 'order';
                if ($a['order'] == $b['order']) {
                    $field = 'mid';
                }

                return $a[$field] < $b[$field] ? - 1 : 1;
            });

            $value['category'] = $value['categories'][0]['slug'];

            $value['directory'] = Rows::alloc()
                ->getAllParentsSlug($value['categories'][0]['mid']);
            $value['directory'][] = $value['category'];
        }

        $value['date'] = new Date($value['created']);

        /** 年代 */
        $value['year'] = $value['date']->year;
        $value['month'] = $value['date']->month;
        $value['day'] = $value['date']->day;

        /** アクセス権の生成 */
        $value['hidden'] = false;

        /** ルートタイプを取得し、このタイプがルーティングテーブルに存在するかどうかを判断する */
        $type = $value['type'];
        $routeExists = (null != Router::get($type));

        $tmpSlug = $value['slug'];
        $tmpCategory = $value['category'];
        $tmpDirectory = $value['directory'];
        $value['slug'] = urlencode($value['slug']);
        $value['category'] = urlencode($value['category']);
        $value['directory'] = implode('/', array_map('urlencode', $value['directory']));

        /** 静的パスの生成 */
        $value['pathinfo'] = $routeExists ? Router::url($type, $value) : '#';

        /** 静的リンクの生成 */
        $value['url'] = $value['permalink'] = Common::url($value['pathinfo'], $this->options->index);

        /** 附属書の処理 */
        if ('attachment' == $type) {
            $content = @unserialize($value['text']);

            //データ情報の追加
            $value['attachment'] = new Config($content);
            $value['attachment']->isImage = in_array($content['type'], ['jpg', 'jpeg', 'gif', 'png', 'tiff', 'bmp', 'webp', 'avif']);
            $value['attachment']->url = Upload::attachmentHandle($value);

            if ($value['attachment']->isImage) {
                $value['text'] = '<img src="' . $value['attachment']->url . '" alt="' .
                    $value['title'] . '" />';
            } else {
                $value['text'] = '<a href="' . $value['attachment']->url . '" title="' .
                    $value['title'] . '">' . $value['title'] . '</a>';
            }
        }

        /** 扱うMarkdown **/
        if (isset($value['text'])) {
            $value['isMarkdown'] = (0 === strpos($value['text'], '<!--markdown-->'));
            if ($value['isMarkdown']) {
                $value['text'] = substr($value['text'], 15);
            }
        }

        /** 集約リンクの生成 */
        /** RSS 2.0 */
        $value['feedUrl'] = $routeExists ? Router::url($type, $value, $this->options->feedUrl) : '#';

        /** RSS 1.0 */
        $value['feedRssUrl'] = $routeExists ? Router::url($type, $value, $this->options->feedRssUrl) : '#';

        /** ATOM 1.0 */
        $value['feedAtomUrl'] = $routeExists ? Router::url($type, $value, $this->options->feedAtomUrl) : '#';

        $value['slug'] = $tmpSlug;
        $value['category'] = $tmpCategory;
        $value['directory'] = $tmpDirectory;

        /** 扱う密码保护流程 */
        if (
            strlen($value['password'] ?? '') > 0 &&
            $value['password'] !== Cookie::get('protectPassword_' . $value['cid']) &&
            $value['authorId'] != $this->user->uid &&
            !$this->user->pass('editor', true)
        ) {
            $value['hidden'] = true;
        }

        $value = Contents::pluginHandle()->filter($value, $this);

        /** アクセスが拒否された場合 */
        if ($value['hidden']) {
            $value['text'] = '<form class="protected" action="' . $this->security->getTokenUrl($value['permalink'])
                . '" method="post">' .
                '<p class="word">' . _t('アクセスするにはパスワードを入力してください。') . '</p>' .
                '<p><input type="password" class="text" name="protectPassword" />
            <input type="hidden" name="protectCID" value="' . $value['cid'] . '" />
            <input type="submit" class="submit" value="' . _t('だす') . '" /></p>' .
                '</form>';

            $value['title'] = _t('此エレメント被密码保护');
            $value['tags'] = [];
            $value['commentsNum'] = 0;
        }

        return $value;
    }

    /**
     * 記事が掲載された日付を出力
     *
     * @param string|null $format 日付形式
     */
    public function date(?string $format = null)
    {
        echo $this->date->format(empty($format) ? $this->options->postDateFormat : $format);
    }

    /**
     * 输出文章エレメント
     *
     * @param mixed $more 記事インターセプト接尾辞
     */
    public function content($more = false)
    {
        echo false !== $more && false !== strpos($this->text, '<!--more-->') ?
            $this->excerpt
                . "<p class=\"more\"><a href=\"{$this->permalink}\" title=\"{$this->title}\">{$more}</a></p>"
            : $this->content;
    }

    /**
     * 出力記事のアブストラクト
     *
     * @param integer $length 概要 インターセプトの長さ
     * @param string $trim 抽象的接尾辞
     */
    public function excerpt(int $length = 100, string $trim = '...')
    {
        echo Common::subStr(strip_tags($this->excerpt), 0, $length, $trim);
    }

    /**
     * 出力タイトル
     *
     * @param integer $length タイトル インターセプトの長さ
     * @param string $trim 接尾辞のインターセプト
     */
    public function title(int $length = 0, string $trim = '...')
    {
        $title = Contents::pluginHandle()->trigger($plugged)->title($this->title, $this);
        if (!$plugged) {
            echo $length > 0 ? Common::subStr($this->title, 0, $length, $trim) : $this->title;
        } else {
            echo $title;
        }
    }

    /**
     * 記事のコメント数を出力する
     *
     * @param ...$args
     */
    public function commentsNum(...$args)
    {
        if (empty($args)) {
            $args[] = '%d';
        }

        $num = intval($this->commentsNum);
        echo sprintf($args[$num] ?? array_pop($args), $num);
    }

    /**
     * 記事の許可を得る
     *
     * @param ...$permissions
     */
    public function allow(...$permissions): bool
    {
        $allow = true;

        foreach ($permissions as $permission) {
            $permission = strtolower($permission);

            if ('edit' == $permission) {
                $allow &= ($this->user->pass('editor', true) || $this->authorId == $this->user->uid);
            } else {
                /** 自動クローズ・フィードバックのサポート */
                if (
                    ('ping' == $permission || 'comment' == $permission) && $this->options->commentsPostTimeout > 0 &&
                    $this->options->commentsAutoClose
                ) {
                    if ($this->options->time - $this->created > $this->options->commentsPostTimeout) {
                        return false;
                    }
                }

                $allow &= ($this->row['allow' . ucfirst($permission)] == 1) and !$this->hidden;
            }
        }

        return $allow;
    }

    /**
     * 出力記事のカテゴリー
     *
     * @param string $split 複数の分類の間のセパレーター
     * @param boolean $link リンクを出力するかどうか
     * @param string|null $default そうでない場合は、次のように出力する。
     */
    public function category(string $split = ',', bool $link = true, ?string $default = null)
    {
        $categories = $this->categories;
        if ($categories) {
            $result = [];

            foreach ($categories as $category) {
                $result[] = $link ? '<a href="' . $category['permalink'] . '">'
                    . $category['name'] . '</a>' : $category['name'];
            }

            echo implode($split, $result);
        } else {
            echo $default;
        }
    }

    /**
     * 複数のカテゴリーで記事を出力
     *
     * @param string $split 複数の分類の間のセパレーター
     * @param boolean $link リンクを出力するかどうか
     * @param string|null $default そうでない場合は、次のように出力する。
     * @throws \Typecho\Widget\Exception
     */
    public function directory(string $split = '/', bool $link = true, ?string $default = null)
    {
        $category = $this->categories[0];
        $directory = Rows::alloc()->getAllParents($category['mid']);
        $directory[] = $category;

        if ($directory) {
            $result = [];

            foreach ($directory as $category) {
                $result[] = $link ? '<a href="' . $category['permalink'] . '">'
                    . $category['name'] . '</a>' : $category['name'];
            }

            echo implode($split, $result);
        } else {
            echo $default;
        }
    }

    /**
     * 出力記事のタグ
     *
     * @param string $split 複数のタグの間のセパレータ
     * @param boolean $link リンクを出力するかどうか
     * @param string|null $default そうでない場合は、次のように出力する。
     */
    public function tags(string $split = ',', bool $link = true, ?string $default = null)
    {
        /** プラスチック射出tags */
        if ($this->tags) {
            $result = [];
            foreach ($this->tags as $tag) {
                $result[] = $link ? '<a href="' . $tag['permalink'] . '">'
                    . $tag['name'] . '</a>' : $tag['name'];
            }

            echo implode($split, $result);
        } else {
            echo $default;
        }
    }

    /**
     * 現在の著者をエクスポートする
     *
     * @param string $item エクスポートする項目
     */
    public function author(string $item = 'screenName')
    {
        if ($this->have()) {
            echo $this->author->{$item};
        }
    }

    /**
     * そうしれいかんtagsプラスチック射出
     *
     * @return array
     * @throws Exception
     */
    protected function ___tags(): array
    {
        return $this->db->fetchAll($this->db
            ->select()->from('table.metas')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $this->cid)
            ->where('table.metas.type = ?', 'tag'), [Metas::alloc(), 'filter']);
    }

    /**
     * 論文執筆者
     *
     * @return Users
     */
    protected function ___author(): Users
    {
        return Author::allocWithAlias($this->cid, ['uid' => $this->authorId]);
    }

    /**
     * 字句解析日の取得
     *
     * @return string
     */
    protected function ___dateWord(): string
    {
        return $this->date->word();
    }

    /**
     * 親を得るid
     *
     * @return int|null
     */
    protected function ___parentId(): ?int
    {
        return $this->row['parent'];
    }

    /**
     * 短いプレーンテキストによる記事の説明
     *
     * @return string|null
     */
    protected function ___description(): ?string
    {
        $plainTxt = str_replace("\n", '', trim(strip_tags($this->excerpt)));
        $plainTxt = $plainTxt ? $plainTxt : $this->title;
        return Common::subStr($plainTxt, 0, 100, '...');
    }

    /**
     * ___fields
     *
     * @return Config
     * @throws Exception
     */
    protected function ___fields(): Config
    {
        $fields = [];
        $rows = $this->db->fetchAll($this->db->select()->from('table.fields')
            ->where('cid = ?', $this->cid));

        foreach ($rows as $row) {
            $value = 'json' == $row['type'] ? json_decode($row['str_value'], true) : $row[$row['type'] . '_value'];
            $fields[$row['name']] = $value;
        }

        return new Config($fields);
    }

    /**
     * 获取文章エレメント摘要
     *
     * @return string|null
     */
    protected function ___excerpt(): ?string
    {
        if ($this->hidden) {
            return $this->text;
        }

        $content = Contents::pluginHandle()->trigger($plugged)->excerpt($this->text, $this);
        if (!$plugged) {
            $content = $this->isMarkdown ? $this->markdown($content)
                : $this->autoP($content);
        }

        $contents = explode('<!--more-->', $content);
        [$excerpt] = $contents;

        return Common::fixHtml(Contents::pluginHandle()->excerptEx($excerpt, $this));
    }

    /**
     * markdown
     *
     * @param string|null $text
     * @return string|null
     */
    public function markdown(?string $text): ?string
    {
        $html = Contents::pluginHandle()->trigger($parsed)->markdown($text);

        if (!$parsed) {
            $html = Markdown::convert($text);
        }

        return $html;
    }

    /**
     * autoP
     *
     * @param string|null $text
     * @return string|null
     */
    public function autoP(?string $text): ?string
    {
        $html = Contents::pluginHandle()->trigger($parsed)->autoP($text);

        if (!$parsed && $text) {
            static $parser;

            if (empty($parser)) {
                $parser = new AutoP();
            }

            $html = $parser->parse($text);
        }

        return $html;
    }

    /**
     * 获取文章エレメント
     *
     * @return string|null
     */
    protected function ___content(): ?string
    {
        if ($this->hidden) {
            return $this->text;
        }

        $content = Contents::pluginHandle()->trigger($plugged)->content($this->text, $this);

        if (!$plugged) {
            $content = $this->isMarkdown ? $this->markdown($content)
                : $this->autoP($content);
        }

        return Contents::pluginHandle()->contentEx($content, $this);
    }

    /**
     * 記事の最初の行を要約として出力する。
     *
     * @return string|null
     */
    protected function ___summary(): ?string
    {
        $content = $this->content;
        $parts = preg_split("/(<\/\s*(?:p|blockquote|q|pre|table)\s*>)/i", $content, 2, PREG_SPLIT_DELIM_CAPTURE);
        if (!empty($parts)) {
            $content = $parts[0] . $parts[1];
        }

        return $content;
    }

    /**
     * アンカーポイントid
     *
     * @return string
     */
    protected function ___theId(): string
    {
        return $this->type . '-' . $this->cid;
    }

    /**
     * レスポンスボックスid
     *
     * @return string
     */
    protected function ___respondId(): string
    {
        return 'respond-' . $this->theId;
    }

    /**
     * コメントアドレス
     *
     * @return string
     */
    protected function ___commentUrl(): string
    {
        /** フィードバック・アドレスの作成 */
        /** 解説 */
        return Router::url(
            'feedback',
            ['type' => 'comment', 'permalink' => $this->pathinfo],
            $this->options->index
        );
    }

    /**
     * trackbackアドレス
     *
     * @return string
     */
    protected function ___trackbackUrl(): string
    {
        return Router::url(
            'feedback',
            ['type' => 'trackback', 'permalink' => $this->pathinfo],
            $this->options->index
        );
    }

    /**
     * 回复アドレス
     *
     * @return string
     */
    protected function ___responseUrl(): string
    {
        return $this->permalink . '#' . $this->respondId;
    }

    /**
     * ページオフセット取得
     *
     * @param string $column フィールド名
     * @param integer $offset オフセット値
     * @param string $type 類型論
     * @param string|null $status ステータス値
     * @param integer $authorId 著者
     * @param integer $pageSize ページネーション値
     * @return integer
     * @throws Exception
     */
    protected function getPageOffset(
        string $column,
        int $offset,
        string $type,
        ?string $status = null,
        int $authorId = 0,
        int $pageSize = 20
    ): int {
        $select = $this->db->select(['COUNT(table.contents.cid)' => 'num'])->from('table.contents')
            ->where("table.contents.{$column} > {$offset}")
            ->where(
                "table.contents.type = ? OR (table.contents.type = ? AND table.contents.parent = ?)",
                $type,
                $type . '_draft',
                0
            );

        if (!empty($status)) {
            $select->where("table.contents.status = ?", $status);
        }

        if ($authorId > 0) {
            $select->where('table.contents.authorId = ?', $authorId);
        }

        $count = $this->db->fetchObject($select)->num + 1;
        return ceil($count / $pageSize);
    }
}
