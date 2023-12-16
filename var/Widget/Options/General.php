<?php

namespace Widget\Options;

use Typecho\Db\Exception;
use Typecho\I18n\GetText;
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
class General extends Options implements ActionInterface
{
    /**
     * 言語リストに登録されているか確認する
     *
     * @param string $lang
     * @return bool
     */
    public function checkLang(string $lang): bool
    {
        $langs = self::getLangs();
        return isset($langs[$lang]);
    }

    /**
     * 言語リストの取得
     *
     * @return array
     */
    public static function getLangs(): array
    {
        $dir = defined('__TYPECHO_LANG_DIR__') ? __TYPECHO_LANG_DIR__ : __TYPECHO_ROOT_DIR__ . '/usr/langs';
        $files = glob($dir . '/*.mo');
        $langs = ['zh_CN' => '簡体字中国語'];

        if (!empty($files)) {
            foreach ($files as $file) {
                $getText = new GetText($file, false);
                [$name] = explode('.', basename($file));
                $title = $getText->translate('lang', $count);
                $langs[$name] = $count > - 1 ? $title : $name;
            }

            ksort($langs);
        }

        return $langs;
    }

    /**
     * 実行可能サフィックスのフィルタリング
     *
     * @param string $ext
     * @return boolean
     */
    public function removeShell(string $ext): bool
    {
        return !preg_match("/^(php|php4|php5|sh|asp|jsp|rb|py|pl|dll|exe|bat)$/i", $ext);
    }

