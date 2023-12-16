<?php

namespace Widget\Comments;

use Typecho\Config;
use Typecho\Db\Exception;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 残響アーカイブ・コンポーネント
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Ping extends Comments
{
    /**
     * _customSinglePingCallback
     *
     * @var boolean
     * @access private
     */
    private $customSinglePingCallback = false;

    /**
     * @param Config $parameter
     */
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('parentId=0');

        /** コールバック関数の初期化 */
        if (function_exists('singlePing')) {
            $this->customSinglePingCallback = true;
        }
    }

    /**
     * アウトプット記事への回答数
     *
     * @param mixed ...$args コメント数
     */
    public function num(...$args)
    {
        if (empty($args)) {
            $args[] = '%d';
        }

        echo sprintf($args[$this->length] ?? array_pop($args), $this->length);
    }

    /**
     * execute
     *
     * @access public
     * @return void
     * @throws Exception
     */
    public function execute()
    {
        if (!$this->parameter->parentId) {
            return;
        }

        $select = $this->select()->where('table.comments.status = ?', 'approved')
            ->where('table.comments.cid = ?', $this->parameter->parentId)
            ->where('table.comments.type <> ?', 'comment')
            ->order('table.comments.coid', 'ASC');

        $this->db->fetchAll($select, [$this, 'push']);
    }

    /**
     * 反響を列挙する
     *
     * @param mixed $singlePingOptions 個別のエコー・カスタマイズ・オプション
     */
    public function listPings($singlePingOptions = null)
    {
        if ($this->have()) {
            //いくつかの変数を初期化する
            $parsedSinglePingOptions = Config::factory($singlePingOptions);
            $parsedSinglePingOptions->setDefault([
                'before'      => '<ol class="ping-list">',
                'after'       => '</ol>',
                'beforeTitle' => '',
                'afterTitle'  => '',
                'beforeDate'  => '',
                'afterDate'   => '',
                'dateFormat'  => $this->options->commentDateFormat
            ]);

            echo $parsedSinglePingOptions->before;

            while ($this->next()) {
                $this->singlePingCallback($parsedSinglePingOptions);
            }

            echo $parsedSinglePingOptions->after;
        }
    }

    /**
     * エコー・コールバック関数
     *
     * @param string $singlePingOptions 個別のエコー・カスタマイズ・オプション
     */
    private function singlePingCallback(string $singlePingOptions)
    {
        if ($this->customSinglePingCallback) {
            return singlePing($this, $singlePingOptions);
        }

        ?>
        <li id="<?php $this->theId(); ?>" class="ping-body">
            <div class="ping-title">
                <cite class="fn"><?php
                    $singlePingOptions->beforeTitle();
                    $this->author(true);
                    $singlePingOptions->afterTitle();
                ?></cite>
            </div>
            <div class="ping-meta">
                <a href="<?php $this->permalink(); ?>"><?php $singlePingOptions->beforeDate();
                    $this->date($singlePingOptions->dateFormat);
                    $singlePingOptions->afterDate(); ?></a>
            </div>
            <?php $this->content(); ?>
        </li>
        <?php
    }

    /**
     * 過負荷のコンテンツ獲得
     *
     * @return array|null
     */
    protected function ___parentContent(): ?array
    {
        return $this->parameter->parentContent;
    }
}
