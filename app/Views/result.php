<?php

use LinkGuard\Support\Escaper;
use LinkGuard\Support\Translator;

$e = static fn (mixed $value): string => Escaper::html($value);
$levelClass = strtolower(str_replace([' Risk', 'Low Observed'], ['', 'low'], $report['risk']['level']));
$coverageStatus = (string) ($report['coverage']['status'] ?? 'Limited');
$summary = $report['explanation']['summary'];
if (locale() === 'ar') {
    $summary = match ($report['risk']['level']) {
        'Critical Risk' => 'تم رصد عدة مؤشرات تحذير قوية. تجنب التفاعل مع هذا الرابط.',
        'High Risk' => 'تم رصد مؤشرات تحذير مهمة. يوصى بشدة بالتحقق من الرابط عبر مصدر مستقل.',
        'Moderate Risk' => 'تم رصد بعض مؤشرات التحذير. تحقق من الوجهة والمرسل قبل المتابعة.',
        default => $coverageStatus === 'Limited'
            ? 'لم تظهر مؤشرات بنيوية قوية، لكن الأدلة المتاحة غير مكتملة. لا تعتبر هذه النتيجة إثباتًا للأمان.'
            : 'لم تظهر مؤشرات تحذير كبيرة ضمن الفحوص المكتملة، لكن النتيجة تظل تقييمًا للمخاطر وليست ضمانًا للأمان.',
    };
}
?>
<section class="report-header">
    <div>
        <div class="eyebrow"><?= $e(tr('INVESTIGATION REPORT', 'تقرير التحقيق')) ?> / <?= $e($report['case_id']) ?></div>
        <h1><?= $e(tr('Link risk assessment', 'تقييم مخاطر الرابط')) ?></h1>
        <p class="mono url-display"><?= $e($report['submitted_url']) ?></p>
    </div>
    <div class="risk-seal risk-<?= $e($levelClass) ?>">
        <span><?= $e(tr('OBSERVED RISK', 'المخاطر المرصودة')) ?></span>
        <strong><?= $e($report['risk']['score']) ?><small>/100</small></strong>
        <b><?= $e(Translator::riskLevel($report['risk']['level'])) ?></b>
    </div>
</section>

