<?= get_header() ?>
<?php $post = current_post(); ?>
<article class="single">
    <header class="single-header">
        <h1><?= fp_escape(title()) ?></h1>
        <div class="single-meta">
            <span class="post-date"><?= fp_escape(post_date('Y-m-d H:i')) ?></span>
            <?php if (author() !== '') : ?>
                <span class="meta-sep">&middot;</span>
                <span class="post-author"><?= fp_escape(author()) ?></span>
            <?php endif; ?>
            <?php if (!empty($post['categories'])) : ?>
                <span class="meta-sep">&middot;</span>
                <span class="post-categories">
                    <?php foreach ($post['categories'] as $cat) : ?>
                        <a href="/category/<?= rawurlencode((string) ($cat['slug'] ?? '')) ?>"><?= fp_escape((string) ($cat['name'] ?? '')) ?></a>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>
    </header>
    <div class="post-content"><?= content() ?></div>
    <?php if (!empty($post['tags'])) : ?>
        <footer class="single-tags">
            <?php foreach ($post['tags'] as $tag) : ?>
                <a href="/tag/<?= rawurlencode((string) ($tag['slug'] ?? '')) ?>" class="tag">#<?= fp_escape((string) ($tag['name'] ?? '')) ?></a>
            <?php endforeach; ?>
        </footer>
    <?php endif; ?>
</article>
<?= get_footer() ?>
