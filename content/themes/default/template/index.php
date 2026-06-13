<?= get_header() ?>
<?php $query = query_data(); ?>

<?php if (list_posts() === []) : ?>
    <div class="empty-state">
        <h2>暂无内容</h2>
        <p>还没有发布任何文章，敬请期待。</p>
    </div>
<?php else : ?>
    <?php foreach (list_posts() as $item) : ?>
        <article class="post-card">
            <h2 class="post-card-title">
                <a href="<?= fp_escape(permalink($item)) ?>"><?= fp_escape((string) ($item['title'] ?? '')) ?></a>
            </h2>
            <div class="post-card-meta">
                <span class="post-date"><?= fp_escape(post_date('Y-m-d', $item['published_at'] ?? '')) ?></span>
                <?php if (isset($item['author']['display_name'])) : ?>
                    <span class="post-author"><?= fp_escape($item['author']['display_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['categories'])) : ?>
                    <span class="post-categories">
                        <?php foreach (array_slice($item['categories'], 0, 2) as $cat) : ?>
                            <a href="/category/<?= rawurlencode((string) ($cat['slug'] ?? '')) ?>"><?= fp_escape((string) ($cat['name'] ?? '')) ?></a>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
            </div>
            <p class="post-card-excerpt"><?= fp_escape((string) ($item['excerpt'] ?? '')) ?></p>
            <a href="<?= fp_escape(permalink($item)) ?>" class="read-more">阅读全文 &rarr;</a>
        </article>
    <?php endforeach; ?>
    <?= pagination() ?>
<?php endif; ?>
<?= get_footer() ?>
