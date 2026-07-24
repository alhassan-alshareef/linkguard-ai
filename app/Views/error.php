<?php use LinkGuard\Support\Escaper; ?>
<section class="paper empty-state">
    <span class="section-number"><?= Escaper::html(tr('NOTICE', 'تنبيه')) ?></span>
    <h1><?= Escaper::html($title) ?></h1>
    <p><?= Escaper::html($message) ?></p>
    <a class="button button-primary" href="/"><?= Escaper::html(tr('Start a new analysis', 'بدء تحليل جديد')) ?></a>
</section>