<section class="report-grid">
    <div class="report-main">
        <article class="paper summary-card">
            <div class="paper-heading">
                <div><span class="section-number"><?= $e(tr('CASE SUMMARY', 'ملخص الحالة')) ?></span><h2><?= $e($summary) ?></h2></div>
            </div>
            <dl class="case-facts">
                <div><dt><?= $e(tr('Domain', 'النطاق')) ?></dt><dd class="mono"><?= $e($report['url']['host']) ?></dd></div>
                <div><dt><?= $e(tr('Protocol', 'البروتوكول')) ?></dt><dd><?= $e(strtoupper($report['url']['scheme'])) ?></dd></div>
                <div><dt><?= $e(tr('Checked', 'وقت الفحص')) ?></dt><dd class="mono"><?= $e(date('Y-m-d · H:i T', strtotime($report['created_at']))) ?></dd></div>
                <div><dt><?= $e(tr('Data source', 'مصدر البيانات')) ?></dt><dd><?= $e(Translator::reportValue($report['reputation']['source'])) ?><?= $report['reputation']['mock'] ? ' · ' . $e(tr('Mock', 'تجريبي')) : '' ?></dd></div>
                <div><dt><?= $e(tr('Assessment coverage', 'تغطية التقييم')) ?></dt><dd><?= $e(Translator::status($coverageStatus)) ?> · <?= $e($report['coverage']['coverage_percent'] ?? 50) ?>%</dd></div>
                <div><dt><?= $e(tr('Page title', 'عنوان الصفحة')) ?></dt><dd><?= $e(Translator::reportValue($report['coverage']['page_title'] ?? 'Not inspected')) ?></dd></div>
            </dl>
        </article>

        <article class="paper">
            <div class="paper-heading">
                <div><span class="section-number"><?= $e(tr('02 / EVIDENCE', '02 / الأدلة')) ?></span><h2><?= $e(tr('Observed indicators', 'المؤشرات المرصودة')) ?></h2></div>
                <span class="evidence-tag"><?= count($report['findings']) ?> <?= $e(tr('FOUND', 'مؤشر')) ?></span>
            </div>
            <?php if ($report['findings'] === []): ?>
                <div class="no-findings"><strong><?= $e(tr('No major warning indicators found', 'لم يتم العثور على مؤشرات تحذير كبيرة')) ?></strong><p><?= $e(tr('This is not proof that the link is safe.', 'هذه النتيجة لا تثبت أن الرابط آمن.')) ?></p></div>
            <?php else: ?>
                <div class="finding-list">
                <?php foreach ($report['findings'] as $finding): ?>
                    <div class="finding">
                        <span class="severity severity-<?= $e($finding['severity']) ?>"><?= $e(locale() === 'ar' ? match ($finding['severity']) { 'low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'critical' => 'حرج', default => $finding['severity'] } : $finding['severity']) ?></span>
                        <div><h3><?= $e(Translator::findingTitle($finding)) ?></h3><p><?= $e(Translator::findingExplanation($finding)) ?></p></div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="paper">
            <div class="paper-heading"><div><span class="section-number"><?= $e(tr('03 / RESPONSE', '03 / الاستجابة')) ?></span><h2><?= $e(tr('Recommended next steps', 'الخطوات الموصى بها')) ?></h2></div></div>
            <ol class="recommendations">
                <?php foreach ($report['explanation']['recommendations'] as $recommendation): ?>
                    <li><?= $e(Translator::recommendation($recommendation)) ?></li>
                <?php endforeach; ?>
            </ol>
        </article>
    </div>

    <aside class="report-side">
        <section class="panel">
            <span class="section-number"><?= $e(tr('SCORE LEDGER', 'سجل النقاط')) ?></span>
            <h2><?= $e(tr('How the score was built', 'كيف تم حساب النتيجة؟')) ?></h2>
            <?php if ($report['risk']['contributions'] === []): ?>
                <p class="muted"><?= $e(tr('No weighted rules contributed points.', 'لم تضف أي قاعدة موزونة نقاطًا.')) ?></p>
            <?php else: ?>
                <div class="score-ledger">
                    <?php foreach ($report['risk']['contributions'] as $item): ?>
                        <div><span><?= $e(Translator::findingTitle(['code' => $item['code'], 'title' => $item['label']])) ?></span><strong>+<?= $e($item['points']) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="score-total"><span><?= $e(tr('Capped total', 'المجموع النهائي')) ?></span><strong><?= $e($report['risk']['score']) ?>/100</strong></div>
        </section>

        <section class="panel">
            <span class="section-number"><?= $e(tr('AGENT LOG', 'سجل الوكلاء')) ?></span>
            <h2><?= $e(tr('Completed checks', 'الفحوص المكتملة')) ?></h2>
            <ul class="agent-summary">
                <?php foreach ($report['agents'] as $agent): ?>
                    <li><span class="status-dot"></span><div><strong><?= $e(Translator::agent($agent['agent'])) ?></strong><small><?= $e(Translator::status($agent['status'])) ?> · <?= count($agent['findings']) ?> <?= $e(tr('finding(s)', 'مؤشر')) ?></small></div></li>
                <?php endforeach; ?>
                <li><span class="status-dot"></span><div><strong><?= $e(Translator::agent('Risk Scoring Agent')) ?></strong><small><?= $e(tr('complete · deterministic rules', 'مكتمل · قواعد حتمية')) ?></small></div></li>
                <li><span class="status-dot"></span><div><strong><?= $e(Translator::agent('Explanation Agent')) ?></strong><small><?= $e(tr('complete · score unchanged', 'مكتمل · دون تغيير النتيجة')) ?></small></div></li>
            </ul>
        </section>

        <section class="panel limitation-panel">
            <span class="section-number"><?= $e(tr('COVERAGE & LIMITATIONS', 'التغطية والقيود')) ?></span>
            <h2><?= $e(locale() === 'ar' ? 'تقييم ' . Translator::status($coverageStatus) : Translator::status($coverageStatus) . ' assessment') ?></h2>
            <progress class="coverage-meter" max="100" value="<?= (int) ($report['coverage']['coverage_percent'] ?? 50) ?>"><?= (int) ($report['coverage']['coverage_percent'] ?? 50) ?>%</progress>
            <p><strong><?= $e(tr('Live reputation:', 'السمعة الحية:')) ?></strong> <?= $e(Translator::reportValue($report['coverage']['reputation'] ?? 'Unavailable')) ?></p>
            <p><strong><?= $e(tr('Page title/content:', 'عنوان ومحتوى الصفحة:')) ?></strong> <?= $e(Translator::reportValue($report['coverage']['page_content'] ?? 'Not inspected')) ?></p>
            <?php foreach ($report['explanation']['limitations'] as $limitation): ?><p><?= $e(Translator::limitation($limitation)) ?></p><?php endforeach; ?>
            <p><?= $e(tr(
                $report['disclaimer'],
                'هذا التقرير تقييم للمخاطر مبني على المؤشرات المتاحة، وليس ضمانًا بأن الرابط آمن أو ضار.'
            )) ?></p>
        </section>
    </aside>
</section>

<div class="report-actions">
    <a class="button button-primary" href="/cases/<?= $e($report['case_id']) ?>/pdf"><?= $e(tr('Download PDF', 'تحميل PDF')) ?></a>
    <a class="button button-secondary" href="/"><?= $e(tr('Analyze another link', 'تحليل رابط آخر')) ?></a>
</div>
