<?php

namespace Widget\Contents\Attachment;

use Typecho\Common;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Layout;
use Widget\ActionInterface;
use Widget\Contents\Post\Edit as PostEdit;
use Widget\Notice;
use Widget\Upload;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 記事コンポーネントの編集
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends PostEdit implements ActionInterface
{
    /**
     * 実行可能関数
     *
     * @throws Exception|\Typecho\Db\Exception
     */
    public function execute()
    {
        /** コントリビューター以上であること */
        $this->user->pass('contributor');

        /** 記事コンテンツを入手する */
        if (!empty($this->request->cid)) {
            $this->db->fetchRow($this->select()
                ->where('table.contents.type = ?', 'attachment')
                ->where('table.contents.cid = ?', $this->request->filter('int')->cid)
                ->limit(1), [$this, 'push']);

            if (!$this->have()) {
                throw new Exception(_t('ファイルが存在しない'), 404);
            } elseif (!$this->allow('edit')) {
                throw new Exception(_t('編集権限なし'), 403);
            }
        }
    }

    /**
     * ファイル名が省略名に変換されたときに合法かどうかを判断する。
     *
     * @param string $name ファイル名
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
     * ファイルのサムネイルが存在するかどうかを判断する
     *
     * @param string $slug 略称
     * @return boolean
     * @throws \Typecho\Db\Exception
     */
    public function slugExists(string $slug): bool
    {
        $select = $this->db->select()
            ->from('table.contents')
            ->where('type = ?', 'attachment')
            ->where('slug = ?', Common::slugName($slug))
            ->limit(1);

        if ($this->request->cid) {
            $select->where('cid <> ?', $this->request->cid);
        }

        $attachment = $this->db->fetchRow($select);
        return !$attachment;
    }

    /**
     * 更新された資料
     *
     * @throws \Typecho\Db\Exception
     * @throws Exception
     */
    public function updateAttachment()
    {
        if ($this->form('update')->validate()) {
            $this->response->goBack();
        }

        /** データ抽出 */
        $input = $this->request->from('name', 'slug', 'description');
        $input['slug'] = Common::slugName(empty($input['slug']) ? $input['name'] : $input['slug']);

        $attachment['title'] = $input['name'];
        $attachment['slug'] = $input['slug'];

        $content = $this->attachment->toArray();
        $content['description'] = $input['description'];

        $attachment['text'] = serialize($content);
        $cid = $this->request->filter('int')->cid;

        /** 更新データ */
        $updateRows = $this->update($attachment, $this->db->sql()->where('cid = ?', $cid));

        if ($updateRows > 0) {
            $this->db->fetchRow($this->select()
                ->where('table.contents.type = ?', 'attachment')
                ->where('table.contents.cid = ?', $cid)
                ->limit(1), [$this, 'push']);

            /** ハイライトの設定 */
            Notice::alloc()->highlight($this->theId);

            /** アラート */
            Notice::alloc()->set('publish' == $this->status ?
                _t('書類 <a href="%s">%s</a> 更新されました', $this->permalink, $this->title) :
                _t('未归档書類 %s 更新されました', $this->title), 'success');
        }

        /** オリジナルページへ */
        $this->response->redirect(Common::url('manage-medias.php?' .
            $this->getPageOffsetQuery($cid, $this->status), $this->options->adminUrl));
    }

    /**
     * フォームの作成
     *
     * @return Form
     */
    public function form(): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/contents-attachment-edit'), Form::POST_METHOD);

        /** ファイル名称 */
        $name = new Form\Element\Text('name', null, $this->title, _t('キャプション') . ' *');
        $form->addInput($name);

        /** 書類略称 */
        $slug = new Form\Element\Text(
            'slug',
            null,
            $this->slug,
            _t('略称'),
            _t('書類略称用于创建友好的链接形式,お勧めの手紙,数値,アンダーバーとクロスバー.')
        );
        $form->addInput($slug);

        /** 書類説明 */
        $description = new Form\Element\Textarea(
            'description',
            null,
            $this->attachment->description,
            _t('説明'),
            _t('此文字用于説明書類,を持つスレッドに表示される。.')
        );
        $form->addInput($description);

        /** アクションの並べ替え */
        $do = new Form\Element\Hidden('do', null, 'update');
        $form->addInput($do);

        /** カテゴリー・プライマリ・キー */
        $cid = new Form\Element\Hidden('cid', null, $this->cid);
        $form->addInput($cid);

        /** 送信ボタン */
        $submit = new Form\Element\Submit(null, null, _t('変更を提出する'));
        $submit->input->setAttribute('class', 'btn primary');
        $delete = new Layout('a', [
            'href'  => $this->security->getIndex('/action/contents-attachment-edit?do=delete&cid=' . $this->cid),
            'class' => 'operate-delete',
            'lang'  => _t('你确认删除書類 %s (質問タグ)?', $this->attachment->name)
        ]);
        $submit->container($delete->html(_t('删除書類')));
        $form->addItem($submit);

        $name->addRule('required', _t('必须填写書類キャプション'));
        $name->addRule([$this, 'nameToSlug'], _t('書類キャプション无法被转换为略称'));
        $slug->addRule([$this, 'slugExists'], _t('略称已经存在'));

        return $form;
    }

    /**
     * のページオフセットを取得します。URL Query
     *
     * @param integer $cid 書類id
     * @param string|null $status 情勢
     * @return string
     * @throws \Typecho\Db\Exception|Exception
     */
    protected function getPageOffsetQuery(int $cid, string $status = null): string
    {
        return 'page=' . $this->getPageOffset(
            'cid',
            $cid,
            'attachment',
            $status,
            $this->user->pass('editor', true) ? 0 : $this->user->uid
        );
    }

    /**
     * 記事を削除
     *
     * @throws \Typecho\Db\Exception
     */
    public function deleteAttachment()
    {
        $posts = $this->request->filter('int')->getArray('cid');
        $deleteCount = 0;

        foreach ($posts as $post) {
            // プラグインインターフェースの削除
            self::pluginHandle()->delete($post, $this);

            $condition = $this->db->sql()->where('cid = ?', $post);
            $row = $this->db->fetchRow($this->select()
                ->where('table.contents.type = ?', 'attachment')
                ->where('table.contents.cid = ?', $post)
                ->limit(1), [$this, 'push']);

            if ($this->isWriteable(clone $condition) && $this->delete($condition)) {
                /** 删除書類 */
                Upload::deleteHandle($row);

                /** コメント削除 */
                $this->db->query($this->db->delete('table.comments')
                    ->where('cid = ?', $post));

                // 完成プラグインインターフェースの削除
                self::pluginHandle()->finishDelete($post, $this);

                $deleteCount++;
            }

            unset($condition);
        }

        if ($this->request->isAjax()) {
            $this->response->throwJson($deleteCount > 0 ? ['code' => 200, 'message' => _t('書類已经被删除')]
                : ['code' => 500, 'message' => _t('没有書類被删除')]);
        } else {
            /** 设置アラート */
            Notice::alloc()
                ->set(
                    $deleteCount > 0 ? _t('書類已经被删除') : _t('没有書類被删除'),
                    $deleteCount > 0 ? 'success' : 'notice'
                );

            /** 元のページに戻る */
            $this->response->redirect(Common::url('manage-medias.php', $this->options->adminUrl));
        }
    }

    /**
     * clearAttachment
     *
     * @access public
     * @return void
     * @throws \Typecho\Db\Exception
     */
    public function clearAttachment()
    {
        $page = 1;
        $deleteCount = 0;

        do {
            $posts = array_column($this->db->fetchAll($this->select('cid')
                ->from('table.contents')
                ->where('type = ? AND parent = ?', 'attachment', 0)
                ->page($page, 100)), 'cid');
            $page++;

            foreach ($posts as $post) {
                // プラグインインターフェースの削除
                self::pluginHandle()->delete($post, $this);

                $condition = $this->db->sql()->where('cid = ?', $post);
                $row = $this->db->fetchRow($this->select()
                    ->where('table.contents.type = ?', 'attachment')
                    ->where('table.contents.cid = ?', $post)
                    ->limit(1), [$this, 'push']);

                if ($this->isWriteable(clone $condition) && $this->delete($condition)) {
                    /** 删除書類 */
                    Upload::deleteHandle($row);

                    /** コメント削除 */
                    $this->db->query($this->db->delete('table.comments')
                        ->where('cid = ?', $post));

                    $status = $this->status;

                    // 完成プラグインインターフェースの削除
                    self::pluginHandle()->finishDelete($post, $this);

                    $deleteCount++;
                }

                unset($condition);
            }
        } while (count($posts) == 100);

        /** 设置アラート */
        Notice::alloc()->set(
            $deleteCount > 0 ? _t('未归档書類已经被清理') : _t('没有未归档書類被清理'),
            $deleteCount > 0 ? 'success' : 'notice'
        );

        /** 元のページに戻る */
        $this->response->redirect(Common::url('manage-medias.php', $this->options->adminUrl));
    }

    /**
     * バインド・アクション
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=delete'))->deleteAttachment();
        $this->on($this->have() && $this->request->is('do=update'))->updateAttachment();
        $this->on($this->request->is('do=clear'))->clearAttachment();
        $this->response->redirect($this->options->adminUrl);
    }
}
