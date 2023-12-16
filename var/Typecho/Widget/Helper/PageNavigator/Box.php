<?php

namespace Typecho\Widget\Helper\PageNavigator;

use Typecho\Widget\Helper\PageNavigator;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ボックス・ページング・スタイル
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Box extends PageNavigator
{
    /**
     * ボックススタイルの改ページを出力する
     *
     * @access public
     * @param string $prevWord 前のテキスト
     * @param string $nextWord 次の文章
     * @param int $splitPage セグメンテーションの範囲
     * @param string $splitWord 分割文字
     * @param array $template
     * @return void
     */
    public function render(
        string $prevWord = 'PREV',
        string $nextWord = 'NEXT',
        int $splitPage = 3,
        string $splitWord = '...',
        array $template = []
    ) {
        if ($this->total < 1) {
            return;
        }

        $default = [
            'itemTag' => 'li',
            'textTag' => 'span',
            'currentClass' => 'current',
            'prevClass' => 'prev',
            'nextClass' => 'next'
        ];

        $template = array_merge($default, $template);
        extract($template);

        // 定義するitem
        $itemBegin = empty($itemTag) ? '' : ('<' . $itemTag . '>');
        $itemCurrentBegin = empty($itemTag) ? '' : ('<' . $itemTag
            . (empty($currentClass) ? '' : ' class="' . $currentClass . '"') . '>');
        $itemPrevBegin = empty($itemTag) ? '' : ('<' . $itemTag
            . (empty($prevClass) ? '' : ' class="' . $prevClass . '"') . '>');
        $itemNextBegin = empty($itemTag) ? '' : ('<' . $itemTag
            . (empty($nextClass) ? '' : ' class="' . $nextClass . '"') . '>');
        $itemEnd = empty($itemTag) ? '' : ('</' . $itemTag . '>');
        $textBegin = empty($textTag) ? '' : ('<' . $textTag . '>');
        $textEnd = empty($textTag) ? '' : ('</' . $textTag . '>');
        $linkBegin = '<a href="%s">';
        $linkCurrentBegin = empty($itemTag) ? ('<a href="%s"'
            . (empty($currentClass) ? '' : ' class="' . $currentClass . '"') . '>')
            : $linkBegin;
        $linkPrevBegin = empty($itemTag) ? ('<a href="%s"'
            . (empty($prevClass) ? '' : ' class="' . $prevClass . '"') . '>')
            : $linkBegin;
        $linkNextBegin = empty($itemTag) ? ('<a href="%s"'
            . (empty($nextClass) ? '' : ' class="' . $nextClass . '"') . '>')
            : $linkBegin;
        $linkEnd = '</a>';

        $from = max(1, $this->currentPage - $splitPage);
        $to = min($this->totalPage, $this->currentPage + $splitPage);

        //前のページを出力する
        if ($this->currentPage > 1) {
            echo $itemPrevBegin . sprintf(
                $linkPrevBegin,
                str_replace($this->pageHolder, $this->currentPage - 1, $this->pageTemplate) . $this->anchor
            )
                . $prevWord . $linkEnd . $itemEnd;
        }

        //最初のページをエクスポートする
        if ($from > 1) {
            echo $itemBegin
                . sprintf($linkBegin, str_replace($this->pageHolder, 1, $this->pageTemplate) . $this->anchor)
                . '1' . $linkEnd . $itemEnd;

            if ($from > 2) {
                //出力省略記号
                echo $itemBegin . $textBegin . $splitWord . $textEnd . $itemEnd;
            }
        }

        //出力中間ページ
        for ($i = $from; $i <= $to; $i++) {
            $current = ($i == $this->currentPage);

            echo ($current ? $itemCurrentBegin : $itemBegin) . sprintf(
                ($current ? $linkCurrentBegin : $linkBegin),
                str_replace($this->pageHolder, $i, $this->pageTemplate) . $this->anchor
            )
                . $i . $linkEnd . $itemEnd;
        }

        //最終ページの出力
        if ($to < $this->totalPage) {
            if ($to < $this->totalPage - 1) {
                echo $itemBegin . $textBegin . $splitWord . $textEnd . $itemEnd;
            }

            echo $itemBegin
                . sprintf(
                    $linkBegin,
                    str_replace($this->pageHolder, $this->totalPage, $this->pageTemplate) . $this->anchor
                )
                . $this->totalPage . $linkEnd . $itemEnd;
        }

        //出力 次のページ
        if ($this->currentPage < $this->totalPage) {
            echo $itemNextBegin . sprintf(
                $linkNextBegin,
                str_replace($this->pageHolder, $this->currentPage + 1, $this->pageTemplate) . $this->anchor
            )
                . $nextWord . $linkEnd . $itemEnd;
        }
    }
}
