<?= get_header() ?>
<?php $query = query_data(); ?>
<h2><?= fp_escape((string) ($query['title'] ?? site_name())) ?></h2>

<?php if (list_posts() === []) : ?>
    <p class="muted">暂无内容。</p>
<?php else : ?>
    <div class="post-list">
        <?php foreach (list_posts() as $item) : ?>
            <article class="post-item">
                <h2><a href="<?= fp_escape(permalink($item)) ?>"><?= fp_escape((string) ($item['title'] ?? '')) ?></a></h2>
                <p class="meta">
                    <span><?= fp_escape((string) (($item['author']['display_name'] ?? $item['author']['username'] ?? '匿名'))) ?></span>
                    <span> · </span>
                    <span><?= fp_escape((string) ($item['published_at'] ?? '')) ?></span>
                </p>
                <p><?= fp_escape((string) ($item['excerpt'] ?? '')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
    <?= pagination() ?>
<?php endif; ?>
<?= get_footer() ?>
