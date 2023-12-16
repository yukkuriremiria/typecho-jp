<?php

namespace Typecho;

/**
 * 日付処理
 *
 * @author qining
 * @category typecho
 * @package Date
 */
class Date
{
    /**
     * ご希望のタイムゾーンオフセット
     *
     * @access public
     * @var integer
     */
    public static $timezoneOffset = 0;

    /**
     * サーバータイムゾーンオフセット
     *
     * @access public
     * @var integer
     */
    public static $serverTimezoneOffset = 0;

    /**
     * 現在のサーバーのタイムスタンプ
     *
     * @access public
     * @var integer
     */
    public static $serverTimeStamp;

    /**
     * 直接変換可能なタイムスタンプ
     *
     * @access public
     * @var integer
     */
    public $timeStamp = 0;

    /**
     * @var string
     */
    public $year;

    /**
     * @var string
     */
    public $month;

    /**
     * @var string
     */
    public $day;

    /**
     * 初期化パラメータ
     *
     * @param integer|null $time タイムスタンプ
     */
    public function __construct(?int $time = null)
    {
        $this->timeStamp = (null === $time ? self::time() : $time)
            + (self::$timezoneOffset - self::$serverTimezoneOffset);

        $this->year = date('Y', $this->timeStamp);
        $this->month = date('m', $this->timeStamp);
        $this->day = date('d', $this->timeStamp);
    }

    /**
     * 現在希望するタイムゾーンのオフセットを設定する
     *
     * @param integer $offset
     */
    public static function setTimezoneOffset(int $offset)
    {
        self::$timezoneOffset = $offset;
        self::$serverTimezoneOffset = idate('Z');
    }

    /**
     * フォーマット時間の取得
     *
     * @param string $format 時間形式
     * @return string
     */
    public function format(string $format): string
    {
        return date($format, $this->timeStamp);
    }

    /**
     * 国際化オフセット時間の取得
     *
     * @return string
     */
    public function word(): string
    {
        return I18n::dateWord($this->timeStamp, self::time() + (self::$timezoneOffset - self::$serverTimezoneOffset));
    }

    /**
     * ゲインGMT回
     *
     * @deprecated
     * @return int
     */
    public static function gmtTime(): int
    {
        return self::time();
    }

    /**
     * ゲイン服务器回
     *
     * @return int
     */
    public static function time(): int
    {
        return self::$serverTimeStamp ?: (self::$serverTimeStamp = time());
    }
}
