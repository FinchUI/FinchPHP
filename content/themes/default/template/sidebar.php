<aside class="sidebar">
    <section class="sidebar-block">
        <h3>关于本站</h3>
        <p class="muted"><?= fp_escape(site_subtitle() !== '' ? site_subtitle() : site_name() . ' 博客') ?></p>
    </section>
    <section class="sidebar-block">
        <h3>站内搜索</h3>
        <form method="get" action="/search" class="search-form">
            <input type="text" name="q" value="<?= fp_escape((string) (query_data()['keyword'] ?? '')) ?>" placeholder="搜索文章..." aria-label="搜索">
            <button type="submit">搜索</button>
        </form>
    </section>
    <?php
    // 获取分类列表
    $categories = sidebar_categories(10);
    if ($categories !== []) :
    ?>
    <section class="sidebar-block">
        <h3>分类</h3>
        <ul class="cat-list">
            <?php foreach ($categories as $cat) : ?>
                <li><a href="/category/<?= rawurlencode((string) $cat['slug']) ?>"><?= fp_escape((string) $cat['name']) ?></a>
                    <?php if ($cat['post_count'] > 0) : ?>
                        <span class="count"><?= $cat['post_count'] ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</aside>
