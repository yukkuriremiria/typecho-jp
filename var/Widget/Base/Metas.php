<?php

namespace Widget\Base;

use Typecho\Common;
use Typecho\Db\Exception;
use Typecho\Db\Query;
use Typecho\Plugin;
use Typecho\Router;
use Widget\Base;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 記述的データ構成要素
 *
 * @property int $mid
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string $description
 * @property int $count
 * @property int $order
 * @property int $parent
 * @property-read string $theId
 * @property-read string $url
 * @property-read string $permalink
 * @property-read string $feedUrl
 * @property-read string $feedRssUrl
 * @property-read string $feedAtomUrl
 */
class Metas extends Base implements QueryInterface
{
    /**
     * レコード総数の取得
     *
     * @param Query $condition 計算条件
     * @return integer
     * @throws Exception
     */
    public function size(Query $condition): int
    {
        return $this->db->fetchObject($condition->select(['COUNT(mid)' => 'num'])->from('table.metas'))->num;
    }

    /**
     * 各行の値をスタックに押し込む
     *
     * @param array $value 1行あたりの価値
     * @return array
     */
    public function push(array $value): array
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    /**
     * 汎用フィルター
     *
     * @param array $value フィルタリングする行データ
     * @return array
     */
    public function filter(array $value): array
    {
        //静的リンクの生成
        $type = $value['type'];
        $routeExists = (null != Router::get($type));
        $tmpSlug = $value['slug'];
        $value['slug'] = urlencode($value['slug']);

        $value['url'] = $value['permalink'] = $routeExists ? Router::url($type, $value, $this->options->index) : '#';

        /** 集約リンクの生成 */
        /** RSS 2.0 */
        $value['feedUrl'] = $routeExists ? Router::url($type, $value, $this->options->feedUrl) : '#';

        /** RSS 1.0 */
        $value['feedRssUrl'] = $routeExists ? Router::url($type, $value, $this->options->feedRssUrl) : '#';

        /** ATOM 1.0 */
        $value['feedAtomUrl'] = $routeExists ? Router::url($type, $value, $this->options->feedAtomUrl) : '#';

        $value['slug'] = $tmpSlug;
        $value = Metas::pluginHandle()->filter($value, $this);
        return $value;
    }

    /**
     * 最大ソートを得る
     *
     * @param string $type
     * @param int $parent
     * @return integer
     * @throws Exception
     */
    public function getMaxOrder(string $type, int $parent = 0): int
    {
        return $this->db->fetchObject($this->db->select(['MAX(order)' => 'maxOrder'])
            ->from('table.metas')
            ->where('type = ? AND parent = ?', $type, $parent))->maxOrder ?? 0;
    }

    /**
     * データは以下のように分析される。sortフィールド・ソート
     *
     * @param array $metas
     * @param string $type
     */
    public function sort(array $metas, string $type)
    {
        foreach ($metas as $sort => $mid) {
            $this->update(
                ['order' => $sort + 1],
                $this->db->sql()->where('mid = ?', $mid)->where('type = ?', $type)
            );
        }
    }

    /**
     * レコードの更新
     *
     * @param array $rows 更新された値を記録する
     * @param Query $condition 更新条件
     * @return integer
     * @throws Exception
     */
    public function update(array $rows, Query $condition): int
    {
        return $this->db->query($condition->update('table.metas')->rows($rows));
    }

