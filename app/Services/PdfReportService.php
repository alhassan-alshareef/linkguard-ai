<?php

namespace LinkGuard\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use LinkGuard\Support\Escaper;
use RuntimeException;

final class PdfReportService
{
    public function render(array $report): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Dompdf is not installed. Run Composer install first.');
        }
        $html = $this->html($report);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        return $dompdf->output();
    }

    public function html(array $report): string
    {
        $e = static fn (mixed $value): string => Escaper::html($value);
        ob_start();
        require BASE_PATH . '/app/Views/pdf.php';
        return (string) ob_get_clean();
    }
}
