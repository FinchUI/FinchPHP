<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= fp_escape(title()) ?> - <?= fp_escape(site_name()) ?></title>
    <link rel="stylesheet" href="<?= fp_escape(fp_app()->asset->themeAsset('css/theme.css')) ?>">
    <?= head() ?>
</head>
<body>
<header class="site-header">
    <div class="wrap site-header-inner">
        <div class="site-brand">
            <a href="/"><?= fp_escape(site_name()) ?></a>
            <?php if (site_subtitle() !== '') : ?>
                <span class="site-desc"><?= fp_escape(site_subtitle()) ?></span>
            <?php endif; ?>
        </div>
        <nav class="site-nav">
            <a href="/">首页</a>
            <a href="/search">搜索</a>
            <a href="/admin/login">管理</a>
        </nav>
    </div>
</header>
<main class="wrap layout">
    <section class="content">
