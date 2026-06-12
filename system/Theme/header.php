<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= fp_escape(title()) ?> - <?= fp_escape(site_name()) ?></title>
    <style>
        :root { --line: #d0d7de; --text: #24292f; --muted: #57606a; --bg: #f6f8fa; --link: #0969da; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--text); background: var(--bg); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .wrap { width: min(1040px, calc(100% - 32px)); margin: 0 auto; }
        .site-header { background: #fff; border-bottom: 1px solid var(--line); }
        .site-header .inner { display: flex; align-items: center; justify-content: space-between; min-height: 64px; gap: 12px; }
        .site-header h1 { margin: 0; font-size: 20px; }
        .site-header a { color: inherit; text-decoration: none; }
        .site-nav a { color: var(--muted); margin-left: 12px; text-decoration: none; }
        .site-nav a:hover { color: var(--link); }
        .layout { display: grid; gap: 20px; padding: 24px 0 40px; grid-template-columns: minmax(0, 1fr) 280px; }
        .content, .sidebar { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 18px; }
        .post-list { display: grid; gap: 14px; }
        .post-item { border: 1px solid var(--line); border-radius: 8px; padding: 14px; }
        .post-item h2 { margin: 0 0 8px; font-size: 19px; }
        .meta { color: var(--muted); font-size: 13px; }
        .pagination { margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .pagination a, .pagination strong { min-width: 30px; text-align: center; padding: 4px 8px; border: 1px solid var(--line); border-radius: 6px; text-decoration: none; }
        .pagination strong { background: #eaf2ff; border-color: #a6c8ff; }
        .single h1 { margin: 0 0 10px; }
        .single .post-content { line-height: 1.8; }
        .site-footer { border-top: 1px solid var(--line); background: #fff; }
        .site-footer .inner { min-height: 58px; display: flex; align-items: center; color: var(--muted); font-size: 14px; }
        a { color: var(--link); }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .site-nav a:first-child { margin-left: 0; }
        }
    </style>
    <?= head() ?>
</head>
<body>
<header class="site-header">
    <div class="wrap inner">
        <h1><a href="/"><?= fp_escape(site_name()) ?></a></h1>
        <nav class="site-nav">
            <a href="/">首页</a>
            <a href="/search">搜索</a>
            <a href="/admin/login">后台</a>
        </nav>
    </div>
</header>
<main class="wrap layout">
    <section class="content">
