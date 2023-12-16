<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <h1 class="post-title" itemprop="name headline">
            <a itemprop="url"
               href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
        </h1>
        <ul class="post-meta">
            <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                <?php _e('著者: '); ?><a itemprop="name"
                                       href="<?php $this->author->permalink(); ?>"
                                       rel="author"><?php $this->author(); ?></a>
            </li>
            <li><?php _e('回: '); ?>
                <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php $this->date(); ?></time>
            </li>
            <li><?php _e('分類: '); ?><?php $this->category(','); ?></li>
        </ul>
        <div class="post-content" itemprop="articleBody">
            <?php $this->content(); ?>
        </div>
        <p itemprop="keywords" class="tags"><?php _e('タブ: '); ?><?php $this->tags(', ', true, 'none'); ?></p>
    </article>

    <?php $this->need('comments.php'); ?>

    <ul class="post-near">
        <li>前の記事: <?php $this->thePrev('%s', 'そうではない。'); ?></li>
        <li>次の記事: <?php $this->theNext('%s', 'そうではない。'); ?></li>
    </ul>
</div><!-- end #main-->

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
