<?php

namespace Widget\Metas\Tag;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Widget\Helper\Form;
use Widget\Base\Metas;
use Widget\ActionInterface;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ラベル編集コンポーネント
 *
 * @author qining
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
     * タグが存在するかどうかを判断する
     *
     * @param integer $mid ラベル主キー
     * @return boolean
     * @throws Exception
     */
    public function tagExists(int $mid): bool
    {
        $tag = $this->db->fetchRow($this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('mid = ?', $mid)->limit(1));

        return (bool)$tag;
    }

    /**
     * タグ名が存在するかどうかを判断する
     *
     * @param string $name ラベル名
     * @return boolean
     * @throws Exception
     */
    public function nameExists(string $name): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('name = ?', $name)
            ->limit(1);

        if ($this->request->mid) {
            $select->where('mid <> ?', $this->request->filter('int')->mid);
        }

        $tag = $this->db->fetchRow($select);
        return !$tag;
    }

    /**
     * タグ名が略称に変換されたときに合法かどうかを判断する
     *
     * @param string $name タグ名
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
     * タグの省略形が存在するかどうかを判断する
     *
     * @param string $slug 略称
     * @return boolean
     * @throws Exception
     */
    public function slugExists(string $slug): bool
    {
        $select = $this->db->select()
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('slug = ?', Common::slugName($slug))
            ->limit(1);

        if ($this->request->mid) {
            $select->where('mid <> ?', $this->request->mid);
        }

        $tag = $this->db->fetchRow($select);
        return !$tag;
    }

    /**
     * タグの挿入
     *
     * @throws Exception
     */
    public function insertTag()
    {
        if ($this->form('insert')->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $tag = $this->request->from('name', 'slug');
        $tag['type'] = 'tag';
        $tag['slug'] = Common::slugName(empty($tag['slug']) ? $tag['name'] : $tag['slug']);

        /** データ挿入 */
        $tag['mid'] = $this->insert($tag);
        $this->push($tag);

        /** ハイライトの設定 */
        Notice::alloc()->highlight($this->theId);

        /** アラート */
        Notice::alloc()->set(
            _t('タブ <a href="%s">%s</a> が追加された', $this->permalink, $this->name),
            'success'
        );

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
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
        $form = new Form($this->security->getIndex('/action/metas-tag-edit'), Form::POST_METHOD);

        /** ラベル名 */
        $name = new Form\Element\Text(
            'name',
            null,
            null,
            _t('ラベル名') . ' *',
            _t('这是タブ在站点中显示的名称.中国語で利用可能,まるで "地球".')
        );
        $form->addInput($name);

        /** タブ略称 */
        $slug = new Form\Element\Text(
            'slug',
            null,
            null,
            _t('タブ略称'),
            _t('タブ略称用于创建友好的链接形式, まるで果留空则默认使用ラベル名.')
        );
        $form->addInput($slug);

        /** タブ动作 */
        $do = new Form\Element\Hidden('do');
        $form->addInput($do);

        /** ラベル主キー */
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
                ->where('type = ?', 'tag')->limit(1));

            if (!$meta) {
                $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
            }

            $name->value($meta['name']);
            $slug->value($meta['slug']);
            $do->value('update');
            $mid->value($meta['mid']);
            $submit->value(_t('编辑タブ'));
            $_action = 'update';
        } else {
            $do->value('insert');
            $submit->value(_t('增加タブ'));
            $_action = 'insert';
        }

        if (empty($action)) {
            $action = $_action;
        }

        /** フォームにルールを追加する */
        if ('insert' == $action || 'update' == $action) {
            $name->addRule('required', _t('必须填写ラベル名'));
            $name->addRule([$this, 'nameExists'], _t('ラベル名已经存在'));
            $name->addRule([$this, 'nameToSlug'], _t('ラベル名无法被转换为略称'));
            $name->addRule('xssCheck', _t('请不要ラベル名中使用特殊字符'));
            $slug->addRule([$this, 'slugExists'], _t('略称已经存在'));
            $slug->addRule('xssCheck', _t('请不要在略称中使用特殊字符'));
        }

        if ('update' == $action) {
            $mid->addRule('required', _t('ラベル主キー不存在'));
            $mid->addRule([$this, 'tagExists'], _t('タブ不存在'));
        }

        return $form;
    }

    /**
     * 更新タブ
     *
     * @throws Exception
     */
    public function updateTag()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $tag = $this->request->from('name', 'slug', 'mid');
        $tag['type'] = 'tag';
        $tag['slug'] = Common::slugName(empty($tag['slug']) ? $tag['name'] : $tag['slug']);

        /** 更新データ */
        $this->update($tag, $this->db->sql()->where('mid = ?', $this->request->filter('int')->mid));
        $this->push($tag);

        /** ハイライトの設定 */
        Notice::alloc()->highlight($this->theId);

        /** アラート */
        Notice::alloc()->set(
            _t('タブ <a href="%s">%s</a> 更新されました', $this->permalink, $this->name),
            'success'
        );

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    /**
     * 删除タブ
     *
     * @throws Exception
     */
    public function deleteTag()
    {
        $tags = $this->request->filter('int')->getArray('mid');
        $deleteCount = 0;

        if ($tags && is_array($tags)) {
            foreach ($tags as $tag) {
                if ($this->delete($this->db->sql()->where('mid = ?', $tag))) {
                    $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $tag));
                    $deleteCount++;
                }
            }
        }

        /** アラート */
        Notice::alloc()->set(
            $deleteCount > 0 ? _t('タブ已经删除') : _t('没有タブ被删除'),
            $deleteCount > 0 ? 'success' : 'notice'
        );

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    /**
     * 合并タブ
     *
     * @throws Exception
     */
    public function mergeTag()
    {
        if (empty($this->request->merge)) {
            Notice::alloc()->set(_t('请填写需要合并到的タブ'), 'notice');
            $this->response->goBack();
        }

        $merge = $this->scanTags($this->request->merge);
        if (empty($merge)) {
            Notice::alloc()->set(_t('合并到的タグ名不合法'), 'error');
            $this->response->goBack();
        }

        $tags = $this->request->filter('int')->getArray('mid');

        if ($tags) {
            $this->merge($merge, 'tag', $tags);

            /** アラート */
            Notice::alloc()->set(_t('タブ已经合并'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何タブ'), 'notice');
        }

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-tags.php', $this->options->adminUrl));
    }

    /**
     * 刷新タブ
     *
     * @access public
     * @return void
     * @throws Exception
     */
    public function refreshTag()
    {
        $tags = $this->request->filter('int')->getArray('mid');
        if ($tags) {
            foreach ($tags as $tag) {
                $this->refreshCountByTypeAndStatus($tag, 'post', 'publish');
            }

            // 自动清理タブ
            $this->clearTags();

            Notice::alloc()->set(_t('タブ刷新已经完成'), 'success');
        } else {
            Notice::alloc()->set(_t('没有选择任何タブ'), 'notice');
        }

        /** オリジナルページへ */
        $this->response->goBack();
    }

    /**
     * エントリ機能,イベントをバインドする
     *
     * @access public
     * @return void
     * @throws Exception
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=insert'))->insertTag();
        $this->on($this->request->is('do=update'))->updateTag();
        $this->on($this->request->is('do=delete'))->deleteTag();
        $this->on($this->request->is('do=merge'))->mergeTag();
        $this->on($this->request->is('do=refresh'))->refreshTag();
        $this->response->redirect($this->options->adminUrl);
    }
}
