<?php

use LinkGuard\Support\Translator;

$rtl = locale() === 'ar';
$arabic = $rtl ? new \ArPHP\I18N\Arabic() : null;
$t = static function (mixed $value) use ($e, $rtl, $arabic): string {
    $text = (string) $value;
    if ($rtl && preg_match('/\p{Arabic}/u', $text)) {
        $text = $arabic->utf8Glyphs($text, 140, false, true);
    }
    return $e($text);
};
$coverageStatus = (string) ($report['coverage']['status'] ?? 'Limited');
$summary = (string) ($report['explanation']['summary'] ?? '');
if ($rtl) {
    $summary = match ($report['risk']['level']) {
        'Critical Risk' => 'تم رصد عدة مؤشرات تحذير قوية. تجنب التفاعل مع هذا الرابط.',
        'High Risk' => 'تم رصد مؤشرات تحذير مهمة. يوصى بشدة بالتحقق من الرابط عبر مصدر مستقل.',
        'Moderate Risk' => 'تم رصد بعض مؤشرات التحذير. تحقق من الوجهة والمرسل قبل المتابعة.',
        default => $coverageStatus === 'Limited'
            ? 'الأدلة المتاحة غير مكتملة. لا تعتبر هذه النتيجة إثباتًا للأمان.'
            : 'لم تظهر مؤشرات تحذير كبيرة، لكن النتيجة ليست ضمانًا للأمان.',
    };
}
?>
<!doctype html>
<html lang="<?= $e(locale()) ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 38px; }
    body { font-family: "DejaVu Sans", sans-serif; color: #24282D; font-size: 9px; line-height: 1.48; }
    .top { width: 100%; direction: ltr; border-collapse: collapse; border-bottom: 3px solid #17191C; margin-bottom: 12px; }
    .top td { border: 0; padding: 0 0 10px; vertical-align: top; }
    .brand-block { width: 65%; text-align: left; }
    .brand { font-size: 20px; font-weight: bold; color: #17191C; direction: ltr; }
    .brand span { color: #8F3434; }
    .case { width: 35%; text-align: right; direction: ltr; font-family: "DejaVu Sans Mono", monospace; font-size: 9px; }
    h1 { font-size: 17px; margin: 0 0 3px; }
    h2 { font-size: 10px; letter-spacing: .5px; border-bottom: 1px solid #C8BDAA; padding-bottom: 3px; margin: 13px 0 6px; }
    .risk { background: #E7E0D2; border-left: 5px solid #8F3434; padding: 9px 13px; margin: 9px 0; }
    .risk p { margin: 4px 0 0; }
    .score { font-size: 24px; font-weight: bold; direction: ltr; display: inline-block; }
    .level { font-size: 11px; color: #8F3434; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 4px 7px; border-bottom: 1px solid #ded7ca; vertical-align: top; }
    td:first-child { width: 27%; font-weight: bold; color: #555; }
    .url, .technical { direction: ltr; text-align: left; font-family: "DejaVu Sans Mono", monospace; word-break: break-all; }
    .url { background: #f2eee6; padding: 6px; }
    .finding { page-break-inside: avoid; margin: 5px 0; padding: 6px 8px; border: 1px solid #C8BDAA; }
    .finding strong { display: block; }
    .points { float: right; direction: ltr; color: #8F3434; font-weight: bold; }
    ul, ol { padding-left: 18px; margin: 4px 0; }
    li { margin-bottom: 2px; }
    .notice { page-break-inside: avoid; margin-top: 10px; padding: 7px; border: 1px solid #B4843E; background: #faf7f0; font-size: 8px; }
    .footer { margin-top: 10px; border-top: 1px solid #C8BDAA; padding-top: 5px; font-size: 7px; color: #666; }
    [dir="rtl"] body { direction: rtl; text-align: right; }
    [dir="rtl"] .brand-block { text-align: right; }
    [dir="rtl"] .case { text-align: left; }
    [dir="rtl"] .risk { border-left: 0; border-right: 5px solid #8F3434; }
    [dir="rtl"] .points { float: left; }
    [dir="rtl"] ul, [dir="rtl"] ol { padding-left: 0; padding-right: 18px; }
</style>
</head>
<body>
<table class="top">
    <tr>
        <?php if ($rtl): ?>
            <td class="case"><?= $t('رقم الحالة') ?><br><?= $e($report['case_id']) ?></td>
            <td class="brand-block"><div class="brand">LinkGuard <span>AI</span></div><div><?= $t('تقرير التحقيق الرقمي في الروابط') ?></div></td>
        <?php else: ?>
            <td class="brand-block"><div class="brand">LinkGuard <span>AI</span></div><div>Digital Link Investigation Report</div></td>
            <td class="case">CASE ID<br><?= $e($report['case_id']) ?></td>
        <?php endif; ?>
    </tr>
</table>
<h1><?= $t(tr('Risk assessment', 'تقييم المخاطر')) ?></h1>
<div class="url"><?= $e($report['submitted_url']) ?></div>
<div class="risk">
    <span class="score"><?= $e($report['risk']['score']) ?>/100</span><br>
    <span class="level"><?= $t(Translator::riskLevel($report['risk']['level'])) ?></span>
    <p><?= $t($summary) ?></p>
</div>
<h2><?= $t(tr('CASE DETAILS', 'تفاصيل الحالة')) ?></h2>
<table>
    <tr><td><?= $t(tr('Case ID', 'رقم الحالة')) ?></td><td class="technical"><?= $e($report['case_id']) ?></td></tr>
    <tr><td><?= $t(tr('Normalized domain', 'النطاق الموحّد')) ?></td><td class="technical"><?= $e($report['url']['host']) ?></td></tr>
    <tr><td><?= $t(tr('Protocol', 'البروتوكول')) ?></td><td class="technical"><?= $e(strtoupper($report['url']['scheme'])) ?></td></tr>
    <tr><td><?= $t(tr('Inspection time', 'وقت الفحص')) ?></td><td class="technical"><?= $e(date('Y-m-d H:i:s T', strtotime($report['created_at']))) ?></td></tr>
    <tr><td><?= $t(tr('Reputation source', 'مصدر السمعة')) ?></td><td><?= $t(Translator::reportValue($report['reputation']['source'])) ?><?= $report['reputation']['mock'] ? ' (' . $t(tr('MOCK DATA', 'بيانات تجريبية')) . ')' : '' ?></td></tr>
    <tr><td><?= $t(tr('Assessment coverage', 'تغطية التقييم')) ?></td><td><?= $t(Translator::status($coverageStatus)) ?> (<?= $e($report['coverage']['coverage_percent'] ?? 50) ?>%)</td></tr>
    <tr><td><?= $t(tr('Page title', 'عنوان الصفحة')) ?></td><td><?= $t(Translator::reportValue($report['coverage']['page_title'] ?? 'Not inspected')) ?></td></tr>
    <tr><td><?= $t(tr('Page inspection', 'فحص الصفحة')) ?></td><td><?= $t(Translator::reportValue($report['coverage']['page_content'] ?? 'Not inspected')) ?></td></tr>
</table>
<h2><?= $t(tr('DETECTED INDICATORS AND SCORE CONTRIBUTIONS', 'المؤشرات المكتشفة ومساهمات النتيجة')) ?></h2>
<?php if ($report['findings'] === []): ?>
    <p><?= $t(tr('No weighted warning indicators were found by the available checks.', 'لم تعثر الفحوص المتاحة على مؤشرات تحذير موزونة.')) ?></p>
<?php else: foreach ($report['findings'] as $finding):
    $points = 0;
    foreach ($report['risk']['contributions'] as $contribution) {
        if ($contribution['code'] === $finding['code']) { $points = $contribution['points']; break; }
    }
?>
    <div class="finding"><span class="points">+<?= $e($points) ?></span><strong><?= $t(Translator::findingTitle($finding)) ?></strong><?= $t(Translator::findingExplanation($finding)) ?></div>
<?php endforeach; endif; ?>
<h2><?= $t(tr('RECOMMENDATIONS', 'التوصيات')) ?></h2>
<ol><?php foreach ($report['explanation']['recommendations'] as $recommendation): ?><li><?= $t(Translator::recommendation($recommendation)) ?></li><?php endforeach; ?></ol>
<h2><?= $t(tr('KNOWN LIMITATIONS', 'القيود المعروفة')) ?></h2>
<ul><?php foreach ($report['explanation']['limitations'] as $limitation): ?><li><?= $t(Translator::limitation($limitation)) ?></li><?php endforeach; ?></ul>
<div class="notice"><strong><?= $t(tr('Disclaimer:', 'إخلاء المسؤولية:')) ?></strong> <?= $t(tr(
    $report['disclaimer'],
    'هذا التقرير تقييم للمخاطر مبني على المؤشرات المتاحة، وليس ضمانًا بأن الرابط آمن أو ضار.'
)) ?></div>
<div class="footer"><?= $t(tr(
    'Generated locally by LinkGuard AI · Rule-based score · Submitted URLs are printed as non-clickable text.',
    'تم إنشاء التقرير محليًا بواسطة LinkGuard AI · النتيجة مبنية على قواعد · الروابط المعروضة غير قابلة للنقر.'
)) ?></div>
</body>
</html>
