<?php

namespace Widget;

use Typecho\Cookie;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * ティップボックス・コンポーネント
 *
 * @package Widget
 */
class Notice extends Widget
{
    /**
     * キュー・ハイライト
     *
     * @var string
     */
    public $highlight;

    /**
     * 関連要素のハイライト
     *
     * @param string $theId 強調すべき要素id
     */
    public function highlight(string $theId)
    {
        $this->highlight = $theId;
        Cookie::set(
            '__typecho_notice_highlight',
            $theId
        );
    }

    /**
     * ハイライトid
     *
     * @return integer
     */
    public function getHighlightId(): int
    {
        return preg_match("/[0-9]+/", $this->highlight, $matches) ? $matches[0] : 0;
    }

    /**
     * スタックの各行の値を設定する
     *
     * @param string|array $value に対応するキー値。
     * @param string|null $type チップタイプ
     * @param string $typeFix 古いプラグインとの互換性
     */
    public function set($value, ?string $type = 'notice', string $typeFix = 'notice')
    {
        $notice = is_array($value) ? array_values($value) : [$value];
        if (empty($type) && $typeFix) {
            $type = $typeFix;
        }

        Cookie::set(
            '__typecho_notice',
            json_encode($notice)
        );
        Cookie::set(
            '__typecho_notice_type',
            $type
        );
    }
}
