<?php

namespace IXR;

/**
 * IXR Base64エンコーディング
 *
 * @package IXR
 */
class Base64
{
    /**
     * エンコーディング数字
     *
     * @var string
     */
    private $data;

    /**
     * 初期化データ
     *
     * @param string $data
     */
    public function __construct(string $data)
    {
        $this->data = $data;
    }

    /**
     * ゲインXML数字
     *
     * @return string
     */
    public function getXml()
    {
        return '<base64>' . base64_encode($this->data) . '</base64>';
    }
}
