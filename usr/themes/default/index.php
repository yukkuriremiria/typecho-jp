<?php
/**
 * Default theme for Typecho
 *
 * @package Typecho Replica Theme
 * @author Typecho Team
 * @version 1.2
 * @link http://typecho.org
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php while ($this->next()): ?>
        <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
            <h2 class="post-title" itemprop="name headline">
                <a itemprop="url"
                   href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
            </h2>
            <ul class="post-meta">
                <li itemprop="author" itemscope itemtype="http://schema.org/Person"><?php _e('著者: '); ?><a
                        itemprop="name" href="<?php $this->author->permalink(); ?>"
                        rel="author"><?php $this->author(); ?></a></li>
                <li><?php _e('回: '); ?>
                    <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php $this->date(); ?></time>
                </li>
                <li><?php _e('分類: '); ?><?php $this->category(','); ?></li>
                <li itemprop="interactionCount">
                    <a itemprop="discussionUrl"
                       href="<?php $this->permalink() ?>#comments"><?php $this->commentsNum('解説', '1 条解説', '%d 条解説'); ?></a>
                </li>
            </ul>
            <div class="post-content" itemprop="articleBody">
                <?php $this->content('- 続きを読む -'); ?>
            </div>
        </article>
    <?php endwhile; ?>

    <?php $this->pageNav('&laquo; 前のページ', 'バックページ &raquo;'); ?>
</div><!-- end #main-->

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
