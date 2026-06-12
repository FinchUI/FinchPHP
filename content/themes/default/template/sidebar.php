<aside class="sidebar">
    <h3>站点信息</h3>
    <p class="muted"><?= fp_escape(site_subtitle() !== '' ? site_subtitle() : 'Finch PHP 默认主题') ?></p>
    <form method="get" action="/search" class="search-form">
        <label for="q">站内搜索</label>
        <input id="q" name="q" value="<?= fp_escape((string) (query_data()['keyword'] ?? '')) ?>">
        <button type="submit">搜索</button>
    </form>
</aside>
