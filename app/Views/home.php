<?php

use LinkGuard\Support\Csrf;
use LinkGuard\Support\Escaper;

$e = static fn (mixed $value): string => Escaper::html($value);
?>
<section class="hero">
    <div class="eyebrow"><?= $e(tr('URL INVESTIGATION DESK / LOCAL PROTOTYPE', 'مكتب فحص الروابط / نموذج محلي')) ?></div>
    <div class="hero-grid">
        <div>
            <h1><?= $e(tr('Inspect a suspicious link before you trust it.', 'افحص الرابط المشبوه قبل أن تثق به.')) ?></h1>
            <p class="lede"><?= $e(tr(
                'LinkGuard examines the address, checks configured reputation data, and—when enabled—inspects bounded page metadata inside an isolated service without executing the page.',
                'يفحص LinkGuard بنية الرابط وبيانات السمعة، وعند تفعيل الخدمة يفحص بيانات محدودة من الصفحة داخل بيئة معزولة دون تنفيذها.'
            )) ?></p>
        </div>
        <div class="case-index" aria-label="<?= $e(tr('Service status', 'حالة الخدمة')) ?>">
            <span><?= $e(tr('DESK STATUS', 'حالة النظام')) ?></span><strong><?= $e(tr('READY', 'جاهز')) ?></strong>
            <span><?= $e(tr('REPUTATION', 'السمعة')) ?></span><strong><?= config('app.reputation_mode') === 'mock' ? $e(tr('MOCK DATA', 'بيانات تجريبية')) : $e(strtoupper((string) config('app.reputation_mode'))) ?></strong>
            <span><?= $e(tr('PAGE INSPECTION', 'فحص الصفحة')) ?></span><strong><?= config('app.content_sandbox_mode') === 'enabled' ? $e(tr('SANDBOXED', 'معزول')) : $e(tr('DISABLED', 'متوقف')) ?></strong>
        </div>
    </div>
</section>

<section class="paper intake">
    <div class="paper-heading">
        <div>
            <span class="section-number"><?= $e(tr('01 / NEW CASE', '01 / حالة جديدة')) ?></span>
            <h2><?= $e(tr('Submit an address for analysis', 'أرسل رابطًا للتحليل')) ?></h2>
        </div>
        <span class="evidence-tag"><?= $e(tr('HTTP + HTTPS ONLY', 'HTTP وHTTPS فقط')) ?></span>
    </div>
    <form method="post" action="/analyze" data-analysis-form novalidate>
        <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
        <label for="url"><?= $e(tr('Suspicious URL', 'الرابط المشبوه')) ?></label>
        <div class="url-entry">
            <input id="url" name="url" type="url" inputmode="url" maxlength="2048"
                   placeholder="https://example.com/verify-account"
                   value="<?= $e($oldUrl ?? '') ?>"
                   aria-describedby="url-help<?= isset($error) ? ' url-error' : '' ?>"
                   <?= isset($error) ? 'aria-invalid="true"' : '' ?> required>
            <button class="button button-primary" type="submit"><?= $e(tr('Analyze link', 'تحليل الرابط')) ?> <span aria-hidden="true">→</span></button>
        </div>
        <p id="url-help" class="field-help"><?= $e(tr(
            'Only bounded HTML metadata is fetched. Scripts, redirects, and downloads are blocked.',
            'يتم جلب بيانات HTML محدودة فقط، مع حظر السكربتات والتحويلات والتنزيلات.'
        )) ?></p>
        <?php if (isset($error)): ?>
            <p id="url-error" class="field-error" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>
    </form>
</section>

<section class="briefing">
    <div>
        <span class="section-number"><?= $e(tr('FIELD NOTES', 'ملاحظات الفحص')) ?></span>
        <h2><?= $e(tr('What the inspection looks for', 'ما الذي يبحث عنه الفحص؟')) ?></h2>
    </div>
    <div class="note-grid">
        <article><span>01</span><h3><?= $e(tr('Disguised destinations', 'وجهات مخفية')) ?></h3><p><?= $e(tr('IP hosts, shortened URLs, encoded domains, deep subdomains, and unusual ports.', 'عناوين IP والروابط المختصرة والنطاقات المشفرة والنطاقات الفرعية والمنافذ غير المعتادة.')) ?></p></article>
        <article><span>02</span><h3><?= $e(tr('Phishing language', 'لغة التصيد')) ?></h3><p><?= $e(tr('Login, verification, payment, prize, and urgency terms in sensitive URL positions.', 'عبارات الدخول والتحقق والدفع والجوائز والاستعجال داخل الرابط.')) ?></p></article>
        <article><span>03</span><h3><?= $e(tr('Reputation evidence', 'أدلة السمعة')) ?></h3><p><?= $e(tr('External provider results when configured, or clearly labeled local mock data.', 'نتائج مزود خارجي عند ربطه، أو بيانات محلية تجريبية موضحة بوضوح.')) ?></p></article>
        <article><span>04</span><h3><?= $e(tr('Page metadata', 'بيانات الصفحة')) ?></h3><p><?= $e(tr('Title, forms, password fields, redirects, and phishing wording in an isolated service.', 'العنوان والنماذج وحقول كلمات المرور والتحويلات وعبارات التصيد داخل خدمة معزولة.')) ?></p></article>
    </div>
</section>

<aside class="disclaimer">
    <strong><?= $e(tr('Assessment boundary', 'حدود التقييم')) ?></strong>
    <p><?= $e(tr(
        'LinkGuard reports observed risk indicators. It cannot guarantee that any link is completely safe or harmful.',
        'يعرض LinkGuard مؤشرات المخاطر المرصودة، ولا يضمن أن أي رابط آمن أو ضار بشكل قاطع.'
    )) ?></p>
</aside>
