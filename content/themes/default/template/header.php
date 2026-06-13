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
            <?php
            $navItems = fp_app()->hooks->filter('fp_nav_items', [], ['group' => 'navbar']);
            if (is_array($navItems) && $navItems !== []) :
                foreach ($navItems as $item) :
                    $hasChildren = !empty($item['children']);
                    if ($hasChildren) :
            ?>
            <div class="site-nav-dropdown">
                <a href="<?= fp_escape((string) $item['url']) ?>" class="site-nav-link<?= $item['icon'] !== '' ? ' ' . fp_escape((string) $item['icon']) : '' ?>"<?= $item['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= fp_escape((string) $item['title']) ?> ▾</a>
                <div class="site-nav-sub">
                    <?php foreach ($item['children'] as $child) : ?>
                    <a href="<?= fp_escape((string) $child['url']) ?>"<?= $child['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= fp_escape((string) $child['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
                    <?php else : ?>
            <a href="<?= fp_escape((string) $item['url']) ?>" class="site-nav-link<?= $item['icon'] !== '' ? ' ' . fp_escape((string) $item['icon']) : '' ?>"<?= $item['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= fp_escape((string) $item['title']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else : ?>
            <a href="/">首页</a>
            <a href="/search">搜索</a>
            <a href="/admin/login">管理</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="wrap layout">
    <section class="content">
