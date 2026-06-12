<?= get_header() ?>
<article class="single">
    <h1><?= fp_escape(title()) ?></h1>
    <div class="post-content"><?= content() ?></div>
</article>
<?= get_footer() ?>
