<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Date;
use Typecho\Db;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Router;
use Utils\AutoP;
use Utils\Markdown;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コメント基本クラス
 *
 * @property int $coid
 * @property int $cid
 * @property int $created
 * @property string author
 * @property int $authorId
 * @property int $ownerId
 * @property string $mail
 * @property string $url
 * @property string $ip
 * @property string $agent
 * @property string $text
 * @property string $type
 * @property string status
 * @property int $parent
 * @property Date $date
 * @property string $dateWord
 * @property string $theId
 * @property array $parentContent
 * @property string $title
 * @property string $permalink
 * @property string $content
 */
class Comments extends Base implements QueryInterface
{
    /**
     * コメントを追加する
     *
     * @param array $rows コメント構造体の配列
     * @return integer
     * @throws Exception
     */
    public function insert(array $rows): int
    {
        /** 建築物の挿入構造 */
        $insertStruct = [
            'cid'      => $rows['cid'],
            'created'  => empty($rows['created']) ? $this->options->time : $rows['created'],
            'author'   => !isset($rows['author']) || strlen($rows['author']) === 0 ? null : $rows['author'],
            'authorId' => empty($rows['authorId']) ? 0 : $rows['authorId'],
            'ownerId'  => empty($rows['ownerId']) ? 0 : $rows['ownerId'],
            'mail'     => !isset($rows['mail']) || strlen($rows['mail']) === 0 ? null : $rows['mail'],
            'url'      => !isset($rows['url']) || strlen($rows['url']) === 0 ? null : $rows['url'],
            'ip'       => !isset($rows['ip']) || strlen($rows['ip']) === 0 ? $this->request->getIp() : $rows['ip'],
            'agent'    => !isset($rows['agent']) || strlen($rows['agent']) === 0
                ? $this->request->getAgent() : $rows['agent'],
            'text'     => !isset($rows['text']) || strlen($rows['text']) === 0 ? null : $rows['text'],
            'type'     => empty($rows['type']) ? 'comment' : $rows['type'],
            'status'   => empty($rows['status']) ? 'approved' : $rows['status'],
            'parent'   => empty($rows['parent']) ? 0 : $rows['parent'],
        ];

        if (!empty($rows['coid'])) {
            $insertStruct['coid'] = $rows['coid'];
        }

        /** クライアント側で過度に長い文字列は切り捨てられるべきである。 */
        if (Common::strLen($insertStruct['agent']) > 511) {
            $insertStruct['agent'] = Common::subStr($insertStruct['agent'], 0, 511, '');
        }

        /** 最初にデータを挿入する */
        $insertId = $this->db->query($this->db->insert('table.comments')->rows($insertStruct));

        /** コメント更新数 */
        $num = $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])->from('table.comments')
            ->where('status = ? AND cid = ?', 'approved', $rows['cid']))->num;

        $this->db->query($this->db->update('table.contents')->rows(['commentsNum' => $num])
            ->where('cid = ?', $rows['cid']));

        return $insertId;
    }

    /**
     * コメント更新
     *
     * @param array $rows コメント構造体の配列
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function update(array $rows, Query $condition): int
    {
        /** コンテンツの主キーを取得 */
        $updateCondition = clone $condition;
        $updateComment = $this->db->fetchObject($condition->select('cid')->from('table.comments')->limit(1));

        if ($updateComment) {
            $cid = $updateComment->cid;
        } else {
            return 0;
        }

        /** 建築物の挿入構造 */
        $preUpdateStruct = [
            'author' => !isset($rows['author']) || strlen($rows['author']) === 0 ? null : $rows['author'],
            'mail'   => !isset($rows['mail']) || strlen($rows['mail']) === 0 ? null : $rows['mail'],
            'url'    => !isset($rows['url']) || strlen($rows['url']) === 0 ? null : $rows['url'],
            'text'   => !isset($rows['text']) || strlen($rows['text']) === 0 ? null : $rows['text'],
            'status' => empty($rows['status']) ? 'approved' : $rows['status'],
        ];

        $updateStruct = [];
        foreach ($rows as $key => $val) {
            if ((array_key_exists($key, $preUpdateStruct))) {
                $updateStruct[$key] = $preUpdateStruct[$key];
            }
        }

        /** 更新作成時間 */
        if (!empty($rows['created'])) {
            $updateStruct['created'] = $rows['created'];
        }

        /** コメント更新数据 */
        $updateRows = $this->db->query($updateCondition->update('table.comments')->rows($updateStruct));

        /** コメント更新数 */
        $num = $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])->from('table.comments')
            ->where('status = ? AND cid = ?', 'approved', $cid))->num;

        $this->db->query($this->db->update('table.contents')->rows(['commentsNum' => $num])
            ->where('cid = ?', $cid));

        return $updateRows;
    }

    /**
     * データ削除
     *
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function delete(Query $condition): int
    {
        /** コンテンツの主キーを取得 */
        $deleteCondition = clone $condition;
        $deleteComment = $this->db->fetchObject($condition->select('cid')->from('table.comments')->limit(1));

        if ($deleteComment) {
            $cid = $deleteComment->cid;
        } else {
            return 0;
        }

        /** コメントデータの削除 */
        $deleteRows = $this->db->query($deleteCondition->delete('table.comments'));

        /** コメント更新数 */
        $num = $this->db->fetchObject($this->db->select(['COUNT(coid)' => 'num'])->from('table.comments')
            ->where('status = ? AND cid = ?', 'approved', $cid))->num;

        $this->db->query($this->db->update('table.contents')->rows(['commentsNum' => $num])
            ->where('cid = ?', $cid));

        return $deleteRows;
    }

    /**
     * コメントをモデレートできるかどうか
     *
     * @param Query|null $condition 前提条件
     * @return bool
     * @throws Exception
     */
    public function commentIsWriteable(?Query $condition = null): bool
    {
        if (empty($condition)) {
            if ($this->have() && ($this->user->pass('editor', true) || $this->ownerId == $this->user->uid)) {
                return true;
            }
        } else {
            $post = $this->db->fetchRow($condition->select('ownerId')->from('table.comments')->limit(1));

            if ($post && ($this->user->pass('editor', true) || $post['ownerId'] == $this->user->uid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 按照前提条件计算评论数量
     *
     * @param Query $condition クエリ件名
     * @return integer
     * @throws Exception
     */
    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(coid)' => 'num'])->from('table.comments'))->num;
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
        $value['author'] = $value['author'] ?? '';
        $value['mail'] = $value['mail'] ?? '';
        $value['url'] = $value['url'] ?? '';
        $value['ip'] = $value['ip'] ?? '';
        $value['agent'] = $value['agent'] ?? '';
        $value['text'] = $value['text'] ?? '';

        $value['date'] = new Date($value['created']);
        return Comments::pluginHandle()->filter($value, $this);
    }

    /**
     * 記事が掲載された日付を出力
     *
     * @param string|null $format 日付形式
     */
    public function date(?string $format = null)
    {
        echo $this->date->format(empty($format) ? $this->options->commentDateFormat : $format);
    }

    /**
     * 出力著者関連
     *
     * @param boolean|null $autoLink リンクを自動的に追加するかどうか
     * @param boolean|null $noFollow が加わるのか？nofollowタブ
     */
    public function author(?bool $autoLink = null, ?bool $noFollow = null)
    {
        $autoLink = (null === $autoLink) ? $this->options->commentsShowUrl : $autoLink;
        $noFollow = (null === $noFollow) ? $this->options->commentsUrlNofollow : $noFollow;

        if ($this->url && $autoLink) {
            echo '<a href="' . Common::safeUrl($this->url) . '"'
                . ($noFollow ? ' rel="external nofollow"' : null) . '>' . $this->author . '</a>';
        } else {
            echo $this->author;
        }
    }

    /**
     * 各論gravatarユーザーアバターのエクスポート
     *
     * @param integer $size アバターサイズ
     * @param string|null $default デフォルトの出力アバター
     */
    public function gravatar(int $size = 32, ?string $default = null)
    {
        if ($this->options->commentsAvatar && 'comment' == $this->type) {
            $rating = $this->options->commentsAvatarRating;

            Comments::pluginHandle()->trigger($plugged)->gravatar($size, $rating, $default, $this);
            if (!$plugged) {
                $url = Common::gravatarUrl($this->mail, $size, $rating, $default, $this->request->isSecure());
                echo '<img class="avatar" loading="lazy" src="' . $url . '" alt="' .
                    $this->author . '" width="' . $size . '" height="' . $size . '" />';
            }
        }
    }

    /**
     * 出力コメントの要約
     *
     * @param integer $length 概要 インターセプトの長さ
     * @param string $trim 抽象的接尾辞
     */
    public function excerpt(int $length = 100, string $trim = '...')
    {
        echo Common::subStr(strip_tags($this->content), 0, $length, $trim);
    }

    /**
     * 出力メールアドレス
     *
     * @param bool $link
     * @return void
     */
    public function mail(bool $link = false)
    {
        $mail = htmlspecialchars($this->mail);
        echo $link ? 'mailto:' . $mail : $mail;
    }

    /**
     * 获取クエリ件名
     *
     * @return Query
     * @throws Exception
     */
    public function select(): Query
    {
        return $this->db->select(
            'table.comments.coid',
            'table.comments.cid',
            'table.comments.author',
            'table.comments.mail',
            'table.comments.url',
            'table.comments.ip',
            'table.comments.authorId',
            'table.comments.ownerId',
            'table.comments.agent',
            'table.comments.text',
            'table.comments.type',
            'table.comments.status',
            'table.comments.parent',
            'table.comments.created'
        )
            ->from('table.comments');
    }

    /**
     * markdown
     *
     * @param string|null $text
     * @return string|null
     */
    public function markdown(?string $text): ?string
    {
        $html = Comments::pluginHandle()->trigger($parsed)->markdown($text);

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
        $html = Comments::pluginHandle()->trigger($parsed)->autoP($text);

        if (!$parsed) {
            static $parser;

            if (empty($parser)) {
                $parser = new AutoP();
            }

            $html = $parser->parse($text);
        }

        return $html;
    }

    /**
     * 現在のコンテンツ構造を取得する
     *
     * @return array|null
     * @throws Exception
     */
    protected function ___parentContent(): ?array
    {
        return $this->db->fetchRow(Contents::alloc()->select()
            ->where('table.contents.cid = ?', $this->cid)
            ->limit(1), [Contents::alloc(), 'filter']);
    }

    /**
     * 現在のコメントタイトルを取得
     *
     * @return string|null
     */
    protected function ___title(): ?string
    {
        return $this->parentContent['title'];
    }

    /**
     * 現在のコメントへのリンクを取得
     *
     * @return string
     * @throws Exception
     */
    protected function ___permalink(): string
    {

        if ($this->options->commentsPageBreak && 'approved' == $this->status) {
            $coid = $this->coid;
            $parent = $this->parent;

            while ($parent > 0 && $this->options->commentsThreaded) {
                $parentRows = $this->db->fetchRow($this->db->select('parent')->from('table.comments')
                    ->where('coid = ? AND status = ?', $parent, 'approved')->limit(1));

                if (!empty($parentRows)) {
                    $coid = $parent;
                    $parent = $parentRows['parent'];
                } else {
                    break;
                }
            }

            $select = $this->db->select('coid', 'parent')
                ->from('table.comments')->where('cid = ? AND status = ?', $this->parentContent['cid'], 'approved')
                ->where('coid ' . ('DESC' == $this->options->commentsOrder ? '>=' : '<=') . ' ?', $coid)
                ->order('coid', Db::SORT_ASC);

            if ($this->options->commentsShowCommentOnly) {
                $select->where('type = ?', 'comment');
            }

            $comments = $this->db->fetchAll($select);

            $commentsMap = [];
            $total = 0;

            foreach ($comments as $comment) {
                $commentsMap[$comment['coid']] = $comment['parent'];

                if (0 == $comment['parent'] || !isset($commentsMap[$comment['parent']])) {
                    $total++;
                }
            }

            $currentPage = ceil($total / $this->options->commentsPageSize);

            $pageRow = ['permalink' => $this->parentContent['pathinfo'], 'commentPage' => $currentPage];
            return Router::url(
                'comment_page',
                $pageRow,
                $this->options->index
            ) . '#' . $this->theId;
        }

        return $this->parentContent['permalink'] . '#' . $this->theId;
    }

    /**
     * 現在のコメント内容を取得する
     *
     * @return string|null
     */
    protected function ___content(): ?string
    {
        $text = $this->parentContent['hidden'] ? _t('コンテンツが隠されている') : $this->text;

        $text = Comments::pluginHandle()->trigger($plugged)->content($text, $this);
        if (!$plugged) {
            $text = $this->options->commentsMarkdown ? $this->markdown($text)
                : $this->autoP($text);
        }

        $text = Comments::pluginHandle()->contentEx($text, $this);
        return Common::stripTags($text, '<p><br>' . $this->options->commentsHTMLTagAllowed);
    }

    /**
     * 辞書化された日付を出力
     *
     * @return string
     */
    protected function ___dateWord(): string
    {
        return $this->date->word();
    }

    /**
     * アンカーポイントid
     *
     * @return string
     */
    protected function ___theId(): string
    {
        return $this->type . '-' . $this->coid;
    }
}
