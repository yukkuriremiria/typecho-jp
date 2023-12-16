<?php

namespace Utils;

/**
 * AutoP
 *
 * @copyright Copyright (c) 2012 Typecho Team. (http://typecho.org)
 * @author Joyqi <magike.net@gmail.com>
 * @license GNU General Public License 2.0
 */
class AutoP
{
    // 段落としてのタグ付け
    private const BLOCK = 'p|pre|div|blockquote|form|ul|ol|dd|table|ins|h1|h2|h3|h4|h5|h6';

    /**
     * ユニークid
     *
     * @access private
     * @var integer
     */
    private $uniqueId = 0;

    /**
     * 保存された段落
     *
     * @access private
     * @var array
     */
    private $blocks = [];

    /**
     * 段落置換のコールバック関数
     *
     * @param array $matches マッチ値
     * @return string
     */
    public function replaceBlockCallback(array $matches): string
    {
        $tagMatch = '|' . $matches[1] . '|';
        $text = $matches[4];

        switch (true) {
            /** 支出brラインフィードを扱う */
            case false !== strpos(
                '|li|dd|dt|td|p|a|span|cite|strong|sup|sub|small|del|u|i|b|ins|h1|h2|h3|h4|h5|h6|',
                $tagMatch
            ):
                $text = nl2br(trim($text));
                break;
            /** 支出段落ラインフィードを扱う */
            case false !== strpos('|div|blockquote|form|', $tagMatch):
                $text = $this->cutByBlock($text);
                if (false !== strpos($text, '</p><p>')) {
                    $text = $this->fixParagraph($text);
                }
                break;
            default:
                break;
        }

        /** 段落能力のないラベリング */
        if (false !== strpos('|a|span|font|code|cite|strong|sup|sub|small|del|u|i|b|', $tagMatch)) {
            $key = '<b' . $matches[2] . '/>';
        } else {
            $key = '<p' . $matches[2] . '/>';
        }

        $this->blocks[$key] = "<{$matches[1]}{$matches[3]}>{$text}</{$matches[1]}>";
        return $key;
    }

    /**
     * 支出段落方法ラインフィードを扱う
     *
     * @param string $text
     * @return string
     */
    private function cutByBlock(string $text): string
    {
        $space = "( |　)";
        $text = str_replace("\r\n", "\n", trim($text));
        $text = preg_replace("/{$space}*\n{$space}*/is", "\n", $text);
        $text = preg_replace("/\s*<p:([0-9]{4})\/>\s*/is", "</p><p:\\1/><p>", $text);
        $text = preg_replace("/\n{2,}/", "</p><p>", $text);
        $text = nl2br($text);
        $text = preg_replace("/(<p>)?\s*<p:([0-9]{4})\/>\s*(<\/p>)?/is", "<p:\\2/>", $text);
        $text = preg_replace("/<p>{$space}*<\/p>/is", '', $text);
        $text = preg_replace("/\s*<p>\s*$/is", '', $text);
        $text = preg_replace("/^\s*<\/p>\s*/is", '', $text);
        return $text;
    }

    /**
     * 段落の始まりと終わりの修復
     *
     * @param string $text
     * @return string
     */
    private function fixParagraph(string $text): string
    {
        $text = trim($text);
        if (!preg_match("/^<(" . self::BLOCK . ")(\s|>)/i", $text)) {
            $text = '<p>' . $text;
        }

        if (!preg_match("/<\/(" . self::BLOCK . ")>$/i", $text)) {
            $text = $text . '</p>';
        }

        return $text;
    }

    /**
     * 自動セグメンテーション
     *
     * @param string $text
     * @return string
     */
    public function parse(string $text): string
    {
        /** カウンターリセット */
        $this->uniqueId = 0;
        $this->blocks = [];

        /** 既存の段落の後の改行を取り除く */
        $text = preg_replace(["/<\/p>\s+<p(\s*)/is", "/\s*<br\s*\/?>\s*/is"], ["</p><p\\1", "<br />"], trim($text));

        /** 将所有非自闭合标签解析为ユニーク的字符串 */
        $foundTagCount = 0;
        $textLength = strlen($text);
        $uniqueIdList = [];

        if (preg_match_all("/<\/\s*([a-z0-9]+)>/is", $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $key => $match) {
                $tag = $matches[1][$key][0];

                $leftOffset = $match[1] - $textLength;
                $posSingle = strrpos($text, '<' . $tag . '>', $leftOffset);
                $posFix = strrpos($text, '<' . $tag . ' ', $leftOffset);
                $pos = false;

                switch (true) {
                    case (false !== $posSingle && false !== $posFix):
                        $pos = max($posSingle, $posFix);
                        break;
                    case false === $posSingle && false !== $posFix:
                        $pos = $posFix;
                        break;
                    case false !== $posSingle && false === $posFix:
                        $pos = $posSingle;
                        break;
                    default:
                        break;
                }

                if (false !== $pos) {
                    $uniqueId = $this->makeUniqueId();
                    $uniqueIdList[$uniqueId] = $tag;
                    $tagLength = strlen($tag);

                    $text = substr_replace($text, $uniqueId, $pos + 1 + $tagLength, 0);
                    $text = substr_replace(
                        $text,
                        $uniqueId,
                        $match[1] + 7 + $foundTagCount * 10 + $tagLength,
                        0
                    ); // 7 = 5 + 2
                    $foundTagCount++;
                }
            }
        }

        foreach ($uniqueIdList as $uniqueId => $tag) {
            $text = preg_replace_callback(
                "/<({$tag})({$uniqueId})([^>]*)>(.*)<\/\\1\\2>/is",
                [$this, 'replaceBlockCallback'],
                $text,
                1
            );
        }

        $text = $this->cutByBlock($text);
        $blocks = array_reverse($this->blocks);

        foreach ($blocks as $blockKey => $blockValue) {
            $text = str_replace($blockKey, $blockValue, $text);
        }

        return $this->fixParagraph($text);
    }

    /**
     * 生成ユニーク的id, スピードを考慮した最大限のサポート1万tagバランスの取れた参加
     *
     * @return string
     */
    private function makeUniqueId(): string
    {
        return ':' . str_pad($this->uniqueId ++, 4, '0', STR_PAD_LEFT);
    }
}

