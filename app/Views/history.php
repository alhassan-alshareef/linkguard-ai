<?php

use LinkGuard\Support\Csrf;
use LinkGuard\Support\Escaper;
use LinkGuard\Support\Translator;

$e = static fn (mixed $value): string => Escaper::html($value);
?>
<section class="page-heading">
    <div class="eyebrow"><?= $e(tr('LOCAL CASE ARCHIVE', 'أرشيف الحالات المحلي')) ?></div>
    <h1><?= $e(tr('Analysis history', 'سجل التحليلات')) ?></h1>
    <p><?= $e(tr('Only analysis evidence is stored locally. Passwords, full page content, and API secrets are never recorded.', 'يتم حفظ أدلة التحليل فقط محليًا، ولا تُحفظ كلمات المرور أو محتوى الصفحة الكامل أو مفاتيح API.')) ?></p>
</section>

<section class="paper">
    <form class="history-search" method="get" action="/history">
        <label for="q"><?= $e(tr('Search cases', 'البحث في الحالات')) ?></label>
        <div>
            <input id="q" name="q" type="search" maxlength="100" value="<?= $e($query) ?>" placeholder="<?= $e(tr('URL, domain, level, or case ID', 'الرابط أو النطاق أو المستوى أو رقم الحالة')) ?>">
            <button class="button button-primary" type="submit"><?= $e(tr('Search', 'بحث')) ?></button>
            <?php if ($query !== ''): ?><a class="button-link" href="/history"><?= $e(tr('Clear', 'مسح')) ?></a><?php endif; ?>
        </div>
    </form>

    <?php if ($records === []): ?>
        <div class="empty-state">
            <span class="section-number"><?= $e(tr('NO RECORDS', 'لا توجد سجلات')) ?></span>
            <h2><?= $e($query === '' ? tr('The case archive is empty.', 'أرشيف الحالات فارغ.') : tr('No matching cases were found.', 'لم يتم العثور على حالات مطابقة.')) ?></h2>
            <p><?= $e(tr('New analyses will appear here automatically.', 'ستظهر التحليلات الجديدة هنا تلقائيًا.')) ?></p>
            <a class="button button-primary" href="/"><?= $e(tr('Inspect a link', 'فحص رابط')) ?></a>
        </div>
    <?php else: ?>
        <div class="history-table-wrap">
            <table class="history-table">
                <thead><tr><th><?= $e(tr('Case', 'الحالة')) ?></th><th><?= $e(tr('Destination', 'الوجهة')) ?></th><th><?= $e(tr('Risk', 'المخاطر')) ?></th><th><?= $e(tr('Checked', 'وقت الفحص')) ?></th><th><span class="sr-only"><?= $e(tr('Actions', 'الإجراءات')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><a class="case-link mono" href="/cases/<?= $e($record['case_id']) ?>"><?= $e($record['case_id']) ?></a></td>
                        <td><strong class="mono"><?= $e($record['host']) ?></strong><small class="truncated mono"><?= $e($record['submitted_url']) ?></small></td>
                        <td><span class="risk-pill"><?= $e($record['risk_score']) ?> · <?= $e(Translator::riskLevel($record['risk_level'])) ?></span></td>
                        <td class="mono"><?= $e(date('Y-m-d H:i', strtotime($record['created_at']))) ?></td>
                        <td>
                            <form method="post" action="/history/<?= $e($record['case_id']) ?>/delete" data-confirm-delete>
                                <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                                <button class="delete-button" type="submit"><?= $e(tr('Delete', 'حذف')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
