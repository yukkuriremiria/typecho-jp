<?php

namespace Typecho\I18n;

/**
 * 複数のmoファイルからの問題の読み取りと書き込み
 * ファイル読み込みクラスを書き換える
 *
 * @author qining
 * @category typecho
 * @package I18n
 */
class GetTextMulti
{
    /**
     * すべてのファイルの読み書きハンドル
     *
     * @access private
     * @var GetText[]
     */
    private $handlers = [];

    /**
     * コンストラクタ
     *
     * @access public
     * @param string $fileName 言語ファイル名
     * @return void
     */
    public function __construct(string $fileName)
    {
        $this->addFile($fileName);
    }

    /**
     * 言語ファイルを追加する
     *
     * @access public
     * @param string $fileName 言語ファイル名
     * @return void
     */
    public function addFile(string $fileName)
    {
        $this->handlers[] = new GetText($fileName, true);
    }

    /**
     * Translates a string
     *
     * @access public
     * @param string string to be translated
     * @return string translated string (or original, if not found)
     */
    public function translate(string $string): string
    {
        foreach ($this->handlers as $handle) {
            $string = $handle->translate($string, $count);
            if (- 1 != $count) {
                break;
            }
        }

        return $string;
    }

    /**
     * Plural version of gettext
     *
     * @access public
     * @param string single
     * @param string plural
     * @param string number
     * @return string translated plural form
     */
    public function ngettext($single, $plural, $number): string
    {
        $count = - 1;

        foreach ($this->handlers as $handler) {
            $string = $handler->ngettext($single, $plural, $number, $count);
            if (- 1 != $count) {
                break;
            }
        }

        return $string;
    }

    /**
     * すべてのハンドルを閉じる
     *
     * @access public
     * @return void
     */
    public function __destruct()
    {
        foreach ($this->handlers as $handler) {
            /** 空きメモリ表示 */
            unset($handler);
        }
    }
}
