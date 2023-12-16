<?php

namespace Typecho\Widget\Helper\PageNavigator;

use Typecho\Widget\Helper\PageNavigator;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * クラシックなページング・スタイル
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Classic extends PageNavigator
{
    /**
     * クラシックスタイルのページネーションのエクスポート
     *
     * @access public
     * @param string $prevWord 前のテキスト
     * @param string $nextWord 次の文章
     * @return void
     */
    public function render(string $prevWord = 'PREV', string $nextWord = 'NEXT')
    {
        $this->prev($prevWord);
        $this->next($nextWord);
    }

    /**
     * 前のページを出力する
     *
     * @access public
     * @param string $prevWord 前のテキスト
     * @return void
     */
    public function prev(string $prevWord = 'PREV')
    {
        //前のページを出力する
        if ($this->total > 0 && $this->currentPage > 1) {
            echo '<a class="prev" href="'
                . str_replace($this->pageHolder, $this->currentPage - 1, $this->pageTemplate)
                . $this->anchor . '">'
                . $prevWord . '</a>';
        }
    }

    /**
     * 出力 次のページ
     *
     * @access public
     * @param string $nextWord 次の文章
     * @return void
     */
    public function next(string $nextWord = 'NEXT')
    {
        //出力 次のページ
        if ($this->total > 0 && $this->currentPage < $this->totalPage) {
            echo '<a class="next" title="" href="'
                . str_replace($this->pageHolder, $this->currentPage + 1, $this->pageTemplate)
                . $this->anchor . '">'
                . $nextWord . '</a>';
        }
    }
}
