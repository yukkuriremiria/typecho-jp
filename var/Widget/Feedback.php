<?php

namespace Widget;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Router;
use Typecho\Validate;
use Typecho\Widget\Exception;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * フィードバック提出コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Feedback extends Comments implements ActionInterface
{
    /**
     * コンテンツ・オブジェクト
     *
     * @access private
     * @var Archive
     */
    private $content;

    /**
     * 登録ユーザーの保護テスト
     *
     * @param string $userName 利用者ID
     * @return bool
     * @throws \Typecho\Db\Exception
     */
    public function requireUserLogin(string $userName): bool
    {
        if ($this->user->hasLogin() && $this->user->screenName != $userName) {
            /** 当前利用者ID与提交者不匹配 */
            return false;
        } elseif (
            !$this->user->hasLogin() && $this->db->fetchRow($this->db->select('uid')
                ->from('table.users')->where('screenName = ? OR name = ?', $userName, $userName)->limit(1))
        ) {
            /** 此利用者ID已经被注册 */
            return false;
        }

        return true;
    }

    /**
     * 初期化関数
     *
     * @throws \Exception
     */
    public function action()
    {
        /** コールバックメソッド */
        $callback = $this->request->type;
        $this->content = Router::match($this->request->permalink);

        /** コンテンツが存在するかどうかを判断する */
        if (
            $this->content instanceof Archive &&
            $this->content->have() && $this->content->is('single') &&
            in_array($callback, ['comment', 'trackback'])
        ) {

            /** 記事がフィードバックを許さない場合 */
            if ('comment' == $callback) {
                /** コメントを閉じる */
                if (!$this->content->allow('comment')) {
                    throw new Exception(_t('すみません,このコンテンツへのフィードバックは無効です.'), 403);
                }

                /** ソースの検査 */
                if ($this->options->commentsCheckReferer && 'false' != $this->parameter->checkReferer) {
                    $referer = $this->request->getReferer();

                    if (empty($referer)) {
                        throw new Exception(_t('コメントソースページのエラー.'), 403);
                    }

                    $refererPart = parse_url($referer);
                    $currentPart = parse_url($this->content->permalink);

                    if (
                        $refererPart['host'] != $currentPart['host'] ||
                        0 !== strpos($refererPart['path'], $currentPart['path'])
                    ) {
                        //カスタム・ホームページ・サポート
                        if ('page:' . $this->content->cid == $this->options->frontPage) {
                            $currentPart = parse_url(rtrim($this->options->siteUrl, '/') . '/');

                            if (
                                $refererPart['host'] != $currentPart['host'] ||
                                0 !== strpos($refererPart['path'], $currentPart['path'])
                            ) {
                                throw new Exception(_t('コメントソースページのエラー.'), 403);
                            }
                        } else {
                            throw new Exception(_t('コメントソースページのエラー.'), 403);
                        }
                    }
                }

                /** プローブipコメント間隔 */
                if (
                    !$this->user->pass('editor', true) && $this->content->authorId != $this->user->uid &&
                    $this->options->commentsPostIntervalEnable
                ) {
                    $latestComment = $this->db->fetchRow($this->db->select('created')->from('table.comments')
                        ->where('cid = ? AND ip = ?', $this->content->cid, $this->request->getIp())
                        ->order('created', Db::SORT_DESC)
                        ->limit(1));

                    if (
                        $latestComment && ($this->options->time - $latestComment['created'] > 0 &&
                            $this->options->time - $latestComment['created'] < $this->options->commentsPostInterval)
                    ) {
                        throw new Exception(_t('すみません, あなたはよくしゃべる, 後でまた投稿してください。.'), 403);
                    }
                }
            }

            /** 記事が引用を許可していない場合 */
            if ('trackback' == $callback && !$this->content->allow('ping')) {
                throw new Exception(_t('すみません,このコンテンツへの言及は禁止されています。.'), 403);
            }

            /** コール機能 */
            $this->$callback();
        } else {
            throw new Exception(_t('コンテンツが見つからない'), 404);
        }
    }

    /**
     * コメント処理機能
     *
     * @throws \Exception
     */
    private function comment()
    {
        // セキュリティモジュールで保護
        $this->security->enable($this->options->commentsAntiSpam);
        $this->security->protect();

        $comment = [
            'cid' => $this->content->cid,
            'created' => $this->options->time,
            'agent' => $this->request->getAgent(),
            'ip' => $this->request->getIp(),
            'ownerId' => $this->content->author->uid,
            'type' => 'comment',
            'status' => !$this->content->allow('edit')
                && $this->options->commentsRequireModeration ? 'waiting' : 'approved'
        ];

        /** 親ノードを決定する */
        if ($parentId = $this->request->filter('int')->get('parent')) {
            if (
                $this->options->commentsThreaded
                && ($parent = $this->db->fetchRow($this->db->select('coid', 'cid')->from('table.comments')
                    ->where('coid = ?', $parentId))) && $this->content->cid == $parent['cid']
            ) {
                $comment['parent'] = $parentId;
            } else {
                throw new Exception(_t('親のコメントが存在しない'));
            }
        }

        //試験形式
        $validator = new Validate();
        $validator->addRule('author', 'required', _t('必须填写利用者ID'));
        $validator->addRule('author', 'xssCheck', _t('请不要在利用者ID中利用する特殊字符'));
        $validator->addRule('author', [$this, 'requireUserLogin'], _t('您所利用するな利用者ID已经被注册,ログインして再度送信してください。'));
        $validator->addRule('author', 'maxLength', _t('利用者ID最多包含150文字'), 150);

        if ($this->options->commentsRequireMail && !$this->user->hasLogin()) {
            $validator->addRule('mail', 'required', _t('メールアドレスは必須'));
        }

        $validator->addRule('mail', 'email', _t('不正な電子メールアドレス'));
        $validator->addRule('mail', 'maxLength', _t('メールアドレスには150文字'), 150);

        if ($this->options->commentsRequireUrl && !$this->user->hasLogin()) {
            $validator->addRule('url', 'required', _t('個人ホームページの記入'));
        }
        $validator->addRule('url', 'url', _t('個人ホームページアドレスの書式誤り'));
        $validator->addRule('url', 'maxLength', _t('個人ホームページのアドレスには255文字'), 255);

        $validator->addRule('text', 'required', _t('コメントは必ず記入すること'));

        $comment['text'] = $this->request->text;

        /** 一般の匿名訪問者,ユーザーデータを1ヶ月間保存 */
        if (!$this->user->hasLogin()) {
            /** Anti-XSS */
            $comment['author'] = $this->request->filter('trim')->author;
            $comment['mail'] = $this->request->filter('trim')->mail;
            $comment['url'] = $this->request->filter('trim', 'url')->url;

            /** ユーザー投稿の修正url */
            if (!empty($comment['url'])) {
                $urlParams = parse_url($comment['url']);
                if (!isset($urlParams['scheme'])) {
                    $comment['url'] = 'http://' . $comment['url'];
                }
            }

            $expire = 30 * 24 * 3600;
            Cookie::set('__typecho_remember_author', $comment['author'], $expire);
            Cookie::set('__typecho_remember_mail', $comment['mail'], $expire);
            Cookie::set('__typecho_remember_url', $comment['url'], $expire);
        } else {
            $comment['author'] = $this->user->screenName;
            $comment['mail'] = $this->user->mail;
            $comment['url'] = $this->user->url;

            /** ログインユーザーのid */
            $comment['authorId'] = $this->user->uid;
        }

        /** レビュアーは過去にレビューが承認されたことがあること */
        if (!$this->options->commentsRequireModeration && $this->options->commentsWhitelist) {
            if (
                $this->size(
                    $this->select()->where(
                        'author = ? AND mail = ? AND status = ?',
                        $comment['author'],
                        $comment['mail'],
                        'approved'
                    )
                )
            ) {
                $comment['status'] = 'approved';
            } else {
                $comment['status'] = 'waiting';
            }
        }

        if ($error = $validator->run($comment)) {
            /** ねんしょ */
            Cookie::set('__typecho_remember_text', $comment['text']);
            throw new Exception(implode("\n", $error));
        }

        /** フィルター生成 */
        try {
            $comment = self::pluginHandle()->comment($comment, $this->content);
        } catch (\Typecho\Exception $e) {
            Cookie::set('__typecho_remember_text', $comment['text']);
            throw $e;
        }

        /** コメントを追加する */
        $commentId = $this->insert($comment);
        Cookie::delete('__typecho_remember_text');
        $this->db->fetchRow($this->select()->where('coid = ?', $commentId)
            ->limit(1), [$this, 'push']);

        /** コメント記入インターフェース */
        self::pluginHandle()->finishComment($this);

        $this->response->goBack('#' . $this->theId);
    }

    /**
     * リファレンス・ハンドリング機能
     *
     * @throws Exception|\Typecho\Db\Exception
     */
    private function trackback()
    {
        /** そうでなければPOST方法論 */
        if (!$this->request->isPost() || $this->request->getReferer()) {
            $this->response->redirect($this->content->permalink);
        }

        /** 現在のipというのもspamなtrackbackそして、それは完全に拒否される */
        if (
            $this->size($this->select()
                ->where('status = ? AND ip = ?', 'spam', $this->request->getIp())) > 0
        ) {
            /** 利用する404ロボットに伝えてくれ。 */
            throw new Exception(_t('コンテンツが見つからない'), 404);
        }

        $trackback = [
            'cid' => $this->content->cid,
            'created' => $this->options->time,
            'agent' => $this->request->getAgent(),
            'ip' => $this->request->getIp(),
            'ownerId' => $this->content->author->uid,
            'type' => 'trackback',
            'status' => $this->options->commentsRequireModeration ? 'waiting' : 'approved'
        ];

        $trackback['author'] = $this->request->filter('trim')->blog_name;
        $trackback['url'] = $this->request->filter('trim', 'url')->url;
        $trackback['text'] = $this->request->excerpt;

        //試験形式
        $validator = new Validate();
        $validator->addRule('url', 'required', 'We require all Trackbacks to provide an url.')
            ->addRule('url', 'url', 'Your url is not valid.')
            ->addRule('url', 'maxLength', 'Your url is not valid.', 255)
            ->addRule('text', 'required', 'We require all Trackbacks to provide an excerption.')
            ->addRule('author', 'required', 'We require all Trackbacks to provide an blog name.')
            ->addRule('author', 'xssCheck', 'Your blog name is not valid.')
            ->addRule('author', 'maxLength', 'Your blog name is not valid.', 150);

        $validator->setBreak();
        if ($error = $validator->run($trackback)) {
            $message = ['success' => 1, 'message' => current($error)];
            $this->response->throwXml($message);
        }

        /** インターセプトの長さ */
        $trackback['text'] = Common::subStr($trackback['text'], 0, 100, '[...]');

        /** ライブラリにすでに重複が存在する場合urlそして、それは完全に拒否される */
        if (
            $this->size($this->select()
                ->where('cid = ? AND url = ? AND type <> ?', $this->content->cid, $trackback['url'], 'comment')) > 0
        ) {
            /** 利用する403ロボットに伝えてくれ。 */
            throw new Exception(_t('重複提出の禁止'), 403);
        }

        /** フィルター生成 */
        $trackback = self::pluginHandle()->trackback($trackback, $this->content);

        /** 参考文献の追加 */
        $this->insert($trackback);

        /** コメント記入インターフェース */
        self::pluginHandle()->finishTrackback($this);

        /** 正しく戻る */
        $this->response->throwXml(['success' => 0, 'message' => 'Trackback has registered.']);
    }
}
