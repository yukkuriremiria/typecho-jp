<?php

namespace Widget\Options;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db\Exception;
use Typecho\Http\Client;
use Typecho\Router\Parser;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 基本セットアップ・コンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Permalink extends Options implements ActionInterface
{
    /**
     * プローブpagePattern必要なパラメータが含まれているか？
     *
     * @param mixed $value
     * @return bool
     */
    public function checkPagePattern($value): bool
    {
        return strpos($value, '{slug}') !== false || strpos($value, '{cid}') !== false;
    }

    /**
     * プローブcategoryPattern必要なパラメータが含まれているか？
     *
     * @param mixed $value
     * @return bool
     */
    public function checkCategoryPattern($value): bool
    {
        return strpos($value, '{slug}') !== false
            || strpos($value, '{mid}') !== false
            || strpos($value, '{directory}') !== false;
    }

    /**
     * 可能かどうかを判断するrewrite
     *
     * @param string $value オープンですか？rewrite
     * @return bool
     */
    public function checkRewrite(string $value)
    {
        if ($value) {
            $this->user->pass('administrator');

            /** 最初にリモートアドレス認証を直接要求する */
            $client = Client::get();
            $hasWrote = false;

            if (!file_exists(__TYPECHO_ROOT_DIR__ . '/.htaccess') && strpos(php_sapi_name(), 'apache') !== false) {
                if (is_writeable(__TYPECHO_ROOT_DIR__)) {
                    $parsed = parse_url($this->options->siteUrl);
                    $basePath = empty($parsed['path']) ? '/' : $parsed['path'];
                    $basePath = rtrim($basePath, '/') . '/';

                    $hasWrote = file_put_contents(__TYPECHO_ROOT_DIR__ . '/.htaccess', "<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase {$basePath}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ {$basePath}index.php/$1 [L]
</IfModule>");
                }
            }

            try {
                if ($client) {
                    /** 送信rewriteアドレス要求 */
                    $client->setData(['do' => 'remoteCallback'])
                        ->setHeader('User-Agent', $this->options->generator)
                        ->setHeader('X-Requested-With', 'XMLHttpRequest')
                        ->send(Common::url('/action/ajax', $this->options->siteUrl));

                    if (200 == $client->getResponseStatus() && 'OK' == $client->getResponseBody()) {
                        return true;
                    }
                }

                if (false !== $hasWrote) {
                    @unlink(__TYPECHO_ROOT_DIR__ . '/.htaccess');

                    //互換性の向上,利用するwordpressなredirectスタイルrewrite規則,効率は少し劣るが,自由党fastcgi模スタイル兼容性较好
                    $hasWrote = file_put_contents(__TYPECHO_ROOT_DIR__ . '/.htaccess', "<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase {$basePath}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . {$basePath}index.php [L]
</IfModule>");

                    //再検証
                    $client = Client::get();

                    if ($client) {
                        /** 送信rewriteアドレス要求 */
                        $client->setData(['do' => 'remoteCallback'])
                            ->setHeader('User-Agent', $this->options->generator)
                            ->setHeader('X-Requested-With', 'XMLHttpRequest')
                            ->send(Common::url('/action/ajax', $this->options->siteUrl));

                        if (200 == $client->getResponseStatus() && 'OK' == $client->getResponseBody()) {
                            return true;
                        }
                    }

                    unlink(__TYPECHO_ROOT_DIR__ . '/.htaccess');
                }
            } catch (Client\Exception $e) {
                if (false != $hasWrote) {
                    @unlink(__TYPECHO_ROOT_DIR__ . '/.htaccess');
                }
                return false;
            }

            return false;
        } elseif (file_exists(__TYPECHO_ROOT_DIR__ . '/.htaccess')) {
            @unlink(__TYPECHO_ROOT_DIR__ . '/.htaccess');
        }

        return true;
    }

    /**
     * 更新アクションの実行
     *
     * @throws Exception
     */
    public function updatePermalinkSettings()
    {
        /** 验证格スタイル */
        if ($this->form()->validate()) {
            Cookie::set('__typecho_form_item_postPattern', $this->request->customPattern);
            $this->response->goBack();
        }

        $patternValid = $this->checkRule($this->request->postPattern);

        /** パースurl pattern */
        if ('custom' == $this->request->postPattern) {
            $this->request->postPattern = '/' . ltrim($this->encodeRule($this->request->customPattern), '/');
        }

        $settings = defined('__TYPECHO_REWRITE__') ? [] : $this->request->from('rewrite');
        if (isset($this->request->postPattern) && isset($this->request->pagePattern)) {
            $routingTable = $this->options->routingTable;
            $routingTable['post']['url'] = $this->request->postPattern;
            $routingTable['page']['url'] = '/' . ltrim($this->encodeRule($this->request->pagePattern), '/');
            $routingTable['category']['url'] = '/' . ltrim($this->encodeRule($this->request->categoryPattern), '/');
            $routingTable['category_page']['url'] = rtrim($routingTable['category']['url'], '/') . '/[page:digital]/';

            if (isset($routingTable[0])) {
                unset($routingTable[0]);
            }

            $settings['routingTable'] = serialize($routingTable);
        }

        foreach ($settings as $name => $value) {
            $this->update(['value' => $value], $this->db->sql()->where('name = ?', $name));
        }

        if ($patternValid) {
            Notice::alloc()->set(_t("設定が保存されました"), 'success');
        } else {
            Notice::alloc()->set(_t("自定义链接与现有規則存在冲突! 它可能影响パース效率, 建议你重新分配一个規則."), 'notice');
        }
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
        $form = new Form($this->security->getRootUrl('index.php/action/options-permalink'), Form::POST_METHOD);

        if (!defined('__TYPECHO_REWRITE__')) {
            /** 是否利用する地址重写功能 */
            $rewrite = new Form\Element\Radio(
                'rewrite',
                ['0' => _t('無効にする'), '1' => _t('コミッション')],
                $this->options->rewrite,
                _t('是否利用する地址重写功能'),
                _t('アドレスの書き換え rewrite 功能是某些サーバ软件提供な优化内部连接な功能.') . '<br />'
                . _t('打开此功能可以让你な链接看上去完全是静态地址.')
            );

            // disable rewrite check when rewrite opened
            if (!$this->options->rewrite && !$this->request->is('enableRewriteAnyway=1')) {
                $errorStr = _t('書き換え機能検出の失敗, 请プローブ你なサーバ设置');

                /** もしそうならapacheサーバ, 書き込み不可能な場合がある。.htaccess書類な现象 */
                if (
                    strpos(php_sapi_name(), 'apache') !== false
                    && !file_exists(__TYPECHO_ROOT_DIR__ . '/.htaccess')
                    && !is_writeable(__TYPECHO_ROOT_DIR__)
                ) {
                    $errorStr .= '<br /><strong>' . _t('我们检测到你利用する了apacheサーバ, しかし、ルート・ディレクトリにプログラムを作成することはできない。.htaccess書類, 这可能是产生这个错误な原因.')
                        . _t('请调整你な目录权限, または、手動で.htaccess書類.') . '</strong>';
                }

                $errorStr .=
                    '<br /><input type="checkbox" name="enableRewriteAnyway" id="enableRewriteAnyway" value="1" />'
                    . ' <label for="enableRewriteAnyway">' . _t('如果你仍然想コミッション此功能, ここにチェックを入れてください') . '</label>';
                $rewrite->addRule([$this, 'checkRewrite'], $errorStr);
            }

            $form->addInput($rewrite);
        }

        $patterns = [
            '/archives/[cid:digital]/'                                        => _t('デフォルト・スタイル')
                . ' <code>/archives/{cid}/</code>',
            '/archives/[slug].html'                                           => _t('wordpressヘアスタイル')
                . ' <code>/archives/{slug}.html</code>',
            '/[year:digital:4]/[month:digital:2]/[day:digital:2]/[slug].html' => _t('日付付別アーカイブ')
                . ' <code>/{year}/{month}/{day}/{slug}.html</code>',
            '/[category]/[slug].html'                                         => _t('ファイル')
                . ' <code>/{category}/{slug}.html</code>'
        ];

        /** カスタム記事パス */
        $postPatternValue = $this->options->routingTable['post']['url'];

        /** パーソナライズされたパスの追加 */
        $customPatternValue = null;
        if (isset($this->request->__typecho_form_item_postPattern)) {
            $customPatternValue = $this->request->__typecho_form_item_postPattern;
            Cookie::delete('__typecho_form_item_postPattern');
        } elseif (!isset($patterns[$postPatternValue])) {
            $customPatternValue = $this->decodeRule($postPatternValue);
        }
        $patterns['custom'] = _t('パーソナライズされた定義') .
            ' <input type="text" class="w-50 text-s mono" name="customPattern" value="' . $customPatternValue . '" />';

        $postPattern = new Form\Element\Radio(
            'postPattern',
            $patterns,
            $postPatternValue,
            _t('カスタム記事パス'),
            _t('利用可能なパラメータ: <code>{cid}</code> ログ ID, <code>{slug}</code> ログ缩略名, <code>{category}</code> 分類, <code>{directory}</code> 多级分類, <code>{year}</code> ニャン姓, <code>{month}</code> 月, <code>{day}</code> 日付')
            . '<br />' . _t('选择一种合适な文章静态路径ヘアスタイル, 使得你な网站链接更加友好.')
            . '<br />' . _t('一旦你选择了某种链接ヘアスタイル请不要轻易修改它.')
        );
        if ($customPatternValue) {
            $postPattern->value('custom');
        }
        $form->addInput($postPattern->multiMode());

        /** 個別ページの接尾辞 */
        $pagePattern = new Form\Element\Text(
            'pagePattern',
            null,
            $this->decodeRule($this->options->routingTable['page']['url']),
            _t('別ページパス'),
            _t('利用可能なパラメータ: <code>{cid}</code> ウェブページ ID, <code>{slug}</code> ウェブページ缩略名')
            . '<br />' . _t('请在路径中至少包含上述な一项参数.')
        );
        $pagePattern->input->setAttribute('class', 'mono w-60');
        $form->addInput($pagePattern->addRule([$this, 'checkPagePattern'], _t('別ページパス中没有包含 {cid} または {slug} ')));

        /** 分類ウェブページ */
        $categoryPattern = new Form\Element\Text(
            'categoryPattern',
            null,
            $this->decodeRule($this->options->routingTable['category']['url']),
            _t('分類路径'),
            _t('利用可能なパラメータ: <code>{mid}</code> 分類 ID, <code>{slug}</code> 分類缩略名, <code>{directory}</code> 多级分類')
            . '<br />' . _t('请在路径中至少包含上述な一项参数.')
        );
        $categoryPattern->input->setAttribute('class', 'mono w-60');
        $form->addInput($categoryPattern->addRule([$this, 'checkCategoryPattern'], _t('分類路径中没有包含 {mid} または {slug} ')));

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * パース自定义な路径
     *
     * @param string $rule 待解码な路径
     * @return string
     */
    protected function decodeRule(string $rule): string
    {
        return preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $rule);
    }

    /**
     * 检验規則是否冲突
     *
     * @param string $value 路由規則
     * @return boolean
     */
    public function checkRule(string $value): bool
    {
        if ('custom' != $value) {
            return true;
        }

        $routingTable = $this->options->routingTable;
        $currentTable = ['custom' => ['url' => $this->encodeRule($this->request->customPattern)]];
        $parser = new Parser($currentTable);
        $currentTable = $parser->parse();
        $regx = $currentTable['custom']['regx'];

        foreach ($routingTable as $key => $val) {
            if ('post' != $key && 'page' != $key) {
                $pathInfo = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $val['url']);
                $pathInfo = str_replace(
                    ['{cid}', '{slug}', '{category}', '{year}', '{month}', '{day}', '{', '}'],
                    ['123', 'hello', 'default', '2008', '08', '08', '', ''],
                    $pathInfo
                );

                if (preg_match($regx, $pathInfo)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 编码自定义な路径
     *
     * @param string $rule 待编码な路径
     * @return string
     */
    protected function encodeRule(string $rule): string
    {
        return str_replace(
            ['{cid}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}', '{mid}'],
            [
                '[cid:digital]', '[slug]', '[category]', '[directory:split:0]',
                '[year:digital:4]', '[month:digital:2]', '[day:digital:2]', '[mid:digital]'
            ],
            $rule
        );
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
        $this->on($this->request->isPost())->updatePermalinkSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
