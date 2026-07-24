<?php

namespace LinkGuard\Support;

final class Translator
{
    public const SUPPORTED = ['en', 'ar'];

    public static function locale(): string
    {
        $locale = (string) ($_SESSION['locale'] ?? 'en');
        return in_array($locale, self::SUPPORTED, true) ? $locale : 'en';
    }

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, self::SUPPORTED, true)) {
            $_SESSION['locale'] = $locale;
        }
    }

    public static function isRtl(): bool
    {
        return self::locale() === 'ar';
    }

    public static function choose(string $english, string $arabic, array $replace = []): string
    {
        $text = self::isRtl() ? $arabic : $english;
        foreach ($replace as $key => $value) {
            $text = str_replace(':' . $key, (string) $value, $text);
        }
        return $text;
    }

    public static function riskLevel(string $level): string
    {
        if (!self::isRtl()) {
            return $level;
        }
        return match ($level) {
            'Low Observed Risk' => 'مخاطر مرصودة منخفضة',
            'Low Risk' => 'مخاطر منخفضة',
            'Moderate Risk' => 'مخاطر متوسطة',
            'High Risk' => 'مخاطر مرتفعة',
            'Critical Risk' => 'مخاطر حرجة',
            default => $level,
        };
    }

    public static function status(string $status): string
    {
        if (!self::isRtl()) {
            return $status;
        }
        return match (strtolower($status)) {
            'complete' => 'مكتمل',
            'available' => 'متاح',
            'unavailable' => 'غير متاح',
            'partial' => 'جزئي',
            'limited' => 'محدود',
            'demonstration' => 'تجريبي',
            'extended' => 'موسع',
            'comprehensive' => 'شامل',
            default => $status,
        };
    }

    public static function agent(string $agent): string
    {
        if (!self::isRtl()) {
            return $agent;
        }
        return match ($agent) {
            'URL Structure Agent' => 'وكيل بنية الرابط',
            'Reputation Agent' => 'وكيل السمعة',
            'Phishing Pattern Agent' => 'وكيل أنماط التصيد',
            'Sandbox Content Agent' => 'وكيل المحتوى المعزول',
            'Risk Scoring Agent' => 'وكيل حساب المخاطر',
            'Explanation Agent' => 'وكيل التفسير',
            default => $agent,
        };
    }

    public static function findingTitle(array $finding): string
    {
        if (!self::isRtl()) {
            return (string) ($finding['title'] ?? '');
        }
        return match ($finding['code'] ?? '') {
            'literal_ip' => 'المضيف عنوان IP مباشر',
            'punycode' => 'النطاق يستخدم Punycode',
            'brand_impersonation' => 'اشتباه في انتحال علامة تجارية',
            'reputation_malicious' => 'حكم سمعة ضار',
            'reputation_suspicious' => 'حكم سمعة مشبوه',
            'phishing_keyword' => 'عبارات مرتبطة بالتصيد',
            'excessive_subdomains' => 'عدد كبير من النطاقات الفرعية',
            'url_shortener' => 'استخدام مختصر روابط',
            'long_url_medium', 'long_url_high' => 'رابط طويل بشكل غير معتاد',
            'non_standard_port' => 'منفذ غير معتاد',
            'http' => 'الاتصال غير مشفر',
            'page_title_missing' => 'عنوان الصفحة مفقود',
            'password_form' => 'تم اكتشاف حقل كلمة مرور',
            'external_form_action' => 'النموذج يرسل البيانات إلى نطاق آخر',
            'meta_refresh' => 'تحويل أو تحديث تلقائي معلن',
            'content_phishing_language' => 'لغة تصيد محتملة في الصفحة',
            'excessive_scripts' => 'عدد كبير من السكربتات',
            default => (string) ($finding['title'] ?? ''),
        };
    }

    public static function findingExplanation(array $finding): string
    {
        if (!self::isRtl()) {
            return (string) ($finding['explanation'] ?? '');
        }
        return match ($finding['code'] ?? '') {
            'brand_impersonation' => 'اسم النطاق يشبه علامة معروفة لكنه ليس ضمن نطاقاتها المعتمدة.',
            'reputation_malicious' => 'مصدر السمعة المهيأ أبلغ عن اكتشافات ضارة لهذا الرابط.',
            'reputation_suspicious' => 'مصدر السمعة المهيأ أبلغ عن مؤشرات مشبوهة لهذا الرابط.',
            'phishing_keyword' => 'يحتوي الرابط على كلمات حساسة تُستخدم كثيرًا في محاولات التصيد.',
            'punycode' => 'قد تُستخدم الأحرف الدولية المشفرة لتقليد نطاقات مألوفة.',
            'http' => 'اتصال HTTP لا يحمي البيانات أثناء النقل، وHTTPS وحده لا يثبت الأمان.',
            'page_title_missing' => 'لم تحتوِ الصفحة على عنوان صالح. هذه إشارة ضعيفة وليست دليلًا قاطعًا.',
            'password_form' => 'تطلب الصفحة كلمة مرور؛ تحقق من النطاق قبل إدخال بياناتك.',
            'external_form_action' => 'يوجد نموذج يرسل البيانات إلى موقع مختلف، وقد يدل ذلك على جمع بيانات الدخول.',
            'meta_refresh' => 'تحتوي الصفحة على تحويل تلقائي، ولم تتبعه خدمة الفحص المعزولة.',
            'content_phishing_language' => 'يحتوي النص الظاهر على عبارات تحقق أو استعجال أو تعليق حساب أو جوائز.',
            'excessive_scripts' => 'تعلن الصفحة عن سكربتات كثيرة؛ تم عدها دون تنفيذها.',
            default => (string) ($finding['explanation'] ?? ''),
        };
    }

    public static function recommendation(string $text): string
    {
        if (!self::isRtl()) {
            return $text;
        }
        return match ($text) {
            'Do not open the link or submit credentials.' => 'لا تفتح الرابط ولا تدخل بيانات الدخول.',
            'Verify the request through the organization’s official app or a manually typed address.' => 'تحقق من الطلب عبر التطبيق الرسمي أو بكتابة العنوان الرسمي يدويًا.',
            'Report the message to your security team or service provider.' => 'أبلغ فريق الأمن أو مزود الخدمة عن الرسالة.',
            'Confirm the sender and destination before opening the link.' => 'تحقق من المرسل والوجهة قبل فتح الرابط.',
            'Use a bookmarked or manually typed official address for sensitive actions.' => 'استخدم رابطًا رسميًا محفوظًا أو اكتبه يدويًا للعمليات الحساسة.',
            'Treat unexpected login, payment, or urgency requests with caution.' => 'تعامل بحذر مع طلبات الدخول أو الدفع أو الاستعجال غير المتوقعة.',
            default => $text,
        };
    }

    public static function reportValue(string $text): string
    {
        if (!self::isRtl()) {
            return $text;
        }
        return match ($text) {
            'Mock data only' => 'بيانات تجريبية فقط',
            'Unavailable' => 'غير متاح',
            'Live provider checked' => 'تم فحص المزود الحي',
            'Not inspected' => 'لم يتم الفحص',
            'Missing' => 'مفقود',
            'Metadata-only HTML inspection completed' => 'اكتمل فحص بيانات HTML المحدودة',
            'Fetch failed' => 'تعذر جلب الصفحة',
            'Redirect blocked' => 'تم حظر التحويل',
            'Disabled' => 'متوقف',
            'Local demonstration dataset' => 'مجموعة بيانات تجريبية محلية',
            default => $text,
        };
    }

    public static function limitation(string $text): string
    {
        if (!self::isRtl()) {
            return $text;
        }
        return match ($text) {
            'Reputation data is unavailable; this must not be interpreted as a clean result.' => 'بيانات السمعة غير متاحة، ولا يجب تفسير ذلك على أنه نتيجة سليمة.',
            'The URL-pattern check uses address text only; isolated page metadata is reported by the Sandbox Content Agent.' => 'يفحص وكيل الأنماط نص الرابط فقط، وتُعرض بيانات الصفحة عبر وكيل المحتوى المعزول.',
            'Page JavaScript, downloads, and redirects were not executed or followed.' => 'لم يتم تنفيذ JavaScript أو التنزيلات أو اتباع التحويلات.',
            default => str_starts_with($text, 'Page metadata could not be inspected:')
                ? 'تعذر فحص بيانات الصفحة عبر الخدمة المعزولة.'
                : $text,
        };
    }

    public static function validationMessage(string $message): string
    {
        if (!self::isRtl()) {
            return $message;
        }
        return match ($message) {
            'Enter a valid, complete URL.' => 'أدخل رابطًا كاملًا وصحيحًا.',
            'Only HTTP and HTTPS links can be analyzed.' => 'يمكن تحليل روابط HTTP وHTTPS فقط.',
            'Links containing embedded credentials are not accepted.' => 'لا تُقبل الروابط التي تحتوي على بيانات دخول مضمنة.',
            'Local, private, reserved, and special-purpose addresses are blocked.' => 'العناوين المحلية والخاصة والمحجوزة محظورة.',
            'The URL is too long.' => 'الرابط أطول من الحد المسموح.',
            'Your session token expired. Refresh the page and try again.' => 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مجددًا.',
            default => $message,
        };
    }
}
