<?php

namespace Widget\Contents\Post;

use Typecho\Config;
use Typecho\Db;
use Typecho\Router;
use Typecho\Widget;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 日付別アーカイブ リストコンポーネント
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Date extends Base
{
    /**
     * @param Config $parameter
     */
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('format=Y-m&type=month&limit=0');
    }

    /**
     * 初期化関数
     *
     * @return void
     */
    public function execute()
    {
        /** パラメータのデフォルト設定 */
        $this->parameter->setDefault('format=Y-m&type=month&limit=0');

        $resource = $this->db->query($this->db->select('created')->from('table.contents')
            ->where('type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created < ?', $this->options->time)
            ->order('table.contents.created', Db::SORT_DESC));

        $offset = $this->options->timezone - $this->options->serverTimezone;
        $result = [];
        while ($post = $this->db->fetchRow($resource)) {
            $timeStamp = $post['created'] + $offset;
            $date = date($this->parameter->format, $timeStamp);

            if (isset($result[$date])) {
                $result[$date]['count'] ++;
            } else {
                $result[$date]['year'] = date('Y', $timeStamp);
                $result[$date]['month'] = date('m', $timeStamp);
                $result[$date]['day'] = date('d', $timeStamp);
                $result[$date]['date'] = $date;
                $result[$date]['count'] = 1;
            }
        }

        if ($this->parameter->limit > 0) {
            $result = array_slice($result, 0, $this->parameter->limit);
        }

        foreach ($result as $row) {
            $row['permalink'] = Router::url(
                'archive_' . $this->parameter->type,
                $row,
                $this->options->index
            );
            $this->push($row);
        }
    }
}
