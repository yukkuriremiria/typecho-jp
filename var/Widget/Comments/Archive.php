<?php

namespace Widget\Comments;

use Typecho\Config;
use Typecho\Cookie;
use Typecho\Router;
use Typecho\Widget\Helper\PageNavigator\Box;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コメントアーカイブコンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Archive extends Comments
{
    /**
     * 現在のページ
     *
     * @access private
     * @var integer
     */
    private $currentPage;

    /**
     * 全記事数
     *
     * @access private
     * @var integer
     */
    private $total = false;

    /**
     * 息子と父親の解説関係
     *
     * @access private
     * @var array
     */
    private $threadedComments = [];

    /**
     * _singleCommentOptions
     *
     * @var mixed
     * @access private
     */
    private $singleCommentOptions = null;

    /**
     * @param Config $parameter
     */
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('parentId=0&commentPage=0&commentsNum=0&allowComment=1');
    }

    /**
     * 記事のコメント数を出力する
     *
     * @param ...$args
     */
    public function num(...$args)
    {
        if (empty($args)) {
            $args[] = '%d';
        }

        $num = intval($this->total);

        echo sprintf($args[$num] ?? array_pop($args), $num);
    }

    /**
     * 実行可能関数
     *
     * @access public
     * @return void
     */
    public function execute()
    {
        if (!$this->parameter->parentId) {
            return;
        }

        $commentsAuthor = Cookie::get('__typecho_remember_author');
        $commentsMail = Cookie::get('__typecho_remember_mail');
        $select = $this->select()->where('table.comments.cid = ?', $this->parameter->parentId)
            ->where(
                'table.comments.status = ? OR (table.comments.author = ?'
                    . ' AND table.comments.mail = ? AND table.comments.status = ?)',
                'approved',
                $commentsAuthor,
                $commentsMail,
                'waiting'
            );
        $threadedSelect = null;

        if ($this->options->commentsShowCommentOnly) {
            $select->where('table.comments.type = ?', 'comment');
        }

        $select->order('table.comments.coid', 'ASC');
        $this->db->fetchAll($select, [$this, 'push']);

        /** エクスポートするコメントのリスト */
        $outputComments = [];

        /** コメント返信が有効になっている場合 */
        if ($this->options->commentsThreaded) {
            foreach ($this->stack as $coid => &$comment) {

                /** 親ノードを削除する */
                $parent = $comment['parent'];

                /** 親ノードが存在する場合 */
                if (0 != $parent && isset($this->stack[$parent])) {

                    /** 現在のノードの深さが最大深さより大きい場合, そしてそれを親ノードにフックする。 */
                    if ($comment['levels'] >= $this->options->commentsMaxNestingLevels) {
                        $comment['levels'] = $this->stack[$parent]['levels'];
                        $parent = $this->stack[$parent]['parent'];     // 過剰結節
                        $comment['parent'] = $parent;
                    }

                    /** 子ノードの順序を計算する */
                    $comment['order'] = isset($this->threadedComments[$parent])
                        ? count($this->threadedComments[$parent]) + 1 : 1;

                    /** 子ノードの場合 */
                    $this->threadedComments[$parent][$coid] = $comment;
                } else {
                    $outputComments[$coid] = $comment;
                }

            }

            $this->stack = $outputComments;
        }

        /** コメントの並べ替え */
        if ('DESC' == $this->options->commentsOrder) {
            $this->stack = array_reverse($this->stack, true);
            $this->threadedComments = array_map('array_reverse', $this->threadedComments);
        }

        /** コメント総数 */
        $this->total = count($this->stack);

        /** コメントのページネーション */
        if ($this->options->commentsPageBreak) {
            if ('last' == $this->options->commentsPageDisplay && !$this->parameter->commentPage) {
                $this->currentPage = ceil($this->total / $this->options->commentsPageSize);
            } else {
                $this->currentPage = $this->parameter->commentPage ? $this->parameter->commentPage : 1;
            }

            /** コメントの傍受 */
            $this->stack = array_slice(
                $this->stack,
                ($this->currentPage - 1) * $this->options->commentsPageSize,
                $this->options->commentsPageSize
            );

            /** コメント位置 */
            $this->length = count($this->stack);
            $this->row = $this->length > 0 ? current($this->stack) : [];
        }

        reset($this->stack);
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

        /** 計算深度 */
        if (0 != $value['parent'] && isset($this->stack[$value['parent']]['levels'])) {
            $value['levels'] = $this->stack[$value['parent']]['levels'] + 1;
        } else {
            $value['levels'] = 0;
        }

        /** におもpush関数,利用するcoid配列のキーとして,簡単なインデックス作成 */
        $this->stack[$value['coid']] = $value;
        $this->length ++;

        return $value;
    }

    /**
     * 出力ページング
     *
     * @access public
     * @param string $prev 前のテキスト
     * @param string $next 次の文章
     * @param int $splitPage セグメンテーションの範囲
     * @param string $splitWord 分割文字
     * @param string|array $template 設定情報を表示する
     * @return void
     * @throws \Typecho\Widget\Exception
     */
    public function pageNav(
        string $prev = '&laquo;',
        string $next = '&raquo;',
        int $splitPage = 3,
        string $splitWord = '...',
        $template = ''
    ) {
        if ($this->options->commentsPageBreak) {
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

            $pageRow = $this->parameter->parentContent;
            $pageRow['permalink'] = $pageRow['pathinfo'];
            $query = Router::url('comment_page', $pageRow, $this->options->index);

            self::pluginHandle()->trigger($hasNav)->pageNav(
                $this->currentPage,
                $this->total,
                $this->options->commentsPageSize,
                $prev,
                $next,
                $splitPage,
                $splitWord,
                $template,
                $query
            );

            if (!$hasNav && $this->total > $this->options->commentsPageSize) {
                /** 利用する盒状分页 */
                $nav = new Box($this->total, $this->currentPage, $this->options->commentsPageSize, $query);
                $nav->setPageHolder('commentPage');
                $nav->setAnchor('comments');

                echo '<' . $template['wrapTag'] . (empty($template['wrapClass'])
                        ? '' : ' class="' . $template['wrapClass'] . '"') . '>';
                $nav->render($prev, $next, $splitPage, $splitWord, $template);
                echo '</' . $template['wrapTag'] . '>';
            }
        }
    }

    /**
     * コメント一覧
     *
     * @param mixed $singleCommentOptions 個別コメントのカスタマイズオプション
     */
    public function listComments($singleCommentOptions = null)
    {
        //いくつかの変数を初期化する
        $this->singleCommentOptions = Config::factory($singleCommentOptions);
        $this->singleCommentOptions->setDefault([
            'before'        => '<ol class="comment-list">',
            'after'         => '</ol>',
            'beforeAuthor'  => '',
            'afterAuthor'   => '',
            'beforeDate'    => '',
            'afterDate'     => '',
            'dateFormat'    => $this->options->commentDateFormat,
            'replyWord'     => _t('もどす'),
            'commentStatus' => _t('あなたのコメントはモデレーション待ちです!'),
            'avatarSize'    => 32,
            'defaultAvatar' => null
        ]);
        self::pluginHandle()->trigger($plugged)->listComments($this->singleCommentOptions, $this);

        if (!$plugged) {
            if ($this->have()) {
                echo $this->singleCommentOptions->before;

                while ($this->next()) {
                    $this->threadedCommentsCallback();
                }

                echo $this->singleCommentOptions->after;
            }
        }
    }

    /**
     * 评论回调関数
     */
    private function threadedCommentsCallback()
    {
        $singleCommentOptions = $this->singleCommentOptions;
        if (function_exists('threadedComments')) {
            return threadedComments($this, $singleCommentOptions);
        }

        $commentClass = '';
        if ($this->authorId) {
            if ($this->authorId == $this->ownerId) {
                $commentClass .= ' comment-by-author';
            } else {
                $commentClass .= ' comment-by-user';
            }
        }
        ?>
        <li itemscope itemtype="http://schema.org/UserComments" id="<?php $this->theId(); ?>" class="comment-body<?php
        if ($this->levels > 0) {
            echo ' comment-child';
            $this->levelsAlt(' comment-level-odd', ' comment-level-even');
        } else {
            echo ' comment-parent';
        }
        $this->alt(' comment-odd', ' comment-even');
        echo $commentClass;
        ?>">
            <div class="comment-author" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                <span
                    itemprop="image">
                    <?php $this->gravatar($singleCommentOptions->avatarSize, $singleCommentOptions->defaultAvatar); ?>
                </span>
                <cite class="fn" itemprop="name"><?php $singleCommentOptions->beforeAuthor();
                    $this->author();
                    $singleCommentOptions->afterAuthor(); ?></cite>
            </div>
            <div class="comment-meta">
                <a href="<?php $this->permalink(); ?>">
                    <time itemprop="commentTime"
                          datetime="<?php $this->date('c'); ?>"><?php
                            $singleCommentOptions->beforeDate();
                            $this->date($singleCommentOptions->dateFormat);
                            $singleCommentOptions->afterDate();
                            ?></time>
                </a>
                <?php if ('waiting' == $this->status) { ?>
                    <em class="comment-awaiting-moderation"><?php $singleCommentOptions->commentStatus(); ?></em>
                <?php } ?>
            </div>
            <div class="comment-content" itemprop="commentText">
                <?php $this->content(); ?>
            </div>
            <div class="comment-reply">
                <?php $this->reply($singleCommentOptions->replyWord); ?>
            </div>
            <?php if ($this->children) { ?>
                <div class="comment-children" itemprop="discusses">
                    <?php $this->threadedComments(); ?>
                </div>
            <?php } ?>
        </li>
        <?php
    }

    /**
     * デプス残差に基づく出力
     *
     * @param mixed ...$args 出力される値
     */
    public function levelsAlt(...$args)
    {
        $num = count($args);
        $split = $this->levels % $num;
        echo $args[(0 == $split ? $num : $split) - 1];
    }

    /**
     * におもalt関数,複数のコメントレベルに対応
     *
     * @param ...$args
     */
    public function alt(...$args)
    {
        $num = count($args);

        $sequence = $this->levels <= 0 ? $this->sequence : $this->order;

        $split = $sequence % $num;
        echo $args[(0 == $split ? $num : $split) - 1];
    }

    /**
     * 评论もどす链接
     *
     * @param string $word もどす链接文字
     */
    public function reply(string $word = '')
    {
        if ($this->options->commentsThreaded && !$this->isTopLevel && $this->parameter->allowComment) {
            $word = empty($word) ? _t('もどす') : $word;
            self::pluginHandle()->trigger($plugged)->reply($word, $this);

            if (!$plugged) {
                echo '<a href="' . substr($this->permalink, 0, - strlen($this->theId) - 1) . '?replyTo=' . $this->coid .
                    '#' . $this->parameter->respondId . '" rel="nofollow" onclick="return TypechoComment.reply(\'' .
                    $this->theId . '\', ' . $this->coid . ');">' . $word . '</a>';
            }
        }
    }

    /**
     * コメントの再帰的出力
     */
    public function threadedComments()
    {
        $children = $this->children;
        if ($children) {
            //簡単に復元できるキャッシュ変数
            $tmp = $this->row;
            $this->sequence ++;

            //サブコメントの前の出力
            echo $this->singleCommentOptions->before;

            foreach ($children as $child) {
                $this->row = $child;
                $this->threadedCommentsCallback();
                $this->row = $tmp;
            }

            //サブコメントの後の出力
            echo $this->singleCommentOptions->after;

            $this->sequence --;
        }
    }

    /**
     * 取消评论もどす链接
     *
     * @param string $word 取消もどす链接文字
     */
    public function cancelReply(string $word = '')
    {
        if ($this->options->commentsThreaded) {
            $word = empty($word) ? _t('取消もどす') : $word;
            self::pluginHandle()->trigger($plugged)->cancelReply($word, $this);

            if (!$plugged) {
                $replyId = $this->request->filter('int')->replyTo;
                echo '<a id="cancel-comment-reply-link" href="' . $this->parameter->parentContent['permalink'] . '#' . $this->parameter->respondId .
                    '" rel="nofollow"' . ($replyId ? '' : ' style="display:none"') . ' onclick="return TypechoComment.cancelReply();">' . $word . '</a>';
            }
        }
    }

    /**
     * 現在のコメントへのリンクを取得
     *
     * @return string
     */
    protected function ___permalink(): string
    {

        if ($this->options->commentsPageBreak) {
            $pageRow = ['permalink' => $this->parentContent['pathinfo'], 'commentPage' => $this->currentPage];
            return Router::url('comment_page', $pageRow, $this->options->index) . '#' . $this->theId;
        }

        return $this->parentContent['permalink'] . '#' . $this->theId;
    }

    /**
     * サブコメント
     *
     * @return array
     */
    protected function ___children(): array
    {
        return $this->options->commentsThreaded && !$this->isTopLevel && isset($this->threadedComments[$this->coid])
            ? $this->threadedComments[$this->coid] : [];
    }

    /**
     * 最上階にたどり着けるかどうか
     *
     * @return boolean
     */
    protected function ___isTopLevel(): bool
    {
        return $this->levels > $this->options->commentsMaxNestingLevels - 2;
    }

    /**
     * におも内容获取
     *
     * @return array|null
     */
    protected function ___parentContent(): ?array
    {
        return $this->parameter->parentContent;
    }
}