    /**
     * 更新アクションの実行
     *
     * @throws Exception
     */
    public function updateGeneralSettings()
    {
        /** バリデーション形式 */
        if ($this->form()->validate()) {
            $this->response->goBack();
        }

        $settings = $this->request->from(
            'title',
            'description',
            'keywords',
            'allowRegister',
            'allowXmlRpc',
            'lang',
            'timezone'
        );
        $settings['attachmentTypes'] = $this->request->getArray('attachmentTypes');

        if (!defined('__TYPECHO_SITE_URL__')) {
            $settings['siteUrl'] = rtrim($this->request->siteUrl, '/');
        }

        $attachmentTypes = [];
        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@image@')) {
            $attachmentTypes[] = '@image@';
        }

        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@media@')) {
            $attachmentTypes[] = '@media@';
        }

        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@doc@')) {
            $attachmentTypes[] = '@doc@';
        }

        $attachmentTypesOther = $this->request->filter('trim', 'strtolower')->attachmentTypesOther;
        if ($this->isEnableByCheckbox($settings['attachmentTypes'], '@other@') && !empty($attachmentTypesOther)) {
            $types = implode(
                ',',
                array_filter(array_map('trim', explode(',', $attachmentTypesOther)), [$this, 'removeShell'])
            );

            if (!empty($types)) {
                $attachmentTypes[] = $types;
            }
        }

        $settings['attachmentTypes'] = implode(',', $attachmentTypes);
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
        $form = new Form($this->security->getIndex('/action/options-general'), Form::POST_METHOD);

        /** サイト名 */
        $title = new Form\Element\Text('title', null, $this->options->title, _t('サイト名'), _t('サイト名はページのタイトルに表示されます。.'));
        $title->input->setAttribute('class', 'w-100');
        $form->addInput($title->addRule('required', _t('请填写サイト名'))
            ->addRule('xssCheck', _t('请不要在サイト名中使用特殊字符')));

        /** サイトアドレス */
        if (!defined('__TYPECHO_SITE_URL__')) {
            $siteUrl = new Form\Element\Text(
                'siteUrl',
                null,
                $this->options->originalSiteUrl,
                _t('サイトアドレス'),
                _t('サイトアドレス主要用于生成内容的永久链接.') . ($this->options->originalSiteUrl == $this->options->rootUrl ?
                    '' : '</p><p class="message notice mono">'
                    . _t('現住所 <strong>%s</strong> 上記設定との不整合', $this->options->rootUrl))
            );
            $siteUrl->input->setAttribute('class', 'w-100 mono');
            $form->addInput($siteUrl->addRule('required', _t('请填写サイトアドレス'))
                ->addRule('url', _t('法定事項を記入してください。URL住所')));
        }

        /** サイト概要 */
        $description = new Form\Element\Text(
            'description',
            null,
            $this->options->description,
            _t('サイト概要'),
            _t('サイト概要将显示在网页代码的头部.')
        );
        $form->addInput($description->addRule('xssCheck', _t('请不要在サイト概要中使用特殊字符')));

        /** 笑い草 */
        $keywords = new Form\Element\Text(
            'keywords',
            null,
            $this->options->keywords,
            _t('笑い草'),
            _t('ハーフコンマをお願いします。 "," 複数のキーワードを分割する.')
        );
        $form->addInput($keywords->addRule('xssCheck', _t('请不要在笑い草中使用特殊字符')));

        /** 在籍 */
        $allowRegister = new Form\Element\Radio(
            'allowRegister',
            ['0' => _t('不許可'), '1' => _t('可')],
            $this->options->allowRegister,
            _t('是否可在籍'),
            _t('可访问者在籍到你的网站, 默认的在籍用户不享有任何写入权限.')
        );
        $form->addInput($allowRegister);

        /** XMLRPC */
        $allowXmlRpc = new Form\Element\Radio(
            'allowXmlRpc',
            ['0' => _t('凝固'), '1' => _t('仅凝固 Pingback コネクタ'), '2' => _t('見せる')],
            $this->options->allowXmlRpc,
            _t('XMLRPC コネクタ')
        );
        $form->addInput($allowXmlRpc);

        /** 言語項目 */
        // hack 言語スキャン
        _t('lang');

        $langs = self::getLangs();

        if (count($langs) > 1) {
            $lang = new Form\Element\Select('lang', $langs, $this->options->lang, _t('多言語主義'));
            $form->addInput($lang->addRule([$this, 'checkLang'], _t('所选择的多言語主義包不存在')));
        }

        /** タイムゾーン */
        $timezoneList = [
            "0"      => _t('グリニッジ(子午線)標準時 (GMT)'),
            "3600"   => _t('中欧標準時 オランダの首都アムステルダム,オランダ,フランス語 (GMT +1)'),
            "7200"   => _t('东欧標準時 ルーマニアの首都ブカレスト,キプロス,ギリシャ (GMT +2)'),
            "10800"  => _t('モスクワ時間 イラク,エチオピア,マダガスカル (GMT +3)'),
            "14400"  => _t('トビリシ時間 オマーン人,モーリタニア,レユニオン島（インド洋の島、フランスの海外県） (GMT +4)'),
            "18000"  => _t('ニューデリー時間 パキスタン,モルディブ (GMT +5)'),
            "21600"  => _t('コロンボ時間 ベンガル (GMT +6)'),
            "25200"  => _t('バンコク ジャカルタ クメール語,インドネシアの島々のひとつ、スマトラ島,ラオス (GMT +7)'),
            "28800"  => _t('中国標準時 本土,シンガポール人,ベトナム (GMT +8)'),
            "32400"  => _t('東京-平壌時間 シギリアン,モルッカ諸島 (GMT +9)'),
            "36000"  => _t('シドニー・グアム時間 タスマニア,ニューギニア (GMT +10)'),
            "39600"  => _t('南西太平洋のソロモン諸島 サハリン (GMT +11)'),
            "43200"  => _t('ウェリントン時間 ニュー・ジーランド,フィジー諸島 (GMT +12)'),
            "-3600"  => _t('フィデル諸島 アゾレス諸島,ポルトガル領ギニア (GMT -1)'),
            "-7200"  => _t('大西洋中部時間 グリーンランド (GMT -2)'),
            "-10800" => _t('ブエノスアイレス、アルゼンチンの首都 ウルグアイ,フランス領ギアナ (GMT -3)'),
            "-14400" => _t('チリ ブラジル ベネズエラ,ボリビア (GMT -4)'),
            "-18000" => _t('ニューヨーク州オタワ 禁輸,コロンビア特別区,ジャマイカ人 (GMT -5)'),
            "-21600" => _t('メキシコシティ時間 ホンジュラス,グアテマラ,コスタリカ (GMT -6)'),
            "-25200" => _t('デンバー, アメリカ合衆国 時間 (GMT -7)'),
            "-28800" => _t('サンフランシスコ（アメリカ合衆国） 時間 (GMT -8)'),
            "-32400" => _t('アラスカ時間 (GMT -9)'),
            "-36000" => _t('ハワイ諸島 (GMT -10)'),
            "-39600" => _t('東サモア (GMT -11)'),
            "-43200" => _t('エネウェタック島 (GMT -12)')
        ];

        $timezone = new Form\Element\Select('timezone', $timezoneList, $this->options->timezone, _t('タイムゾーン'));
        $form->addInput($timezone);

        /** 拡張子名 */
        $attachmentTypesOptionsResult = (null != trim($this->options->attachmentTypes)) ?
            array_map('trim', explode(',', $this->options->attachmentTypes)) : [];
        $attachmentTypesOptionsValue = [];

        if (in_array('@image@', $attachmentTypesOptionsResult)) {
            $attachmentTypesOptionsValue[] = '@image@';
        }

        if (in_array('@media@', $attachmentTypesOptionsResult)) {
            $attachmentTypesOptionsValue[] = '@media@';
        }

        if (in_array('@doc@', $attachmentTypesOptionsResult)) {
            $attachmentTypesOptionsValue[] = '@doc@';
        }

        $attachmentTypesOther = array_diff($attachmentTypesOptionsResult, $attachmentTypesOptionsValue);
        $attachmentTypesOtherValue = '';
        if (!empty($attachmentTypesOther)) {
            $attachmentTypesOptionsValue[] = '@other@';
            $attachmentTypesOtherValue = implode(',', $attachmentTypesOther);
        }

        $attachmentTypesOptions = [
            '@image@' => _t('画像ファイル') . ' <code>(gif jpg jpeg png tiff bmp webp avif)</code>',
            '@media@' => _t('マルチメディアファイル') . ' <code>(mp3 mp4 mov wmv wma rmvb rm avi flv ogg oga ogv)</code>',
            '@doc@'   => _t('ファイル') . ' <code>(txt doc docx xls xlsx ppt pptx zip rar pdf)</code>',
            '@other@' => _t(
                'その他のフォーマット %s',
                ' <input type="text" class="w-50 text-s mono" name="attachmentTypesOther" value="'
                . htmlspecialchars($attachmentTypesOtherValue) . '" />'
            ),
        ];

        $attachmentTypes = new Form\Element\Checkbox(
            'attachmentTypes',
            $attachmentTypesOptions,
            $attachmentTypesOptionsValue,
            _t('可上传的文件类型'),
            _t('カンマ "," セパレートサフィックス, 例えば: %s', '<code>cpp, h, mak</code>')
        );
        $form->addInput($attachmentTypes->multiMode());

        /** 送信ボタン */
        $submit = new Form\Element\Submit('submit', null, _t('設定の保存'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    /**
     * バインド・アクション
     */
    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updateGeneralSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
