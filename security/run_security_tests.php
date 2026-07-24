<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/SecurityTestRunner.php';

use LinkGuard\Security\SecurityTestRunner;
use LinkGuard\Support\Escaper;

$dataset = json_decode(
    (string) file_get_contents(__DIR__ . '/golden_dataset.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$baseUrl = 'http://127.0.0.1:8000';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--base-url=')) {
        $baseUrl = rtrim(substr($argument, 11), '/');
    }
}

$report = (new SecurityTestRunner($dataset, $baseUrl))->run();
$reports = __DIR__ . '/reports';
if (!is_dir($reports)) {
    mkdir($reports, 0775, true);
}
file_put_contents(
    $reports . '/latest.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
);

$e = static fn (mixed $value): string => Escaper::html($value);
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>LinkGuard AI Security Audit</title>
<style>
body{font-family:system-ui,sans-serif;margin:36px;color:#24282d}h1{margin-bottom:4px}.summary{display:flex;gap:18px;margin:24px 0}.summary div{border:1px solid #ccc;padding:12px 18px}.passed{color:#47603d}.failed{color:#8f3434}.skipped{color:#8a682f}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}code{font-size:11px}.critical,.high{font-weight:700}</style>
</head>
<body>
<h1>LinkGuard AI Security Audit</h1>
<p>Generated <?= $e($report['generated_at']) ?> · Dataset <?= $e($report['dataset_version']) ?></p>
<div class="summary">
    <div><strong><?= $e($report['overall_score']) ?></strong><br>Overall score</div>
    <div class="passed"><strong><?= $e($report['counts']['passed']) ?></strong><br>Passed</div>
    <div class="failed"><strong><?= $e($report['counts']['failed']) ?></strong><br>Failed</div>
    <div class="skipped"><strong><?= $e($report['counts']['skipped']) ?></strong><br>Skipped</div>
    <div><strong><?= $e($report['counts']['not-applicable']) ?></strong><br>Not applicable</div>
</div>
<table>
<thead><tr><th>ID</th><th>Severity</th><th>Control</th><th>Status</th><th>Evidence</th><th>Remediation</th></tr></thead>
<tbody>
<?php foreach ($report['results'] as $result): ?>
<tr>
    <td><code><?= $e($result['id']) ?></code></td>
    <td class="<?= $e($result['severity']) ?>"><?= $e($result['severity']) ?></td>
    <td><?= $e($result['title']) ?></td>
    <td class="<?= $e($result['status']) ?>"><?= $e($result['status']) ?></td>
    <td><?= $e($result['evidence']) ?></td>
    <td><?= $e($result['remediation']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
<?php
file_put_contents($reports . '/latest.html', (string) ob_get_clean());

echo json_encode([
    'total' => $report['total'],
    'counts' => $report['counts'],
    'overall_score' => $report['overall_score'],
    'critical_failure' => $report['critical_failure'],
    'json_report' => 'security/reports/latest.json',
    'html_report' => 'security/reports/latest.html',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

exit($report['counts']['failed'] > 0 ? 1 : 0);
