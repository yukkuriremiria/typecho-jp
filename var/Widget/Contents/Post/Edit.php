<?php

namespace Widget\Contents\Post;

use Typecho\Common;
use Typecho\Config;
use Typecho\Validate;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;
use Widget\Base\Contents;
use Widget\Base\Metas;
use Widget\ActionInterface;
use Typecho\Db\Exception as DbException;
use Typecho\Date as TypechoDate;
use Widget\Notice;
use Widget\Service;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 記事コンポーネントの編集
 *
 * @property-read array|null $draft
 */
class Edit extends Contents implements ActionInterface
{
    /**
     * のカスタムフィールドhookな
     *
     * @var string
     */
    protected $themeCustomFieldsHook = 'themePostFields';

    /**
     * 実行可能関数
     *
     * @throws Exception|DbException
     */
    public function execute()
    {
        /** コントリビューター以上であること */
        $this->user->pass('contributor');

        /** 記事コンテンツを入手する */
        if (!empty($this->request->cid)) {
            $this->db->fetchRow($this->select()
                ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'post_draft')
                ->where('table.contents.cid = ?', $this->request->filter('int')->cid)
                ->limit(1), [$this, 'push']);

            if ('post_draft' == $this->type && $this->parent) {
                $this->response->redirect(
                    Common::url('write-post.php?cid=' . $this->parent, $this->options->adminUrl)
                );
            }

            if (!$this->have()) {
                throw new Exception(_t('記事は存在しない。'), 404);
            } elseif (!$this->allow('edit')) {
                throw new Exception(_t('編集権限なし'), 403);
            }
        }
    }

    /**
     * 記事の許可を得る
     *
     * @param mixed ...$permissions
     * @return bool
     * @throws Exception|DbException
     */
    public function allow(...$permissions): bool
    {
        $allow = true;

        foreach ($permissions as $permission) {
            $permission = strtolower($permission);

            if ('edit' == $permission) {
                $allow &= ($this->user->pass('editor', true) || $this->authorId == $this->user->uid);
            } else {
                $permission = 'allow' . ucfirst(strtolower($permission));
                $optionPermission = 'default' . ucfirst($permission);
                $allow &= ($this->{$permission} ?? $this->options->{$optionPermission});
            }
        }

        return $allow;
    }

    /**
     * フィルタースタック
     *
     * @param array $value 1行あたりの価値
     * @return array
     * @throws DbException
     */
    public function filter(array $value): array
    {
        if ('post' == $value['type'] || 'page' == $value['type']) {
            $draft = $this->db->fetchRow(Contents::alloc()->select()
                ->where(
                    'table.contents.parent = ? AND table.contents.type = ?',
                    $value['cid'],
                    $value['type'] . '_draft'
                )
                ->limit(1));

            if (!empty($draft)) {
                $draft['slug'] = ltrim($draft['slug'], '@');
                $draft['type'] = $value['type'];

                $draft = parent::filter($draft);

                $draft['tags'] = $this->db->fetchAll($this->db
                    ->select()->from('table.metas')
                    ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', $draft['cid'])
                    ->where('table.metas.type = ?', 'tag'), [Metas::alloc(), 'filter']);
                $draft['cid'] = $value['cid'];

                return $draft;
            }
        }

        return parent::filter($value);
    }

    /**
     * 記事が掲載された日付を出力
     *
     * @param string $format 日付形式
     * @return void
     */
    public function date($format = null)
    {
        if (isset($this->created)) {
            parent::date($format);
        } else {
            echo date($format, $this->options->time + $this->options->timezone - $this->options->serverTimezone);
        }
    }

    /**
     * ページタイトルの取得
     *
     * @return string
     */
    public function getMenuTitle(): string
    {
        return _t('コンパイラ %s', $this->title);
    }

    /**
     * getFieldItems
     *
     * @throws DbException
     */
    public function getFieldItems(): array
    {
        $fields = [];

        if ($this->have()) {
            $defaultFields = $this->getDefaultFieldItems();
            $rows = $this->db->fetchAll($this->db->select()->from('table.fields')
                ->where('cid = ?', $this->cid));

            foreach ($rows as $row) {
                $isFieldReadOnly = Contents::pluginHandle()
                    ->trigger($plugged)->isFieldReadOnly($row['name']);

                if ($plugged && $isFieldReadOnly) {
                    continue;
                }

                if (!isset($defaultFields[$row['name']])) {
                    $fields[] = $row;
                }
            }
        }

        return $fields;
    }

    /**
     * getDefaultFieldItems
     *
     * @return array
     */
    public function getDefaultFieldItems(): array
    {
        $defaultFields = [];
        $configFile = $this->options->themeFile($this->options->theme, 'functions.php');
        $layout = new Layout();
        $fields = new Config();

        if ($this->have()) {
            $fields = $this->fields;
        }

        self::pluginHandle()->getDefaultFieldItems($layout);

        if (file_exists($configFile)) {
            require_once $configFile;

            if (function_exists('themeFields')) {
                themeFields($layout);
            }

            if (function_exists($this->themeCustomFieldsHook)) {
                call_user_func($this->themeCustomFieldsHook, $layout);
            }
        }

        $items = $layout->getItems();
        foreach ($items as $item) {
            if ($item instanceof Element) {
                $name = $item->input->getAttribute('name');

                $isFieldReadOnly = Contents::pluginHandle()
                    ->trigger($plugged)->isFieldReadOnly($name);
                if ($plugged && $isFieldReadOnly) {
                    continue;
                }

                if (preg_match("/^fields\[(.+)\]$/", $name, $matches)) {
                    $name = $matches[1];
                } else {
                    $inputName = 'fields[' . $name . ']';
                    if (preg_match("/^(.+)\[\]$/", $name, $matches)) {
                        $name = $matches[1];
                        $inputName = 'fields[' . $name . '][]';
                    }

                    foreach ($item->inputs as $input) {
                        $input->setAttribute('name', $inputName);
                    }
                }

                if (isset($fields->{$name})) {
                    $item->value($fields->{$name});
                }

                $elements = $item->container->getItems();
                array_shift($elements);
                $div = new Layout('div');

                foreach ($elements as $el) {
                    $div->addItem($el);
                }

                $defaultFields[$name] = [$item->label, $div];
            }
        }

        return $defaultFields;
    }

    /**
     * 出版記事
     */
    public function writePost()
    {
        $contents = $this->request->from(
            'password',
            'allowComment',
            'allowPing',
            'allowFeed',
            'slug',
            'tags',
            'text',
            'visibility'
        );

        $contents['category'] = $this->request->getArray('category');
        $contents['title'] = $this->request->get('title', _t('無題の文書'));
        $contents['created'] = $this->getCreated();

        if ($this->request->markdown && $this->options->markdown) {
            $contents['text'] = '<!--markdown-->' . $contents['text'];
        }

        $contents = self::pluginHandle()->write($contents, $this);

        if ($this->request->is('do=publish')) {
            /** 既存の記事の再投稿 */
            $contents['type'] = 'post';
            $this->publish($contents);

            // プラグイン・インターフェイスの公開を終了する
            self::pluginHandle()->finishPublish($contents, $this);

            /** 送信ping */
            $trackback = array_filter(array_unique(preg_split("/(\r|\n|\r\n)/", trim($this->request->trackback))));
            Service::alloc()->sendPing($this, $trackback);

            /** アラートメッセージの設定 */
            Notice::alloc()->set('post' == $this->type ?
                _t('文 "<a href="%s">%s</a>" 出版', $this->permalink, $this->title) :
                _t('文 "%s" 審査待ち', $this->title), 'success');

            /** ハイライトの設定 */
            Notice::alloc()->highlight($this->theId);

            /** ページオフセット取得 */
            $pageQuery = $this->getPageOffsetQuery($this->cid);

            /** 新しいページにジャンプする */
            $this->response->redirect(Common::url('manage-posts.php?' . $pageQuery, $this->options->adminUrl));
        } else {
            /** 保存文 */
            $contents['type'] = 'post_draft';
            $this->save($contents);

            // プラグインインターフェースの保存を終了する
            self::pluginHandle()->finishSave($contents, $this);

            /** ハイライトの設定 */
            Notice::alloc()->highlight($this->cid);

            if ($this->request->isAjax()) {
                $created = new TypechoDate();
                $this->response->throwJson([
                    'success' => 1,
                    'time'    => $created->format('H:i:s A'),
                    'cid'     => $this->cid,
                    'draftId' => $this->draft['cid']
                ]);
            } else {
                /** アラートメッセージの設定 */
                Notice::alloc()->set(_t('概要 "%s" 保存済み', $this->title), 'success');

                /** 元のページに戻る */
                $this->response->redirect(Common::url('write-post.php?cid=' . $this->cid, $this->options->adminUrl));
            }
        }
    }

    /**
     * 送信に基づく値を取得するcreatedフィールド値
     *
     * @return integer
     */
    protected function getCreated(): int
    {
        $created = $this->options->time;
        if (!empty($this->request->created)) {
            $created = $this->request->created;
        } elseif (!empty($this->request->date)) {
            $dstOffset = !empty($this->request->dst) ? $this->request->dst : 0;
            $timezoneSymbol = $this->options->timezone >= 0 ? '+' : '-';
            $timezoneOffset = abs($this->options->timezone);
            $timezone = $timezoneSymbol . str_pad($timezoneOffset / 3600, 2, '0', STR_PAD_LEFT) . ':00';
            [$date, $time] = explode(' ', $this->request->date);

            $created = strtotime("{$date}T{$time}{$timezone}") - $dstOffset;
        } elseif (!empty($this->request->year) && !empty($this->request->month) && !empty($this->request->day)) {
            $second = intval($this->request->get('sec', date('s')));
            $min = intval($this->request->get('min', date('i')));
            $hour = intval($this->request->get('hour', date('H')));

            $year = intval($this->request->year);
            $month = intval($this->request->month);
            $day = intval($this->request->day);

            $created = mktime($hour, $min, $second, $month, $day, $year)
                - $this->options->timezone + $this->options->serverTimezone;
        } elseif ($this->have() && $this->created > 0) {
            //如果是修改文
            $created = $this->created;
        } elseif ($this->request->is('do=save')) {
            // 如果是概要而且没有任何输入则保持原状
            $created = 0;
        }

        return $created;
    }

    /**
     * 投稿エレメント
     *
     * @param array $contents コンテンツ構造
     * @throws DbException|Exception
     */
    protected function publish(array $contents)
    {
        /** 投稿エレメント, 直接公開する許可を得ているか確認する */
        if ($this->user->pass('editor', true)) {
            if (empty($contents['visibility'])) {
                $contents['status'] = 'publish';
            } elseif (
                !in_array($contents['visibility'], ['private', 'waiting', 'publish', 'hidden'])
            ) {
                if (empty($contents['password']) || 'password' != $contents['visibility']) {
                    $contents['password'] = '';
                }
                $contents['status'] = 'publish';
            } else {
                $contents['status'] = $contents['visibility'];
                $contents['password'] = '';
            }
        } else {
            $contents['status'] = 'waiting';
            $contents['password'] = '';
        }

        /** 本物のコンテンツid */
        $realId = 0;

        /** 是否是从概要情勢发布 */
        $isDraftToPublish = ('post_draft' == $this->type || 'page_draft' == $this->type);

        $isBeforePublish = ('publish' == $this->status);
        $isAfterPublish = ('publish' == $contents['status']);

        /** 既存コンテンツのリパブリッシング */
        if ($this->have()) {

            /** 如果它本身不是概要, 需要删除其概要 */
            if (!$isDraftToPublish && $this->draft) {
                $cid = $this->draft['cid'];
                $this->deleteDraft($cid);
                $this->deleteFields($cid);
            }

            /** 直接そうしれいかん概要情勢更改 */
            if ($this->update($contents, $this->db->sql()->where('cid = ?', $this->cid))) {
                $realId = $this->cid;
            }
        } else {
            /** 新しいコンテンツを公開する */
            $realId = $this->insert($contents);
        }

        if ($realId > 0) {
            /** カテゴリー挿入 */
            if (array_key_exists('category', $contents)) {
                $this->setCategories(
                    $realId,
                    !empty($contents['category']) && is_array($contents['category'])
                        ? $contents['category'] : [$this->options->defaultCategory],
                    !$isDraftToPublish && $isBeforePublish,
                    $isAfterPublish
                );
            }

            /** タグの挿入 */
            if (array_key_exists('tags', $contents)) {
                $this->setTags($realId, $contents['tags'], !$isDraftToPublish && $isBeforePublish, $isAfterPublish);
            }

            /** シンクロアクセサリー */
            $this->attach($realId);

            /** カスタムフィールドの保存 */
            $this->applyFields($this->getFields(), $realId);

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $realId)->limit(1), [$this, 'push']);
        }
    }

    /**
     * 删除概要
     *
     * @param integer $cid 概要id
     * @throws DbException
     */
    protected function deleteDraft($cid)
    {
        $this->delete($this->db->sql()->where('cid = ?', $cid));

        /** 删除概要分類 */
        $this->setCategories($cid, [], false, false);

        /** タグの削除 */
        $this->setTags($cid, null, false, false);
    }

    /**
     * カテゴリーの設定
     *
     * @param integer $cid エレメントid
     * @param array $categories 分類id集合配列
     * @param boolean $beforeCount カウントに参加するか否か
     * @param boolean $afterCount カウントに参加するか否か
     * @throws DbException
     */
    public function setCategories(int $cid, array $categories, bool $beforeCount = true, bool $afterCount = true)
    {
        $categories = array_unique(array_map('trim', $categories));

        /** 抜粋category */
        $existCategories = array_column(
            $this->db->fetchAll(
                $this->db->select('table.metas.mid')
                    ->from('table.metas')
                    ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', $cid)
                    ->where('table.metas.type = ?', 'category')
            ),
            'mid'
        );

        /** 既存の削除category */
        if ($existCategories) {
            foreach ($existCategories as $category) {
                $this->db->query($this->db->delete('table.relationships')
                    ->where('cid = ?', $cid)
                    ->where('mid = ?', $category));

                if ($beforeCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count - 1')
                        ->where('mid = ?', $category));
                }
            }
        }

        /** スティックcategory */
        if ($categories) {
            foreach ($categories as $category) {
                /** 如果分類不存在 */
                if (
                    !$this->db->fetchRow(
                        $this->db->select('mid')
                        ->from('table.metas')
                        ->where('mid = ?', $category)
                        ->limit(1)
                    )
                ) {
                    continue;
                }

                $this->db->query($this->db->insert('table.relationships')
                    ->rows([
                        'mid' => $category,
                        'cid' => $cid
                    ]));

                if ($afterCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count + 1')
                        ->where('mid = ?', $category));
                }
            }
        }
    }

    /**
     * 设置エレメント标签
     *
     * @param integer $cid
     * @param string|null $tags
     * @param boolean $beforeCount カウントに参加するか否か
     * @param boolean $afterCount カウントに参加するか否か
     * @throws DbException
     */
    public function setTags(int $cid, ?string $tags, bool $beforeCount = true, bool $afterCount = true)
    {
        $tags = str_replace('，', ',', $tags);
        $tags = array_unique(array_map('trim', explode(',', $tags)));
        $tags = array_filter($tags, [Validate::class, 'xssCheck']);

        /** 抜粋tag */
        $existTags = array_column(
            $this->db->fetchAll(
                $this->db->select('table.metas.mid')
                ->from('table.metas')
                ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $cid)
                ->where('table.metas.type = ?', 'tag')
            ),
            'mid'
        );

        /** 既存の削除tag */
        if ($existTags) {
            foreach ($existTags as $tag) {
                if (0 == strlen($tag)) {
                    continue;
                }

                $this->db->query($this->db->delete('table.relationships')
                    ->where('cid = ?', $cid)
                    ->where('mid = ?', $tag));

                if ($beforeCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count - 1')
                        ->where('mid = ?', $tag));
                }
            }
        }

        /** プラスチック射出スティックtag */
        $insertTags = Metas::alloc()->scanTags($tags);

        /** スティックtag */
        if ($insertTags) {
            foreach ($insertTags as $tag) {
                if (0 == strlen($tag)) {
                    continue;
                }

                $this->db->query($this->db->insert('table.relationships')
                    ->rows([
                        'mid' => $tag,
                        'cid' => $cid
                    ]));

                if ($afterCount) {
                    $this->db->query($this->db->update('table.metas')
                        ->expression('count', 'count + 1')
                        ->where('mid = ?', $tag));
                }
            }
        }
    }

    /**
     * シンクロアクセサリー
     *
     * @param integer $cid エレメントid
     * @throws DbException
     */
    protected function attach(int $cid)
    {
        $attachments = $this->request->getArray('attachment');
        if (!empty($attachments)) {
            foreach ($attachments as $key => $attachment) {
                $this->db->query($this->db->update('table.contents')->rows([
                    'parent' => $cid,
                    'status' => 'publish',
                    'order'  => $key + 1
                ])->where('cid = ? AND type = ?', $attachment, 'attachment'));
            }
        }
    }

    /**
     * getFields
     *
     * @return array
     */
    protected function getFields(): array
    {
        $fields = [];
        $fieldNames = $this->request->getArray('fieldNames');

        if (!empty($fieldNames)) {
            $data = [
                'fieldNames'  => $this->request->getArray('fieldNames'),
                'fieldTypes'  => $this->request->getArray('fieldTypes'),
                'fieldValues' => $this->request->getArray('fieldValues')
            ];
            foreach ($data['fieldNames'] as $key => $val) {
                $val = trim($val);

                if (0 == strlen($val)) {
                    continue;
                }

                $fields[$val] = [$data['fieldTypes'][$key], $data['fieldValues'][$key]];
            }
        }

        $customFields = $this->request->getArray('fields');
        foreach ($customFields as $key => $val) {
            $fields[$key] = [is_array($val) ? 'json' : 'str', $val];
        }

        return $fields;
    }

    /**
     * ページオフセット取得的URL Query
     *
     * @param integer $cid 文id
     * @param string|null $status 情勢
     * @return string
     * @throws DbException
     */
    protected function getPageOffsetQuery(int $cid, ?string $status = null): string
    {
        return 'page=' . $this->getPageOffset(
            'cid',
            $cid,
            'post',
            $status,
            'on' == $this->request->__typecho_all_posts ? 0 : $this->user->uid
        );
    }

    /**
     * 保存エレメント
     *
     * @param array $contents コンテンツ構造
     * @throws DbException|Exception
     */
    protected function save(array $contents)
    {
        /** 投稿エレメント, 直接公開する許可を得ているか確認する */
        if ($this->user->pass('editor', true)) {
            if (empty($contents['visibility'])) {
                $contents['status'] = 'publish';
            } elseif (
                !in_array($contents['visibility'], ['private', 'waiting', 'publish', 'hidden'])
            ) {
                if (empty($contents['password']) || 'password' != $contents['visibility']) {
                    $contents['password'] = '';
                }
                $contents['status'] = 'publish';
            } else {
                $contents['status'] = $contents['visibility'];
                $contents['password'] = '';
            }
        } else {
            $contents['status'] = 'waiting';
            $contents['password'] = '';
        }

        /** 本物のコンテンツid */
        $realId = 0;

        /** 如果概要已经存在 */
        if ($this->draft) {

            /** 直接そうしれいかん概要情勢更改 */
            if ($this->update($contents, $this->db->sql()->where('cid = ?', $this->draft['cid']))) {
                $realId = $this->draft['cid'];
            }
        } else {
            if ($this->have()) {
                $contents['parent'] = $this->cid;
            }

            /** 新しいコンテンツを公開する */
            $realId = $this->insert($contents);

            if (!$this->have()) {
                $this->db->fetchRow(
                    $this->select()->where('table.contents.cid = ?', $realId)->limit(1),
                    [$this, 'push']
                );
            }
        }

        if ($realId > 0) {
            /** カテゴリー挿入 */
            if (array_key_exists('category', $contents)) {
                $this->setCategories($realId, !empty($contents['category']) && is_array($contents['category']) ?
                    $contents['category'] : [$this->options->defaultCategory], false, false);
            }

            /** タグの挿入 */
            if (array_key_exists('tags', $contents)) {
                $this->setTags($realId, $contents['tags'], false, false);
            }

            /** シンクロアクセサリー */
            $this->attach($this->cid);

            /** カスタムフィールドの保存 */
            $this->applyFields($this->getFields(), $realId);
        }
    }

    /**
     * 标记文
     *
     * @throws DbException
     */
    public function markPost()
    {
        $status = $this->request->get('status');
        $statusList = [
            'publish' => _t('公然と'),
            'private' => _t('親しい'),
            'hidden'  => _t('ネッスル'),
            'waiting' => _t('承認待ち')
        ];

        if (!isset($statusList[$status])) {
            $this->response->goBack();
        }

        $posts = $this->request->filter('int')->getArray('cid');
        $markCount = 0;

        foreach ($posts as $post) {
            // タグ付けプラグインのインターフェース
            self::pluginHandle()->mark($status, $post, $this);

            $condition = $this->db->sql()->where('cid = ?', $post);
            $postObject = $this->db->fetchObject($this->db->select('status', 'type')
                ->from('table.contents')->where('cid = ? AND (type = ? OR type = ?)', $post, 'post', 'post_draft'));

            if ($this->isWriteable(clone $condition) && count((array)$postObject)) {

                /** 标记情勢 */
                $this->db->query($condition->update('table.contents')->rows(['status' => $status]));

                // リフレッシュMetas
                if ($postObject->type == 'post') {
                    $op = null;

                    if ($status == 'publish' && $postObject->status != 'publish') {
                        $op = '+';
                    } elseif ($status != 'publish' && $postObject->status == 'publish') {
                        $op = '-';
                    }

                    if (!empty($op)) {
                        $metas = $this->db->fetchAll(
                            $this->db->select()->from('table.relationships')->where('cid = ?', $post)
                        );
                        foreach ($metas as $meta) {
                            $this->db->query($this->db->update('table.metas')
                                ->expression('count', 'count ' . $op . ' 1')
                                ->where('mid = ? AND (type = ? OR type = ?)', $meta['mid'], 'category', 'tag'));
                        }
                    }
                }

                // 处理概要
                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $post, 'post_draft')
                    ->limit(1));

                if (!empty($draft)) {
                    $this->db->query($this->db->update('table.contents')->rows(['status' => $status])
                        ->where('cid = ?', $draft['cid']));
                }

                // 完成タグ付けプラグインのインターフェース
                self::pluginHandle()->finishMark($status, $post, $this);

                $markCount++;
            }

            unset($condition);
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $markCount > 0 ? _t('文已经被标记为<strong>%s</strong>', $statusList[$status]) : _t('没有文被标记'),
                $markCount > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * 删除文
     *
     * @throws DbException
     */
    public function deletePost()
    {
        $posts = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($posts as $post) {
            // プラグインインターフェースの削除
            self::pluginHandle()->delete($post, $this);

            $condition = $this->db->sql()->where('cid = ?', $post);
            $postObject = $this->db->fetchObject($this->db->select('status', 'type')
                ->from('table.contents')->where('cid = ? AND (type = ? OR type = ?)', $post, 'post', 'post_draft'));

            if ($this->isWriteable(clone $condition) && count((array)$postObject) && $this->delete($condition)) {

                /** 删除分類 */
                $this->setCategories($post, [], 'publish' == $postObject->status
                    && 'post' == $postObject->type);

                /** タグの削除 */
                $this->setTags($post, null, 'publish' == $postObject->status
                    && 'post' == $postObject->type);

                /** コメント削除 */
                $this->db->query($this->db->delete('table.comments')
                    ->where('cid = ?', $post));

                /** 添付ファイルのリンク解除 */
                $this->unAttach($post);

                /** 删除概要 */
                $draft = $this->db->fetchRow($this->db->select('cid')
                    ->from('table.contents')
                    ->where('table.contents.parent = ? AND table.contents.type = ?', $post, 'post_draft')
                    ->limit(1));

                /** カスタムフィールドの削除 */
                $this->deleteFields($post);

                if ($draft) {
                    $this->deleteDraft($draft['cid']);
                    $this->deleteFields($draft['cid']);
                }

                // 完成プラグインインターフェースの削除
                self::pluginHandle()->finishDelete($post, $this);

                $deleteCount++;
            }

            unset($condition);
        }

        // ラベルをきれいにする
        if ($deleteCount > 0) {
            Metas::alloc()->clearTags();
        }

        /** アラートメッセージの設定 */
        Notice::alloc()->set(
            $deleteCount > 0 ? _t('文已经被删除') : _t('没有文被删除'),
            $deleteCount > 0 ? 'success' : 'notice'
        );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * アタッチメント・アソシエーションの解除
     *
     * @param integer $cid エレメントid
     * @throws DbException
     */
    protected function unAttach($cid)
    {
        $this->db->query($this->db->update('table.contents')->rows(['parent' => 0, 'status' => 'publish'])
            ->where('parent = ? AND type = ?', $cid, 'attachment'));
    }

    /**
     * 删除文所属概要
     *
     * @throws DbException
     */
    public function deletePostDraft()
    {
        $posts = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($posts as $post) {
            /** 删除概要 */
            $draft = $this->db->fetchRow($this->db->select('cid')
                ->from('table.contents')
                ->where('table.contents.parent = ? AND table.contents.type = ?', $post, 'post_draft')
                ->limit(1));

            if ($draft) {
                $this->deleteDraft($draft['cid']);
                $this->deleteFields($draft['cid']);
                $deleteCount++;
            }
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $deleteCount > 0 ? _t('概要已经被删除') : _t('没有概要被删除'),
                $deleteCount > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * バインド・アクション
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=publish') || $this->request->is('do=save'))->writePost();
        $this->on($this->request->is('do=delete'))->deletePost();
        $this->on($this->request->is('do=mark'))->markPost();
        $this->on($this->request->is('do=deleteDraft'))->deletePostDraft();

        $this->response->redirect($this->options->adminUrl);
    }

    /**
     * そうしれいかんtagsプラスチック射出
     *
     * @return array
     * @throws DbException
     */
    protected function ___tags(): array
    {
        if ($this->have()) {
            return $this->db->fetchAll($this->db
                ->select()->from('table.metas')
                ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $this->cid)
                ->where('table.metas.type = ?', 'tag'), [Metas::alloc(), 'filter']);
        }

        return [];
    }

    /**
     * 現在時刻の取得
     *
     * @return TypechoDate
     */
    protected function ___date(): TypechoDate
    {
        return new TypechoDate();
    }

    /**
     * 当前文的概要
     *
     * @return array|null
     * @throws DbException
     */
    protected function ___draft(): ?array
    {
        if ($this->have()) {
            if ('post_draft' == $this->type || 'page_draft' == $this->type) {
                return $this->row;
            } else {
                return $this->db->fetchRow(Contents::alloc()->select()
                    ->where(
                        'table.contents.parent = ? AND (table.contents.type = ? OR table.contents.type = ?)',
                        $this->cid,
                        'post_draft',
                        'page_draft'
                    )
                    ->limit(1), [Contents::alloc(), 'filter']);
            }
        }

        return null;
    }
}
