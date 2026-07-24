<?php

use LinkGuard\Support\Escaper;

$e = static fn (mixed $value): string => Escaper::html($value);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$languageQuery = $_GET;
$languageQuery['lang'] = locale() === 'ar' ? 'en' : 'ar';
$languageUrl = $currentPath . '?' . http_build_query($languageQuery);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="<?= $e(locale()) ?>" dir="<?= locale() === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $e(tr('LinkGuard AI inspects suspicious URL indicators safely.', 'يفحص LinkGuard AI مؤشرات الروابط المشبوهة بأمان.')) ?>">
    <title><?= $e($title ?? 'LinkGuard AI') ?> — LinkGuard AI</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="/" aria-label="<?= $e(tr('LinkGuard AI home', 'الصفحة الرئيسية لـ LinkGuard AI')) ?>">
        <span class="brand-mark" aria-hidden="true">LG</span>
        <span><strong>LinkGuard</strong><small><?= $e(tr('AI · Link Investigation', 'تحقيق الروابط · AI')) ?></small></span>
    </a>
    <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav"><?= $e(tr('Menu', 'القائمة')) ?></button>
    <nav id="site-nav" class="site-nav" aria-label="<?= $e(tr('Primary navigation', 'التنقل الرئيسي')) ?>">
        <a href="/"<?= $currentPath === '/' ? ' aria-current="page"' : '' ?>><?= $e(tr('Inspect', 'فحص رابط')) ?></a>
        <a href="/history"<?= $currentPath === '/history' ? ' aria-current="page"' : '' ?>><?= $e(tr('Case history', 'سجل الحالات')) ?></a>
        <a href="/about"<?= $currentPath === '/about' ? ' aria-current="page"' : '' ?>><?= $e(tr('How it works', 'كيف يعمل')) ?></a>
        <a class="language-switch" href="<?= $e($languageUrl) ?>"
           lang="<?= locale() === 'ar' ? 'en' : 'ar' ?>"><?= locale() === 'ar' ? 'English' : 'العربية' ?></a>
    </nav>
</header>
<main id="main-content">
    <?php if ($flash): ?>
        <div class="flash" role="status"><?= $e($flash) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>
<footer class="site-footer">
    <span><?= $e(tr('LinkGuard AI · Local investigation prototype', 'LinkGuard AI · نموذج تحقيق محلي')) ?></span>
    <span><?= $e(tr('Risk assessment, not a safety guarantee.', 'تقييم للمخاطر وليس ضمانًا للأمان.')) ?></span>
</footer>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