    /**
     * データの統合
     *
     * @param integer $mid データ主キー
     * @param string $type データタイプ
     * @param array $metas 統合されるデータセット
     * @throws Exception
     */
    public function merge(int $mid, string $type, array $metas)
    {
        $contents = array_column($this->db->fetchAll($this->select('cid')
            ->from('table.relationships')
            ->where('mid = ?', $mid)), 'cid');

        foreach ($metas as $meta) {
            if ($mid != $meta) {
                $existsContents = array_column($this->db->fetchAll($this->db
                    ->select('cid')->from('table.relationships')
                    ->where('mid = ?', $meta)), 'cid');

                $where = $this->db->sql()->where('mid = ? AND type = ?', $meta, $type);
                $this->delete($where);
                $diffContents = array_diff($existsContents, $contents);
                $this->db->query($this->db->delete('table.relationships')->where('mid = ?', $meta));

                foreach ($diffContents as $content) {
                    $this->db->query($this->db->insert('table.relationships')
                        ->rows(['mid' => $mid, 'cid' => $content]));
                    $contents[] = $content;
                }

                $this->update(['parent' => $mid], $this->db->sql()->where('parent = ?', $meta));
                unset($existsContents);
            }
        }

        $num = $this->db->fetchObject($this->db
            ->select(['COUNT(mid)' => 'num'])->from('table.relationships')
            ->where('table.relationships.mid = ?', $mid))->num;

        $this->update(['count' => $num], $this->db->sql()->where('mid = ?', $mid));
    }

    /**
     * 元のクエリーオブジェクトの取得
     *
     * @return Query
     * @throws Exception
     */
    public function select(): Query
    {
        return $this->db->select()->from('table.metas');
    }

    /**
     * 記録の削除
     *
     * @param Query $condition 条件の削除
     * @return integer
     * @throws Exception
     */
    public function delete(Query $condition): int
    {
        return $this->db->query($condition->delete('table.metas'));
    }

    /**
     * 基礎tagゲインID
     *
     * @param mixed $inputTags タグ名
     * @return array|int
     * @throws Exception
     */
    public function scanTags($inputTags)
    {
        $tags = is_array($inputTags) ? $inputTags : [$inputTags];
        $result = [];

        foreach ($tags as $tag) {
            if (empty($tag)) {
                continue;
            }

            $row = $this->db->fetchRow($this->select()
                ->where('type = ?', 'tag')
                ->where('name = ?', $tag)->limit(1));

            if ($row) {
                $result[] = $row['mid'];
            } else {
                $slug = Common::slugName($tag);

                if ($slug) {
                    $result[] = $this->insert([
                        'name'  => $tag,
                        'slug'  => $slug,
                        'type'  => 'tag',
                        'count' => 0,
                        'order' => 0,
                    ]);
                }
            }
        }

        return is_array($inputTags) ? $result : current($result);
    }

    /**
     * レコードの挿入
     *
     * @param array $rows レコード挿入値
     * @return integer
     * @throws Exception
     */
    public function insert(array $rows): int
    {
        return $this->db->query($this->db->insert('table.metas')->rows($rows));
    }

    /**
     * 内容のないラベルを一掃する
     *
     * @throws Exception
     */
    public function clearTags()
    {
        // プラスチック射出countというのも0ラベル
        $tags = array_column($this->db->fetchAll($this->db->select('mid')
            ->from('table.metas')->where('type = ? AND count = ?', 'tags', 0)), 'mid');

        foreach ($tags as $tag) {
            // アソシエーションがなくなったことを確認する
            $content = $this->db->fetchRow($this->db->select('cid')
                ->from('table.relationships')->where('mid = ?', $tag)
                ->limit(1));

            if (empty($content)) {
                $this->db->query($this->db->delete('table.metas')
                    ->where('mid = ?', $tag));
            }
        }
    }

    /**
     * 基礎内容的指定フォーム和情勢更新相关metaの計数情報
     *
     * @param int $mid meta id
     * @param string $type フォーム
     * @param string $status 情勢
     * @throws Exception
     */
    public function refreshCountByTypeAndStatus(int $mid, string $type, string $status = 'publish')
    {
        $num = $this->db->fetchObject($this->db->select(['COUNT(table.contents.cid)' => 'num'])->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $mid)
            ->where('table.contents.type = ?', $type)
            ->where('table.contents.status = ?', $status))->num;

        $this->db->query($this->db->update('table.metas')->rows(['count' => $num])
            ->where('mid = ?', $mid));
    }

    /**
     * アンカーポイントid
     *
     * @access protected
     * @return string
     */
    protected function ___theId(): string
    {
        return $this->type . '-' . $this->mid;
    }
}
