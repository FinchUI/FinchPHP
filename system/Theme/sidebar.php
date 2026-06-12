<aside class="sidebar">
    <h3 style="margin-top:0">站点信息</h3>
    <p style="color:#57606a;line-height:1.7;margin:0 0 12px">
        <?= fp_escape(site_subtitle() !== '' ? site_subtitle() : 'Finch PHP 默认主题') ?>
    </p>
    <form method="get" action="/search" style="display:grid;gap:8px">
        <label for="q" style="font-size:13px;color:#57606a">站内搜索</label>
        <input id="q" name="q" value="<?= fp_escape((string) (query_data()['keyword'] ?? '')) ?>" style="min-height:36px;border:1px solid #d0d7de;border-radius:6px;padding:6px 8px">
        <button type="submit" style="min-height:36px;border:0;border-radius:6px;background:#0969da;color:#fff;font-weight:600">搜索</button>
    </form>
</aside>
