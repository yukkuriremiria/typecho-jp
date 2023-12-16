<?php

namespace Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * グローバル統計コンポーネント
 *
 * @property-read int $publishedPostsNum
 * @property-read int $waitingPostsNum
 * @property-read int $draftPostsNum
 * @property-read int $myPublishedPostsNum
 * @property-read int $myWaitingPostsNum
 * @property-read int $myDraftPostsNum
 * @property-read int $currentPublishedPostsNum
 * @property-read int $currentWaitingPostsNum
 * @property-read int $currentDraftPostsNum
 * @property-read int $publishedPagesNum
 * @property-read int $draftPagesNum
 * @property-read int $publishedCommentsNum
 * @property-read int $waitingCommentsNum
 * @property-read int $spamCommentsNum
 * @property-read int $myPublishedCommentsNum
 * @property-read int $myWaitingCommentsNum
 * @property-read int $mySpamCommentsNum
 * @property-read int $currentCommentsNum
 * @property-read int $currentPublishedCommentsNum
 * @property-read int $currentWaitingCommentsNum
 * @property-read int $currentSpamCommentsNum
 * @property-read int $categoriesNum
 * @property-read int $tagsNum
 */
class Stat extends Base
{
    /**
     * @param int $components
     */
    protected function initComponents(int &$components)
    {
        $components = self::INIT_USER;
    }

    /**
     * 掲載記事数の取得
     *
     * @return integer
     */
    protected function ___publishedPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish'))->num;
    }

    /**
     * レビューする記事の数を取得する
     *
     * @return integer
     */
    protected function ___waitingPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'post_draft')
            ->where('table.contents.status = ?', 'waiting'))->num;
    }

    /**
     * 入手した記事草稿の数
     *
     * @return integer
     */
    protected function ___draftPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post_draft'))->num;
    }

    /**
     * 現在のユーザーが公開した投稿数を取得する
     *
     * @return integer
     */
    protected function ___myPublishedPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.authorId = ?', $this->user->uid))->num;
    }

    /**
     * 現在のユーザーがレビューする記事の数を取得する
     *
     * @return integer
     */
    protected function ___myWaitingPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'post_draft')
            ->where('table.contents.status = ?', 'waiting')
            ->where('table.contents.authorId = ?', $this->user->uid))->num;
    }

    /**
     * 現在のユーザーによる下書き投稿の数を取得する
     *
     * @return integer
     */
    protected function ___myDraftPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post_draft')
            ->where('table.contents.authorId = ?', $this->user->uid))->num;
    }

    /**
     * 現在のユーザーが公開した投稿数を取得する
     *
     * @return integer
     */
    protected function ___currentPublishedPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.authorId = ?', $this->request->filter('int')->uid))->num;
    }

    /**
     * 現在のユーザーがレビューする記事の数を取得する
     *
     * @return integer
     */
    protected function ___currentWaitingPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'post_draft')
            ->where('table.contents.status = ?', 'waiting')
            ->where('table.contents.authorId = ?', $this->request->filter('int')->uid))->num;
    }

    /**
     * 現在のユーザーによる下書き投稿の数を取得する
     *
     * @return integer
     */
    protected function ___currentDraftPostsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post_draft')
            ->where('table.contents.authorId = ?', $this->request->filter('int')->uid))->num;
    }

    /**
     * 公開ページ数の取得
     *
     * @return integer
     */
    protected function ___publishedPagesNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'page')
            ->where('table.contents.status = ?', 'publish'))->num;
    }

    /**
     * 下書きページ数の取得
     *
     * @return integer
     */
    protected function ___draftPagesNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'page_draft'))->num;
    }

    /**
     * 現在表示されているコメントの数を取得する
     *
     * @return integer
     */
    protected function ___publishedCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'approved'))->num;
    }

    /**
     * 現在モデレーション待ちのコメント数を取得する
     *
     * @return integer
     */
    protected function ___waitingCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'waiting'))->num;
    }

    /**
     * 現在のスパムコメント数を取得する
     *
     * @return integer
     */
    protected function ___spamCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'spam'))->num;
    }

    /**
     * 現在のユーザーが表示したコメントの数を取得する
     *
     * @return integer
     */
    protected function ___myPublishedCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'approved')
            ->where('table.comments.ownerId = ?', $this->user->uid))->num;
    }

    /**
     * 現在のユーザーがモデレーションを保留しているコメントの数を取得する
     *
     * @return integer
     */
    protected function ___myWaitingCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'waiting')
            ->where('table.comments.ownerId = ?', $this->user->uid))->num;
    }

    /**
     * 現在のユーザーからのスパムコメント数を取得する
     *
     * @return integer
     */
    protected function ___mySpamCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'spam')
            ->where('table.comments.ownerId = ?', $this->user->uid))->num;
    }

    /**
     * 現在の記事のコメント数を取得する
     *
     * @return integer
     */
    protected function ___currentCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.cid = ?', $this->request->filter('int')->cid))->num;
    }

    /**
     * 現在の投稿に表示されているコメントの数を取得する
     *
     * @return integer
     */
    protected function ___currentPublishedCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'approved')
            ->where('table.comments.cid = ?', $this->request->filter('int')->cid))->num;
    }

    /**
     * 現在の投稿で、モデレーションを保留しているコメントの数を取得する
     *
     * @return integer
     */
    protected function ___currentWaitingCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'waiting')
            ->where('table.comments.cid = ?', $this->request->filter('int')->cid))->num;
    }

    /**
     * 現在の記事に対するスパムコメントの数を取得する
     *
     * @return integer
     */
    protected function ___currentSpamCommentsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where('table.comments.status = ?', 'spam')
            ->where('table.comments.cid = ?', $this->request->filter('int')->cid))->num;
    }

    /**
     * 獲得分類数
     *
     * @return integer
     */
    protected function ___categoriesNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(mid)' => 'num'])
            ->from('table.metas')
            ->where('table.metas.type = ?', 'category'))->num;
    }

    /**
     * タグの数を取得する
     *
     * @return integer
     */
    protected function ___tagsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(mid)' => 'num'])
            ->from('table.metas')
            ->where('table.metas.type = ?', 'tag'))->num;
    }
}
