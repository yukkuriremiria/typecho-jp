<?php

namespace Widget\Comments;

use Typecho\Db\Exception;
use Widget\Base\Comments;
use Widget\ActionInterface;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コメント編集コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Edit extends Comments implements ActionInterface
{
    /**
     * レビュー用マーク
     */
    public function waitingComment()
    {
        $comments = $this->request->filter('int')->getArray('coid');
        $updateRows = 0;

        foreach ($comments as $comment) {
            if ($this->mark($comment, 'waiting')) {
                $updateRows++;
            }
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $updateRows > 0 ? _t('评论已经被レビュー用マーク') : _t('没有评论被レビュー用マーク'),
                $updateRows > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * コメントステータスをマークする
     *
     * @param integer $coid コメント主キー
     * @param string $status 情勢
     * @return boolean
     * @throws Exception
     */
    private function mark($coid, $status)
    {
        $comment = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($comment && $this->commentIsWriteable()) {
            /** コメント編集プラグインのインターフェイスを追加 */
            self::pluginHandle()->mark($comment, $this, $status);

            /** 更新が不要な状況 */
            if ($status == $comment['status']) {
                return false;
            }

            /** コメント更新 */
            $this->db->query($this->db->update('table.comments')
                ->rows(['status' => $status])->where('coid = ?', $coid));

            /** 関連コンテンツのコメント数を更新 */
            if ('approved' == $comment['status'] && 'approved' != $status) {
                $this->db->query($this->db->update('table.contents')
                    ->expression('commentsNum', 'commentsNum - 1')
                    ->where('cid = ? AND commentsNum > 0', $comment['cid']));
            } elseif ('approved' != $comment['status'] && 'approved' == $status) {
                $this->db->query($this->db->update('table.contents')
                    ->expression('commentsNum', 'commentsNum + 1')->where('cid = ?', $comment['cid']));
            }

            return true;
        }

        return false;
    }

    /**
     * ゴミ
     *
     * @throws Exception
     */
    public function spamComment()
    {
        $comments = $this->request->filter('int')->getArray('coid');
        $updateRows = 0;

        foreach ($comments as $comment) {
            if ($this->mark($comment, 'spam')) {
                $updateRows++;
            }
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $updateRows > 0 ? _t('评论已经被ゴミ') : _t('没有评论被ゴミ'),
                $updateRows > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * タグ別アーカイブ
     *
     * @throws Exception
     */
    public function approvedComment()
    {
        $comments = $this->request->filter('int')->getArray('coid');
        $updateRows = 0;

        foreach ($comments as $comment) {
            if ($this->mark($comment, 'approved')) {
                $updateRows++;
            }
        }

        /** アラートメッセージの設定 */
        Notice::alloc()
            ->set(
                $updateRows > 0 ? _t('コメントは承認されました') : _t('コメントは承認されなかった'),
                $updateRows > 0 ? 'success' : 'notice'
            );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * コメント削除
     *
     * @throws Exception
     */
    public function deleteComment()
    {
        $comments = $this->request->filter('int')->getArray('coid');
        $deleteRows = 0;

        foreach ($comments as $coid) {
            $comment = $this->db->fetchRow($this->select()
                ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

            if ($comment && $this->commentIsWriteable()) {
                self::pluginHandle()->delete($comment, $this);

                /** コメント削除 */
                $this->db->query($this->db->delete('table.comments')->where('coid = ?', $coid));

                /** 関連コンテンツのコメント数を更新 */
                if ('approved' == $comment['status']) {
                    $this->db->query($this->db->update('table.contents')
                        ->expression('commentsNum', 'commentsNum - 1')->where('cid = ?', $comment['cid']));
                }

                self::pluginHandle()->finishDelete($comment, $this);

                $deleteRows++;
            }
        }

        if ($this->request->isAjax()) {
            if ($deleteRows > 0) {
                $this->response->throwJson([
                    'success' => 1,
                    'message' => _t('コメント削除成功')
                ]);
            } else {
                $this->response->throwJson([
                    'success' => 0,
                    'message' => _t('コメント削除失败')
                ]);
            }
        } else {
            /** アラートメッセージの設定 */
            Notice::alloc()
                ->set(
                    $deleteRows > 0 ? _t('コメントは削除されました') : _t('削除されたコメントはありません'),
                    $deleteRows > 0 ? 'success' : 'notice'
                );

            /** 元のページに戻る */
            $this->response->goBack();
        }
    }

    /**
     * すべてのスパムコメントを削除する
     *
     * @throws Exception
     */
    public function deleteSpamComment()
    {
        $deleteQuery = $this->db->delete('table.comments')->where('status = ?', 'spam');
        if (!$this->request->__typecho_all_comments || !$this->user->pass('editor', true)) {
            $deleteQuery->where('ownerId = ?', $this->user->uid);
        }

        if (isset($this->request->cid)) {
            $deleteQuery->where('cid = ?', $this->request->cid);
        }

        $deleteRows = $this->db->query($deleteQuery);

        /** アラートメッセージの設定 */
        Notice::alloc()->set(
            $deleteRows > 0 ? _t('所有垃圾コメントは削除されました') : _t('スパムコメントは削除されませんでした'),
            $deleteRows > 0 ? 'success' : 'notice'
        );

        /** 元のページに戻る */
        $this->response->goBack();
    }

    /**
     * 編集可能なコメントを取得する
     *
     * @throws Exception
     */
    public function getComment()
    {
        $coid = $this->request->filter('int')->coid;
        $comment = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($comment && $this->commentIsWriteable()) {
            $this->response->throwJson([
                'success' => 1,
                'comment' => $comment
            ]);
        } else {
            $this->response->throwJson([
                'success' => 0,
                'message' => _t('コメント取得に失敗')
            ]);
        }
    }

    /**
     * 編集部コメント
     *
     * @return bool
     * @throws Exception
     */
    public function editComment(): bool
    {
        $coid = $this->request->filter('int')->coid;
        $commentSelect = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($commentSelect && $this->commentIsWriteable()) {
            $comment['text'] = $this->request->text;
            $comment['author'] = $this->request->filter('strip_tags', 'trim', 'xss')->author;
            $comment['mail'] = $this->request->filter('strip_tags', 'trim', 'xss')->mail;
            $comment['url'] = $this->request->filter('url')->url;

            if ($this->request->is('created')) {
                $comment['created'] = $this->request->filter('int')->created;
            }

            /** コメントプラグインのインターフェース */
            $comment = self::pluginHandle()->edit($comment, $this);

            /** コメント更新 */
            $this->update($comment, $this->db->sql()->where('coid = ?', $coid));

            $updatedComment = $this->db->fetchRow($this->select()
                ->where('coid = ?', $coid)->limit(1), [$this, 'push']);
            $updatedComment['content'] = $this->content;

            /** コメントプラグインのインターフェース */
            self::pluginHandle()->finishEdit($this);

            $this->response->throwJson([
                'success' => 1,
                'comment' => $updatedComment
            ]);
        }

        $this->response->throwJson([
            'success' => 0,
            'message' => _t('コメント修正の失敗')
        ]);
    }

    /**
     * コメントへの対応
     *
     * @throws Exception
     */
    public function replyComment()
    {
        $coid = $this->request->filter('int')->coid;
        $commentSelect = $this->db->fetchRow($this->select()
            ->where('coid = ?', $coid)->limit(1), [$this, 'push']);

        if ($commentSelect && $this->commentIsWriteable()) {
            $comment = [
                'cid'      => $commentSelect['cid'],
                'created'  => $this->options->time,
                'agent'    => $this->request->getAgent(),
                'ip'       => $this->request->getIp(),
                'ownerId'  => $commentSelect['ownerId'],
                'authorId' => $this->user->uid,
                'type'     => 'comment',
                'author'   => $this->user->screenName,
                'mail'     => $this->user->mail,
                'url'      => $this->user->url,
                'parent'   => $coid,
                'text'     => $this->request->text,
                'status'   => 'approved'
            ];

            /** コメントプラグインのインターフェース */
            self::pluginHandle()->comment($comment, $this);

            /** コメントへの対応 */
            $commentId = $this->insert($comment);

            $insertComment = $this->db->fetchRow($this->select()
                ->where('coid = ?', $commentId)->limit(1), [$this, 'push']);
            $insertComment['content'] = $this->content;

            /** コメント記入インターフェース */
            self::pluginHandle()->finishComment($this);

            $this->response->throwJson([
                'success' => 1,
                'comment' => $insertComment
            ]);
        }

        $this->response->throwJson([
            'success' => 0,
            'message' => _t('コメントへの対応失败')
        ]);
    }

    /**
     * 初期化関数
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->user->pass('contributor');
        $this->security->protect();
        $this->on($this->request->is('do=waiting'))->waitingComment();
        $this->on($this->request->is('do=spam'))->spamComment();
        $this->on($this->request->is('do=approved'))->approvedComment();
        $this->on($this->request->is('do=delete'))->deleteComment();
        $this->on($this->request->is('do=delete-spam'))->deleteSpamComment();
        $this->on($this->request->is('do=get&coid'))->getComment();
        $this->on($this->request->is('do=edit&coid'))->editComment();
        $this->on($this->request->is('do=reply&coid'))->replyComment();

        $this->response->redirect($this->options->adminUrl);
    }
}
