<?php

namespace Widget\Metas\Category;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Validate;
use Typecho\Widget\Helper\Form;
use Widget\Base\Metas;
use Widget\ActionInterface;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * カテゴリー・コンポーネントの編集
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends Metas implements ActionInterface
{
    /**
     * エントリ機能
     */
    public function execute()
    {
        /** 上記のパーミッションを編集する */
        $this->user->pass('editor');
    }

    /**
     * 分類が存在するかどうかの判断
     *
     * @param integer $mid カテゴリー・プライマリ・キー
     * @return boolean
     * @throws Exception
     */
    public function categoryExists(int $mid): bool
    {
        $category = $this->db->fetchRow($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('mid = ?', $mid)->limit(1));

        return (bool)$category;
    }

    /**
     * 分類名が存在するかどうかを判断する
     *
     * @param string $name 分類名
     * @return boolean
     * @throws Exception
     */
    public function nameExists(string $name): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('name = ?', $name)
            ->limit(1);

        if ($this->request->mid) {
            $select->where('mid <> ?', $this->request->mid);
        }

        $category = $this->db->fetchRow($select);
        return !$category;
    }

    /**
     * カテゴリー名が省略名に変換されたときに合法かどうかを判断する。
     *
     * @param string $name 分類名
     * @return boolean
     */
    public function nameToSlug(string $name): bool
    {
        if (empty($this->request->slug)) {
            $slug = Common::slugName($name);
            if (empty($slug) || !$this->slugExists($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * カテゴリの略称が存在するかどうかを判断する
     *
     * @param string $slug 略称
     * @return boolean
     * @throws Exception
     */
    public function slugExists(string $slug): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('slug = ?', Common::slugName($slug))
            ->limit(1);

        if ($this->request->mid) {
            $select->where('mid <> ?', $this->request->mid);
        }

        $category = $this->db->fetchRow($select);
        return !$category;
    }

    /**
     * カテゴリーに追加する
     *
     * @throws Exception
     */
    public function insertCategory()
    {
        if ($this->form('insert')->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $category = $this->request->from('name', 'slug', 'description', 'parent');

        $category['slug'] = Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']);
        $category['type'] = 'category';
        $category['order'] = $this->getMaxOrder('category', $category['parent']) + 1;

        /** データ挿入 */
        $category['mid'] = $this->insert($category);
        $this->push($category);

        /** ハイライトの設定 */
        Notice::alloc()->highlight($this->theId);

        /** アラート */
        Notice::alloc()->set(
            _t('分類 <a href="%s">%s</a> が追加された', $this->permalink, $this->name),
            'success'
        );

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-categories.php'
            . ($category['parent'] ? '?parent=' . $category['parent'] : ''), $this->options->adminUrl));
    }

    /**
     * フォームの作成
     *
     * @param string|null $action フォームアクション
     * @return Form
     * @throws Exception
     */
    public function form(?string $action = null): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/metas-category-edit'), Form::POST_METHOD);

        /** 分類名 */
        $name = new Form\Element\Text('name', null, null, _t('分類名') . ' *');
        $form->addInput($name);

        /** 分類略称 */
        $slug = new Form\Element\Text(
            'slug',
            null,
            null,
            _t('分類略称'),
            _t('分類略称用于创建友好的链接形式, お勧めの手紙, 数値, アンダーバーとクロスバー.')
        );
        $form->addInput($slug);

        /** 父级分類 */
        $options = [0 => _t('非選択性')];
        $parents = Rows::allocWithAlias(
            'options',
            (isset($this->request->mid) ? 'ignore=' . $this->request->mid : '')
        );

        while ($parents->next()) {
            $options[$parents->mid] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $parents->levels) . $parents->name;
        }

        $parent = new Form\Element\Select(
            'parent',
            $options,
            $this->request->parent,
            _t('父级分類'),
            _t('此分類将归档在您选择的父级分類下.')
        );
        $form->addInput($parent);

        /** 分類描述 */
        $description = new Form\Element\Textarea(
            'description',
            null,
            null,
            _t('分類描述'),
            _t('此文字用于描述分類, を持つスレッドに表示される。.')
        );
        $form->addInput($description);

        /** 分類动作 */
        $do = new Form\Element\Hidden('do');
        $form->addInput($do);

        /** カテゴリー・プライマリ・キー */
        $mid = new Form\Element\Hidden('mid');
        $form->addInput($mid);

        /** 送信ボタン */
        $submit = new Form\Element\Submit();
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        if (isset($this->request->mid) && 'insert' != $action) {
            /** 更新モード */
            $meta = $this->db->fetchRow($this->select()
                ->where('mid = ?', $this->request->mid)
                ->where('type = ?', 'category')->limit(1));

            if (!$meta) {
                $this->response->redirect(Common::url('manage-categories.php', $this->options->adminUrl));
            }

            $name->value($meta['name']);
            $slug->value($meta['slug']);
            $parent->value($meta['parent']);
            $description->value($meta['description']);
            $do->value('update');
            $mid->value($meta['mid']);
            $submit->value(_t('编辑分類'));
            $_action = 'update';
        } else {
            $do->value('insert');
            $submit->value(_t('カテゴリーに追加する'));
            $_action = 'insert';
        }

        if (empty($action)) {
            $action = $_action;
        }

        /** フォームにルールを追加する */
        if ('insert' == $action || 'update' == $action) {
            $name->addRule('required', _t('必须填写分類名'));
            $name->addRule([$this, 'nameExists'], _t('分類名已经存在'));
            $name->addRule([$this, 'nameToSlug'], _t('分類名无法被转换为略称'));
            $name->addRule('xssCheck', _t('请不要在分類名中使用特殊字符'));
            $slug->addRule([$this, 'slugExists'], _t('略称已经存在'));
            $slug->addRule('xssCheck', _t('请不要在略称中使用特殊字符'));
        }

        if ('update' == $action) {
            $mid->addRule('required', _t('カテゴリー・プライマリ・キー不存在'));
            $mid->addRule([$this, 'categoryExists'], _t('分類不存在'));
        }

        return $form;
    }

    /**
     * 更新分類
     *
     * @throws Exception
     */
    public function updateCategory()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $category = $this->request->from('name', 'slug', 'description', 'parent');
        $category['mid'] = $this->request->mid;
        $category['slug'] = Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']);
        $category['type'] = 'category';
        $current = $this->db->fetchRow($this->select()->where('mid = ?', $category['mid']));

        if ($current['parent'] != $category['parent']) {
            $parent = $this->db->fetchRow($this->select()->where('mid = ?', $category['parent']));

            if ($parent['mid'] == $category['mid']) {
                $category['order'] = $parent['order'];
                $this->update([
                    'parent' => $current['parent'],
                    'order'  => $current['order']
                ], $this->db->sql()->where('mid = ?', $parent['mid']));
            } else {
                $category['order'] = $this->getMaxOrder('category', $category['parent']) + 1;
            }
        }

        /** 更新データ */
        $this->update($category, $this->db->sql()->where('mid = ?', $this->request->filter('int')->mid));
        $this->push($category);

        /** ハイライトの設定 */
        Notice::alloc()->highlight($this->theId);

        /** アラート */
        Notice::alloc()
            ->set(_t('分類 <a href="%s">%s</a> 更新されました', $this->permalink, $this->name), 'success');

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-categories.php'
            . ($category['parent'] ? '?parent=' . $category['parent'] : ''), $this->options->adminUrl));
    }

    /**
     * 删除分類
     *
     * @access public
     * @return void
     * @throws Exception
     */
    public function deleteCategory()
    {
        $categories = $this->request->filter('int')->getArray('mid');
        $deleteCount = 0;

        foreach ($categories as $category) {
            $parent = $this->db->fetchObject($this->select()->where('mid = ?', $category))->parent;

            if ($this->delete($this->db->sql()->where('mid = ?', $category))) {
                $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $category));
                $this->update(['parent' => $parent], $this->db->sql()->where('parent = ?', $category));
                $deleteCount++;
            }
        }

        /** アラート */
        Notice::alloc()
            ->set($deleteCount > 0 ? _t('分類已经删除') : _t('没有分類被删除'), $deleteCount > 0 ? 'success' : 'notice');

        /** オリジナルページへ */
        $this->response->goBack();
    }

    /**
     * 合并分類
     */
    public function mergeCategory()
    {
        /** 検証データ */
        $validator = new Validate();
        $validator->addRule('merge', 'required', _t('カテゴリー・プライマリ・キー不存在'));
        $validator->addRule('merge', [$this, 'categoryExists'], _t('请选择需要合并的分類'));

        if ($error = $validator->run($this->request->from('merge'))) {
            Notice::alloc()->set($error, 'error');
            $this->response->goBack();
        }

        $merge = $this->request->merge;
        $categories = $this->request->filter('int')->getArray('mid');

        if ($categories) {
            $this->merge($merge, 'category', $categories);

            /** アラート */
            Notice::alloc()->set(_t('分類已经合并'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何分類'), 'notice');
        }

        /** オリジナルページへ */
        $this->response->goBack();
    }

    /**
     * 分類排序
     */
    public function sortCategory()
    {
        $categories = $this->request->filter('int')->getArray('mid');
        if ($categories) {
            $this->sort($categories, 'category');
        }

        if (!$this->request->isAjax()) {
            /** オリジナルページへ */
            $this->response->redirect(Common::url('manage-categories.php', $this->options->adminUrl));
        } else {
            $this->response->throwJson(['success' => 1, 'message' => _t('分類排序已经完成')]);
        }
    }

    /**
     * 刷新分類
     *
     * @throws Exception
     */
    public function refreshCategory()
    {
        $categories = $this->request->filter('int')->getArray('mid');
        if ($categories) {
            foreach ($categories as $category) {
                $this->refreshCountByTypeAndStatus($category, 'post', 'publish');
            }

            Notice::alloc()->set(_t('分類刷新已经完成'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何分類'), 'notice');
        }

        /** オリジナルページへ */
        $this->response->goBack();
    }

    /**
     * 设置默认分類
     *
     * @throws Exception
     */
    public function defaultCategory()
    {
        /** 検証データ */
        $validator = new Validate();
        $validator->addRule('mid', 'required', _t('カテゴリー・プライマリ・キー不存在'));
        $validator->addRule('mid', [$this, 'categoryExists'], _t('分類不存在'));

        if ($error = $validator->run($this->request->from('mid'))) {
            Notice::alloc()->set($error, 'error');
        } else {
            $this->db->query($this->db->update('table.options')
                ->rows(['value' => $this->request->mid])
                ->where('name = ?', 'defaultCategory'));

            $this->db->fetchRow($this->select()->where('mid = ?', $this->request->mid)
                ->where('type = ?', 'category')->limit(1), [$this, 'push']);

            /** ハイライトの設定 */
            Notice::alloc()->highlight($this->theId);

            /** アラート */
            Notice::alloc()->set(
                _t('<a href="%s">%s</a> 已经被设为默认分類', $this->permalink, $this->name),
                'success'
            );
        }

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-categories.php', $this->options->adminUrl));
    }

    /**
     * メニュータイトルの取得
     *
     * @return string|null
     * @throws \Typecho\Widget\Exception|Exception
     */
    public function getMenuTitle(): ?string
    {
        if (isset($this->request->mid)) {
            $category = $this->db->fetchRow($this->select()
                ->where('type = ? AND mid = ?', 'category', $this->request->mid));

            if (!empty($category)) {
                return _t('编辑分類 %s', $category['name']);
            }

        }
        if (isset($this->request->parent)) {
            $category = $this->db->fetchRow($this->select()
                ->where('type = ? AND mid = ?', 'category', $this->request->parent));

            if (!empty($category)) {
                return _t('追加 %s 的子分類', $category['name']);
            }

        } else {
            return null;
        }

        throw new \Typecho\Widget\Exception(_t('分類不存在'), 404);
    }

    /**
     * エントリ機能
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=insert'))->insertCategory();
        $this->on($this->request->is('do=update'))->updateCategory();
        $this->on($this->request->is('do=delete'))->deleteCategory();
        $this->on($this->request->is('do=merge'))->mergeCategory();
        $this->on($this->request->is('do=sort'))->sortCategory();
        $this->on($this->request->is('do=refresh'))->refreshCategory();
        $this->on($this->request->is('do=default'))->defaultCategory();
        $this->response->redirect($this->options->adminUrl);
    }
}
