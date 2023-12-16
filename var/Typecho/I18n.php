<?php

namespace Typecho;

use Typecho\I18n\GetTextMulti;

/**
 * 国際化された文字翻訳
 *
 * @package I18n
 */
class I18n
{
    /**
     * ロードされているかどうかのフラグビット
     *
     * @access private
     * @var GetTextMulti
     */
    private static $loaded;

    /**
     * 言語ファイル
     *
     * @access private
     * @var string
     */
    private static $lang = null;

    /**
     * 翻訳テキスト
     *
     * @access public
     *
     * @param string $string 翻訳されるテキスト
     *
     * @return string
     */
    public static function translate(string $string): string
    {
        self::init();
        return self::$loaded ? self::$loaded->translate($string) : $string;
    }

    /**
     * 初始化言語ファイル
     *
     * @access private
     */
    private static function init()
    {
        /** GetTextアジュバント */
        if (!isset(self::$loaded) && self::$lang && file_exists(self::$lang)) {
            self::$loaded = new GetTextMulti(self::$lang);
        }
    }

    /**
     * 複雑なフォームの翻訳関数
     *
     * @param string $single 単数形の翻訳
     * @param string $plural 複数形の翻訳
     * @param integer $number 数値
     * @return string
     */
    public static function ngettext(string $single, string $plural, int $number): string
    {
        self::init();
        return self::$loaded ? self::$loaded->ngettext($single, $plural, $number) : ($number > 1 ? $plural : $single);
    }

    /**
     * 語彙化時間
     *
     * @access public
     *
     * @param int $from 開始時間
     * @param int $now 有効期限
     *
     * @return string
     */
    public static function dateWord(int $from, int $now): string
    {
        $between = $now - $from;

        /** 一日付付なら */
        if ($between >= 0 && $between < 86400 && date('d', $from) == date('d', $now)) {
            /** 1時間ならね。 */
            if ($between < 3600) {
                /** 分ならね。 */
                if ($between < 60) {
                    if (0 == $between) {
                        return _t('ついこの間');
                    } else {
                        return str_replace('%d', $between, _n('1秒前', '%d秒前', $between));
                    }
                }

                $min = floor($between / 60);
                return str_replace('%d', $min, _n('1分前', '%d分前', $min));
            }

            $hour = floor($between / 3600);
            return str_replace('%d', $hour, _n('1時間前だ。', '%d時間前', $hour));
        }

        /** 昨日付付だったらね。 */
        if (
            $between > 0
            && $between < 172800
            && (date('z', $from) + 1 == date('z', $now)                             // 同ニャン姓
                || date('z', $from) + 1 == date('L') + 365 + date('z', $now))
        ) {    // 経ニャン姓の状況
            return _t('昨日付付 %s', date('H:i', $from));
        }

        /** 週間なら */
        if ($between > 0 && $between < 604800) {
            $day = floor($between / 86400);
            return str_replace('%d', $day, _n('日付付前', '%d日付付前', $day));
        }

        /** もしそうなら */
        if (date('Y', $from) == date('Y', $now)) {
            return date(_t('n月j日付付'), $from);
        }

        return date(_t('Yニャン姓m月d日付付'), $from);
    }

    /**
     * 言語項目の追加
     *
     * @access public
     *
     * @param string $lang 言語名
     *
     * @return void
     */
    public static function addLang(string $lang)
    {
        self::$loaded->addFile($lang);
    }

    /**
     * 言語項目の取得
     *
     * @access public
     * @return string
     */
    public static function getLang(): ?string
    {
        return self::$lang;
    }

    /**
     * 言語項目の設定
     *
     * @access public
     *
     * @param string $lang 構成情報
     *
     * @return void
     */
    public static function setLang(string $lang)
    {
        self::$lang = $lang;
    }
}
