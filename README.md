# LinkGuard AI | فاحص الروابط المشبوهة

[![Tests](https://github.com/alhassan-alshareef/linkguard-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/alhassan-alshareef/linkguard-ai/actions/workflows/tests.yml)

تطبيق ويب ثنائي اللغة يساعد المستخدم على **فحص مؤشرات الخطر الظاهرة في الروابط قبل فتحها**. يحلل الرابط بقواعد واضحة، يفحص سمعته عند تفعيل مزود خارجي، ويمكنه قراءة بيانات محدودة من الصفحة داخل حاوية معزولة، ثم ينشئ نتيجة قابلة للتفسير وتقرير PDF بالعربية أو الإنجليزية.

> النتيجة هي درجة **مؤشرات خطر مرصودة** وليست ضماناً بأن الموقع آمن أو خبيث. ظهور 0% يعني أن الفحوص المتاحة لم تجد مؤشرات، وليس أن الرابط آمن 100%.

## الديمو

- رابط الديمو: سيُضاف هنا بعد ربط المستودع بخدمة الاستضافة.
- يعمل الديمو العام افتراضياً في وضع `mock` الآمن؛ نتائج السمعة فيه تجريبية ومعلّمة بوضوح.
- للحصول على سمعة حية يجب ضبط `REPUTATION_MODE=virustotal` وإضافة مفتاح VirusTotal في متغيرات البيئة، بدون وضعه داخل الكود.

## ماذا يفعل المشروع؟

1. يتحقق من صيغة الرابط ويرفض البروتوكولات غير المسموحة والعناوين الداخلية.
2. يفحص شكل الرابط: Punycode، عنوان IP مباشر، كثرة النطاقات الفرعية، الروابط المختصرة، HTTP، المنافذ غير المعتادة، والطول الزائد.
3. يبحث عن كلمات التصيد ومحاولات تقليد أسماء العلامات.
4. يقرأ سمعة الرابط من مزود قابل للتبديل: بيانات تجريبية محلية أو VirusTotal.
5. اختيارياً، يقرأ عنوان الصفحة والنماذج وبعض البيانات الوصفية داخل حاوية Node.js معزولة، بدون تشغيل JavaScript أو تنزيل ملفات.
6. يحسب درجة حتمية من 0 إلى 100 ويعرض الأدلة وحدود الفحص.
7. يحفظ سجل الحالة في SQLite ويصدر تقرير PDF باللغة المختارة.

## الأدوات والتقنيات

| الأداة | استخدامها في المشروع |
|---|---|
| PHP 8.2 | منطق التطبيق، التوجيه، الخدمات، والتحقق الأمني |
| HTML / CSS / JavaScript | واجهة متجاوبة بدون إطار واجهات ثقيل |
| SQLite + PDO | حفظ نتائج التحليل محلياً باستعلامات مجهزة |
| Dompdf | إنشاء تقارير PDF |
| Ar-PHP | تشكيل النص العربي داخل تقارير PDF |
| Node.js | خدمة فحص بيانات الصفحة الوصفية |
| Docker Compose | عزل خدمة فحص المحتوى وتقييد مواردها |
| VirusTotal API | مزود اختياري لسمعة الروابط |
| PHPUnit | اختبارات الوحدات والتكامل والأمان |
| GitHub Actions | تشغيل اختبارات PHP وNode تلقائياً |
| Render Blueprint | إعداد جاهز لنشر ديمو Docker |

## التشغيل السريع

المتطلبات: PHP 8.2 أو أحدث، Composer 2، وإضافات `curl` و`mbstring` و`pdo_sqlite` و`dom`.

```powershell
Copy-Item .env.example .env
composer install
php -S 127.0.0.1:8000 -t public public/router.php
```

افتح `http://127.0.0.1:8000`.

لتشغيل فحص بيانات الصفحة المعزول:

```powershell
php scripts/setup-sandbox.php
docker compose up -d --build
```

ثم غيّر `CONTENT_SANDBOX_MODE=http` في ملف `.env`. لا ترفع ملف `.env` إلى GitHub.

## روابط آمنة للتجربة

| الحالة | الرابط |
|---|---|
| مؤشرات قليلة | `https://example.com/` |
| حالة سمعة تجريبية مشبوهة | `https://suspicious-demo.example.com/verify-account` |
| نمط تقليد علامة | `https://paypal-secure.example.com/verify-account` |
| حالة تجريبية عالية الخطورة | `https://known-risk.example.com/login` |
| رابط غير صالح | `https://webook@@.com/w` |

النطاقات ذات النتائج التجريبية أعلاه محجوزة للاختبار، ولا تمثل حكماً على مواقع حقيقية.

## أوضاع السمعة

- `mock`: بيانات محلية توضيحية، وهو الوضع الافتراضي للديمو.
- `virustotal`: فحص سمعة حي بمفتاح API صالح.
- غير متاح: يُعرض الفحص كغير مكتمل، ولا يُعامل غياب البيانات كإشارة أمان.

## نموذج الدرجة

الأوزان موجودة في [`config/risk.php`](config/risk.php)، ومن أمثلتها:

| المؤشر | النقاط |
|---|---:|
| عنوان IP مباشر | 20 |
| Punycode | 20 |
| احتمال تقليد علامة | 25 |
| سمعة خبيثة | 40 |
| سمعة مشبوهة | 25 |
| كلمات تصيد | 10 |
| HTTP بدون تشفير | 5 |
| نموذج يرسل إلى نطاق خارجي | 20 |

المستويات: 0–24 منخفض مرصود، 25–49 متوسط، 50–74 مرتفع، و75–100 حرج.

## الاختبارات

```powershell
composer test
Set-Location sandbox
npm.cmd test
```

ولتجربة مجموعة الأمان التنفيذية شغّل التطبيق أولاً، ثم:

```powershell
php security/run_security_tests.php --base-url=http://127.0.0.1:8000
```

تشمل الاختبارات: SSRF وXSS وCSRF وSQL injection والجلسات والحد من الطلبات وصحة احتساب الدرجة وإنشاء PDF وعدم تشغيل JavaScript غير الموثوق.

## هيكل المشروع

```text
app/
  Controllers/        استقبال الطلبات
  Models/             تخزين SQLite
  Services/
    Agents/           وكلاء التحليل والدرجة والتفسير
    Reputation/       مزودات السمعة
    Sandbox/          الاتصال بخدمة المحتوى المعزولة
  Support/            التحقق، الحماية، الترجمة، وتحديد المعدل
  Views/              صفحات HTML وقالب PDF
config/               إعداد التطبيق وأوزان الخطورة
database/             مخطط قاعدة البيانات
public/               نقطة الدخول وCSS وJavaScript
sandbox/              خدمة Node.js المعزولة
security/             مجموعة اختبارات الأمان
tests/                اختبارات PHPUnit
```

## حدود مهمة

- لا يفحص عمر النطاق أو مالكه أو سجل الشهادات حالياً.
- الفحص المعزول ثابت ولا يشغّل JavaScript الخاص بالموقع.
- قواعد العلامات والكلمات توضيحية وقد تنتج إيجابيات أو سلبيات خاطئة.
- VirusTotal قد لا يملك تقريراً سابقاً لبعض الروابط؛ التطبيق لا يرسل الرابط للفحص تلقائياً.
- SQLite مناسبة للديمو المحلي، وليست إعداداً نهائياً لخدمة متعددة المستخدمين.

التفاصيل الأمنية وحدود الثقة موثقة في [`SECURITY.md`](SECURITY.md).

---

## English summary

LinkGuard AI is a bilingual PHP 8.2 application that validates suspicious URLs, blocks internal targets, analyzes URL and phishing patterns, optionally checks VirusTotal reputation, inspects bounded page metadata in an isolated Node.js container, calculates a deterministic observed-risk score, stores cases in SQLite, and exports Arabic or English PDF reports.

Quick start:

```bash
cp .env.example .env
composer install
php -S 127.0.0.1:8000 -t public public/router.php
```

The score represents observed indicators—not the probability that a URL is malicious and never a guarantee of safety.
