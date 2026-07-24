<?php

namespace LinkGuard\Controllers;

use InvalidArgumentException;
use LinkGuard\Models\AnalysisRepository;
use LinkGuard\Services\PdfReportService;
use LinkGuard\Services\ServiceFactory;
use LinkGuard\Support\Csrf;
use LinkGuard\Support\RateLimiter;
use LinkGuard\Support\Translator;
use RuntimeException;
use Throwable;

final class AppController
{
    public function __construct(private readonly AnalysisRepository $repository)
    {
    }

    public function home(): void
    {
        $this->render('home', ['title' => tr('Inspect a suspicious link', 'فحص رابط مشبوه')]);
    }

    public function analyze(): void
    {
        try {
            $this->requireCsrf();
            $limiter = new RateLimiter(
                BASE_PATH . '/storage/rate-limits',
                (int) config('app.rate_limit_max'),
                (int) config('app.rate_limit_window'),
            );
            $client = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!$limiter->attempt('analyze:' . $client)) {
                $this->render('home', [
                    'title' => tr('Inspect a suspicious link', 'فحص رابط مشبوه'),
                    'error' => tr('Too many analysis requests. Please wait a minute and try again.', 'طلبات التحليل كثيرة. انتظر دقيقة ثم حاول مجددًا.'),
                    'oldUrl' => (string) ($_POST['url'] ?? ''),
                ], 429);
                return;
            }

            $report = ServiceFactory::orchestrator($this->repository)->analyze((string) ($_POST['url'] ?? ''));
            $this->render('analysis', [
                'title' => tr('Analysis in progress', 'التحليل قيد التنفيذ'),
                'report' => $report,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->render('home', [
                'title' => tr('Inspect a suspicious link', 'فحص رابط مشبوه'),
                'error' => Translator::validationMessage($exception->getMessage()),
                'oldUrl' => (string) ($_POST['url'] ?? ''),
            ], 422);
        } catch (Throwable $exception) {
            $this->log($exception);
            $this->render('home', [
                'title' => tr('Inspect a suspicious link', 'فحص رابط مشبوه'),
                'error' => tr('The analysis could not be completed. Please try again.', 'تعذر إكمال التحليل. حاول مجددًا.'),
                'oldUrl' => (string) ($_POST['url'] ?? ''),
            ], 500);
        }
    }

    public function show(string $caseId): void
    {
        $report = $this->repository->find($caseId);
        if ($report === null) {
            $this->notFound();
            return;
        }
        $this->render('result', ['title' => tr('Investigation report', 'تقرير التحقيق'), 'report' => $report]);
    }

    public function history(): void
    {
        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $this->render('history', [
            'title' => tr('Analysis history', 'سجل التحليلات'),
            'records' => $this->repository->search($query),
            'query' => $query,
        ]);
    }

    public function delete(string $caseId): void
    {
        if (!Csrf::valid($_POST['_token'] ?? null)) {
            $this->render('error', [
                'title' => tr('Session expired', 'انتهت الجلسة'),
                'message' => tr('Refresh the case history and try again.', 'حدّث سجل الحالات وحاول مجددًا.'),
            ], 419);
            return;
        }
        $limiter = new RateLimiter(BASE_PATH . '/storage/rate-limits', 20, 60);
        if (!$limiter->attempt('delete:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
            http_response_code(429);
            $this->render('error', [
                'title' => tr('Request limit reached', 'تم بلوغ حد الطلبات'),
                'message' => tr('Please wait before deleting another record.', 'انتظر قبل حذف سجل آخر.'),
            ]);
            return;
        }
        $this->repository->delete($caseId);
        $_SESSION['flash'] = tr('The analysis record was deleted.', 'تم حذف سجل التحليل.');
        header('Location: /history', true, 303);
    }

    public function pdf(string $caseId): void
    {
        $report = $this->repository->find($caseId);
        if ($report === null) {
            $this->notFound();
            return;
        }
        try {
            $content = (new PdfReportService())->render($report);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="linkguard-' . rawurlencode($caseId) . '.pdf"');
            header('Content-Length: ' . strlen($content));
            echo $content;
        } catch (RuntimeException $exception) {
            $this->log($exception);
            $this->render('error', [
                'title' => tr('PDF unavailable', 'ملف PDF غير متاح'),
                'message' => tr('The report could not be generated. Check the application installation.', 'تعذر إنشاء التقرير. تحقق من تثبيت التطبيق.'),
            ], 503);
        }
    }

    public function about(): void
    {
        $this->render('about', ['title' => tr('How LinkGuard works', 'كيف يعمل LinkGuard')]);
    }

    public function notFound(): void
    {
        $this->render('error', [
            'title' => tr('Case not found', 'الحالة غير موجودة'),
            'message' => tr('The requested analysis record does not exist or has been deleted.', 'سجل التحليل المطلوب غير موجود أو تم حذفه.'),
        ], 404);
    }

    private function requireCsrf(): void
    {
        if (!Csrf::valid($_POST['_token'] ?? null)) {
            throw new InvalidArgumentException('Your session token expired. Refresh the page and try again.');
        }
    }

    private function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        extract($data, EXTR_SKIP);
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();
        require BASE_PATH . '/app/Views/layout.php';
    }

    private function log(Throwable $exception): void
    {
        $line = sprintf("[%s] %s in %s:%d\n", date(DATE_ATOM), $exception->getMessage(), $exception->getFile(), $exception->getLine());
        error_log($line, 3, BASE_PATH . '/storage/logs/app.log');
    }
}
