<?php

use LinkGuard\Support\Escaper;
use LinkGuard\Support\Translator;

$e = static fn (mixed $value): string => Escaper::html($value);
?>
<section class="analysis-shell" data-analysis-progress data-result-url="/cases/<?= $e($report['case_id']) ?>">
    <div class="eyebrow"><?= $e(tr('CASE', 'الحالة')) ?> <?= $e($report['case_id']) ?> / <?= $e(tr('ANALYSIS SEQUENCE', 'تسلسل التحليل')) ?></div>
    <div class="analysis-heading">
        <div>
            <h1><?= $e(tr('Reviewing observed indicators', 'مراجعة المؤشرات المرصودة')) ?></h1>
            <p><?= $e(tr('Each specialist returns structured evidence. Internal reasoning is never displayed.', 'يعيد كل وكيل أدلة منظمة دون عرض الاستدلال الداخلي.')) ?></p>
        </div>
        <div class="spinner" aria-hidden="true"></div>
    </div>
    <div class="agent-list" role="status" aria-live="polite">
        <?php
        $steps = [
            ['URL Structure Agent', tr('Checking URL structure', 'فحص بنية الرابط')],
            ['Reputation Agent', tr('Checking reputation sources', 'فحص مصادر السمعة')],
            ['Phishing Pattern Agent', tr('Detecting phishing patterns', 'اكتشاف أنماط التصيد')],
            ['Sandbox Content Agent', tr('Inspecting bounded HTML metadata safely', 'فحص بيانات HTML المحدودة بأمان')],
            ['Risk Scoring Agent', tr('Calculating deterministic risk score', 'حساب درجة المخاطر بالقواعد الحتمية')],
            ['Explanation Agent', tr('Preparing investigation report', 'إعداد تقرير التحقيق')],
        ];
        foreach ($steps as $index => [$agent, $status]):
        ?>
        <div class="agent-step" data-step="<?= $index ?>">
            <span class="step-marker"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <div><strong><?= $e(Translator::agent($agent)) ?></strong><span><?= $e($status) ?></span></div>
            <span class="step-status"><?= $e(tr('Queued', 'في الانتظار')) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <noscript><p><a class="button button-primary" href="/cases/<?= $e($report['case_id']) ?>"><?= $e(tr('Open completed report', 'فتح التقرير المكتمل')) ?></a></p></noscript>
</section>
