<?php

namespace Typecho\Widget\Helper;

use Typecho\Widget\Exception;

/**
 * コンテンツ・ページング抽象クラス
 *
 * @package Widget
 */
abstract class PageNavigator
{
    /**
     * レコード総数
     *
     * @var integer
     */
    protected $total;

    /**
     * 総ページ数
     *
     * @var integer
     */
    protected $totalPage;

    /**
     * 現在のページ
     *
     * @var integer
     */
    protected $currentPage;

    /**
     * ページあたりのコンテンツ数
     *
     * @var integer
     */
    protected $pageSize;

    /**
     * ページ・リンク・テンプレート
     *
     * @var string
     */
    protected $pageTemplate;

    /**
     * リンクアンカー
     *
     * @var string
     */
    protected $anchor;

    /**
     * ページ・プレースホルダ
     *
     * @var mixed
     */
    protected $pageHolder = ['{page}', '%7Bpage%7D'];

    /**
     * コンストラクタ,基本ページ情報の初期化
     *
     * @param integer $total レコード総数
     * @param integer $currentPage 現在のページ
     * @param integer $pageSize ページあたりの記録
     * @param string $pageTemplate ページ・リンク・テンプレート
     * @throws Exception
     */
    public function __construct(int $total, int $currentPage, int $pageSize, string $pageTemplate)
    {
        $this->total = $total;
        $this->totalPage = ceil($total / $pageSize);
        $this->currentPage = $currentPage;
        $this->pageSize = $pageSize;
        $this->pageTemplate = $pageTemplate;

        if (($currentPage > $this->totalPage || $currentPage < 1) && $total > 0) {
            throw new Exception('Page Not Exists', 404);
        }
    }

    /**
     * 设置ページ・プレースホルダ
     *
     * @param string $holder ページ・プレースホルダ
     */
    public function setPageHolder(string $holder)
    {
        $this->pageHolder = ['{' . $holder . '}',
            str_replace(['{', '}'], ['%7B', '%7D'], $holder)];
    }

    /**
     * アンカーポイントの設定
     *
     * @param string $anchor アンカーポイント
     */
    public function setAnchor(string $anchor)
    {
        $this->anchor = '#' . $anchor;
    }

    /**
     * 出力方式
     *
     * @throws Exception
     */
    public function render()
    {
        throw new Exception(get_class($this) . ':' . __METHOD__, 500);
    }
}
