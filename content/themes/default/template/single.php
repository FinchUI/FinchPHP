<?= get_header() ?>
<article class="single">
    <h1><?= fp_escape(title()) ?></h1>
    <p class="meta">
        <span><?= fp_escape(author()) ?></span>
        <span> · </span>
        <span><?= fp_escape(post_date()) ?></span>
    </p>
    <?php if (categories() !== '') : ?>
        <p class="meta">分类：<?= categories() ?></p>
    <?php endif; ?>
    <?php if (tags() !== '') : ?>
        <p class="meta">标签：<?= tags() ?></p>
    <?php endif; ?>
    <div class="post-content"><?= content() ?></div>
</article>
<?= get_footer() ?>
