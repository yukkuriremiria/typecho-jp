<?php

namespace Widget\Options;

use Typecho\Db\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * コメント設定コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Discussion extends Options implements ActionInterface
{
    /**
     * 更新アクションの実行
     *
     * @throws Exception
     */
    public function updateDiscussionSettings()
    {
        /** バリデーション形式 */
        if ($this->form()->validate()) {
            $this->response->goBack();
        }

        $settings = $this->request->from(
            'commentDateFormat',
            'commentsListSize',
            'commentsPageSize',
            'commentsPageDisplay',
            'commentsAvatar',
            'commentsOrder',
            'commentsMaxNestingLevels',
            'commentsUrlNofollow',
            'commentsPostTimeout',
            'commentsUniqueIpInterval',
            'commentsWhitelist',
            'commentsRequireMail',
            'commentsAvatarRating',
            'commentsPostTimeout',
            'commentsPostInterval',
            'commentsRequireModeration',
            'commentsRequireURL',
            'commentsHTMLTagAllowed',
            'commentsStopWords',
            'commentsIpBlackList'
        );
        $settings['commentsShow'] = $this->request->getArray('commentsShow');
        $settings['commentsPost'] = $this->request->getArray('commentsPost');

        $settings['commentsShowCommentOnly'] = $this->isEnableByCheckbox(
            $settings['commentsShow'],
            'commentsShowCommentOnly'
        );
        $settings['commentsMarkdown'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsMarkdown');
        $settings['commentsShowUrl'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsShowUrl');
        $settings['commentsUrlNofollow'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsUrlNofollow');
        $settings['commentsAvatar'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsAvatar');
        $settings['commentsPageBreak'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsPageBreak');
        $settings['commentsThreaded'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsThreaded');

        $settings['commentsPageSize'] = intval($settings['commentsPageSize']);
        $settings['commentsMaxNestingLevels'] = min(7, max(2, intval($settings['commentsMaxNestingLevels'])));
        $settings['commentsPageDisplay'] = ('first' == $settings['commentsPageDisplay']) ? 'first' : 'last';
        $settings['commentsOrder'] = ('DESC' == $settings['commentsOrder']) ? 'DESC' : 'ASC';
        $settings['commentsAvatarRating'] = in_array($settings['commentsAvatarRating'], ['G', 'PG', 'R', 'X'])
            ? $settings['commentsAvatarRating'] : 'G';

        $settings['commentsRequireModeration'] = $this->isEnableByCheckbox(
            $settings['commentsPost'],
            'commentsRequireModeration'
        );
        $settings['commentsWhitelist'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsWhitelist');
        $settings['commentsRequireMail'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsRequireMail');
        $settings['commentsRequireURL'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsRequireURL');
        $settings['commentsCheckReferer'] = $this->isEnableByCheckbox(
            $settings['commentsPost'],
            'commentsCheckReferer'
        );
        $settings['commentsAntiSpam'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsAntiSpam');
        $settings['commentsAutoClose'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsAutoClose');
        $settings['commentsPostIntervalEnable'] = $this->isEnableByCheckbox(
            $settings['commentsPost'],
            'commentsPostIntervalEnable'
        );

        $settings['commentsPostTimeout'] = intval($settings['commentsPostTimeout']) * 24 * 3600;
        $settings['commentsPostInterval'] = round($settings['commentsPostInterval'], 1) * 60;

        unset($settings['commentsShow']);
        unset($settings['commentsPost']);

        foreach ($settings as $name => $value) {
            $this->update(['value' => $value], $this->db->sql()->where('name = ?', $name));
        }

        Notice::alloc()->set(_t("設定が保存されました"), 'success');
        $this->response->goBack();
    }

    /**
     * 出力フォームの構造
     *
     * @return Form
     */
    public function form(): Form
    {
        /** フォームの構築 */
        $form = new Form($this->security->getIndex('/action/options-discussion'), Form::POST_METHOD);

        /** コメントの日付形式 */
        $commentDateFormat = new Form\Element\Text(
            'commentDateFormat',
            null,
            $this->options->commentDateFormat,
            _t('コメントの日付形式'),
            _t('これはデフォルトのフォーマットです。,テンプレートでShow Comment Dateメソッドを呼び出す場合, 日付フォーマットを指定しない場合, は次の形式で出力されます。.') . '<br />'
            . _t('具体的な書き方については <a href="https://www.php.net/manual/zh/function.date.php">PHP 日付の書式</a>.')
        );
        $commentDateFormat->input->setAttribute('class', 'w-40 mono');
        $form->addInput($commentDateFormat);

        /** コメントリストの数 */
        $commentsListSize = new Form\Element\Text(
            'commentsListSize',
            null,
            $this->options->commentsListSize,
            _t('コメントリストの数'),
            _t('此数目用于指定显示在侧边栏中的コメントリストの数.')
        );
        $commentsListSize->input->setAttribute('class', 'w-20');
        $form->addInput($commentsListSize->addRule('isInteger', _t('番号を記入してください')));

        $commentsShowOptions = [
            'commentsShowCommentOnly' => _t('コメントのみ表示, 表示なし Pingback 歌で応える Trackback'),
            'commentsMarkdown'        => _t('コメントでの使用 Markdown 語彙'),
            'commentsShowUrl'         => _t('コメント投稿者の名前が表示され、個人ページへのリンクが自動的に追加される。'),
            'commentsUrlNofollow'     => _t('コメント投稿者の個人ホームページへのリンクの使用 <a href="https://en.wikipedia.org/wiki/Nofollow">nofollow 因果性</a>'),
            'commentsAvatar'          => _t('コミッション <a href="https://gravatar.com">Gravatar</a> アバターサービス, 表示される最高評価は %s アバター',
                '</label><select id="commentsShow-commentsAvatarRating" name="commentsAvatarRating">
            <option value="G"' . ('G' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('G - 普通') . '</option>
            <option value="PG"' . ('PG' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('PG - 13歳によると上') . '</option>
            <option value="R"' . ('R' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('R - 17歳によると上成人') . '</option>
            <option value="X"' . ('X' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('X - 制限付き') . '</option></select>
            <label for="commentsShow-commentsAvatarRating">'),
            'commentsPageBreak'       => _t('コミッション分页, そして各ページには %s 論評, リストアップする際 %s デフォルト表示',
                '</label><input type="text" value="' . $this->options->commentsPageSize
                . '" class="text num text-s" id="commentsShow-commentsPageSize" name="commentsPageSize" /><label for="commentsShow-commentsPageSize">',
                '</label><select id="commentsShow-commentsPageDisplay" name="commentsPageDisplay">
            <option value="first"' . ('first' == $this->options->commentsPageDisplay ? ' selected="true"' : '') . '>' . _t('最初のページ') . '</option>
            <option value="last"' . ('last' == $this->options->commentsPageDisplay ? ' selected="true"' : '') . '>' . _t('最終ページ') . '</option></select>'
                . '<label for="commentsShow-commentsPageDisplay">'),
            'commentsThreaded'        => _t('コミッション评论回复, によると %s レイヤーを、1コメントあたりの返信レイヤーの最大数とする',
                    '</label><input name="commentsMaxNestingLevels" type="text" class="text num text-s" value="' . $this->options->commentsMaxNestingLevels . '" id="commentsShow-commentsMaxNestingLevels" />
            <label for="commentsShow-commentsMaxNestingLevels">') . '</label></span><span class="multiline">'
                . _t('そうしれいかん %s コメントは前面に表示される', '<select id="commentsShow-commentsOrder" name="commentsOrder">
            <option value="DESC"' . ('DESC' == $this->options->commentsOrder ? ' selected="true"' : '') . '>' . _t('新しい') . '</option>
            <option value="ASC"' . ('ASC' == $this->options->commentsOrder ? ' selected="true"' : '') . '>' . _t('年上') . '</option></select><label for="commentsShow-commentsOrder">')
        ];

        $commentsShowOptionsValue = [];
        if ($this->options->commentsShowCommentOnly) {
            $commentsShowOptionsValue[] = 'commentsShowCommentOnly';
        }

        if ($this->options->commentsMarkdown) {
            $commentsShowOptionsValue[] = 'commentsMarkdown';
        }

        if ($this->options->commentsShowUrl) {
            $commentsShowOptionsValue[] = 'commentsShowUrl';
        }

        if ($this->options->commentsUrlNofollow) {
            $commentsShowOptionsValue[] = 'commentsUrlNofollow';
        }

        if ($this->options->commentsAvatar) {
            $commentsShowOptionsValue[] = 'commentsAvatar';
        }

        if ($this->options->commentsPageBreak) {
            $commentsShowOptionsValue[] = 'commentsPageBreak';
        }

        if ($this->options->commentsThreaded) {
            $commentsShowOptionsValue[] = 'commentsThreaded';
        }

        $commentsShow = new Form\Element\Checkbox(
            'commentsShow',
            $commentsShowOptions,
            $commentsShowOptionsValue,
            _t('コメント表示')
        );
        $form->addInput($commentsShow->multiMode());

        /** 提出されたコメント */
        $commentsPostOptions = [
            'commentsRequireModeration'  => _t('すべてのコメントはモデレートされなければならない'),
            'commentsWhitelist'          => _t('レビュアーは過去にレビューが承認されたことがあること'),
            'commentsRequireMail'        => _t('メールアドレスの記入が必要'),
            'commentsRequireURL'         => _t('URLは必ず記入してください。'),
            'commentsCheckReferer'       => _t('コメントソースページをチェック URL 記事のリンクと一致しているか'),
            'commentsAntiSpam'           => _t('スパム対策を有効にする'),
            'commentsAutoClose'          => _t('記事掲載 %s 天によると后自动关闭评论',
                '</label><input name="commentsPostTimeout" type="text" class="text num text-s" value="' . intval($this->options->commentsPostTimeout / (24 * 3600)) . '" id="commentsPost-commentsPostTimeout" />
            <label for="commentsPost-commentsPostTimeout">'),
            'commentsPostIntervalEnable' => _t('同じ IP コメントを投稿できる時間は以下の通りです。 %s 議事録',
                '</label><input name="commentsPostInterval" type="text" class="text num text-s" value="' . round($this->options->commentsPostInterval / (60), 1) . '" id="commentsPost-commentsPostInterval" />
            <label for="commentsPost-commentsPostInterval">')
        ];

        $commentsPostOptionsValue = [];
        if ($this->options->commentsRequireModeration) {
            $commentsPostOptionsValue[] = 'commentsRequireModeration';
        }

        if ($this->options->commentsWhitelist) {
            $commentsPostOptionsValue[] = 'commentsWhitelist';
        }

        if ($this->options->commentsRequireMail) {
            $commentsPostOptionsValue[] = 'commentsRequireMail';
        }

        if ($this->options->commentsRequireURL) {
            $commentsPostOptionsValue[] = 'commentsRequireURL';
        }

        if ($this->options->commentsCheckReferer) {
            $commentsPostOptionsValue[] = 'commentsCheckReferer';
        }

        if ($this->options->commentsAntiSpam) {
            $commentsPostOptionsValue[] = 'commentsAntiSpam';
        }

        if ($this->options->commentsAutoClose) {
            $commentsPostOptionsValue[] = 'commentsAutoClose';
        }

        if ($this->options->commentsPostIntervalEnable) {
            $commentsPostOptionsValue[] = 'commentsPostIntervalEnable';
        }

        $commentsPost = new Form\Element\Checkbox(
            'commentsPost',
            $commentsPostOptions,
            $commentsPostOptionsValue,
            _t('提出されたコメント')
        );
        $form->addInput($commentsPost->multiMode());

        /** 無料HTMLタブ歌で応える因果性 */
        $commentsHTMLTagAllowed = new Form\Element\Textarea(
            'commentsHTMLTagAllowed',
            null,
            $this->options->commentsHTMLTagAllowed,
            _t('無料HTMLタブ歌で応える因果性'),
            _t('デフォルトのユーザーコメントはHTMLタブ, 你可によると在这里填写無料HTMLタブ.') . '<br />'
            . _t('例えば: %s', '<code>&lt;a href=&quot;&quot;&gt; &lt;img src=&quot;&quot;&gt; &lt;blockquote&gt;</code>')
        );
        $commentsHTMLTagAllowed->input->setAttribute('class', 'mono');
        $form->addInput($commentsHTMLTagAllowed);

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * バインド・アクション
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updateDiscussionSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
