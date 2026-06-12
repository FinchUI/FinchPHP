<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= fp_escape(title()) ?> - <?= fp_escape(site_name()) ?></title>
    <link rel="stylesheet" href="<?= fp_escape(fp_app()->asset->themeAsset('css/theme.css')) ?>">
    <?= head() ?>
</head>
<body data-theme-source="content-default">
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
