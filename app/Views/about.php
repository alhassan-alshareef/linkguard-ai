<?php use LinkGuard\Support\Escaper; use LinkGuard\Support\Translator; $e = static fn (mixed $value): string => Escaper::html($value); ?>
<section class="page-heading">
    <div class="eyebrow"><?= $e(tr('METHOD / SCOPE / SAFETY', 'المنهج / النطاق / الأمان')) ?></div>
    <h1><?= $e(tr('How LinkGuard works', 'كيف يعمل LinkGuard؟')) ?></h1>
    <p><?= $e(tr('A transparent multi-agent pipeline turns observable URL evidence into a deterministic risk assessment.', 'يحوّل مسار شفاف متعدد الوكلاء أدلة الرابط المرصودة إلى تقييم مخاطر حتمي.')) ?></p>
</section>

<section class="method-grid">
    <article class="paper method-card"><span>01</span><h2><?= $e(Translator::agent('URL Structure Agent')) ?></h2><p><?= $e(tr('Examines IP hosts, Punycode, shorteners, unusual length, subdomains, ports, and protocol.', 'يفحص عناوين IP وPunycode والروابط المختصرة والطول والنطاقات الفرعية والمنافذ والبروتوكول.')) ?></p></article>
    <article class="paper method-card"><span>02</span><h2><?= $e(Translator::agent('Reputation Agent')) ?></h2><p><?= $e(tr('Uses the configured reputation provider. Failure is reported as unavailable, never as a clean verdict.', 'يستخدم مزود السمعة المهيأ، ويعرض فشله كغير متاح ولا يحوله إلى نتيجة سليمة.')) ?></p></article>
    <article class="paper method-card"><span>03</span><h2><?= $e(Translator::agent('Phishing Pattern Agent')) ?></h2><p><?= $e(tr('Looks for sensitive wording and possible brand imitation in the URL.', 'يبحث عن العبارات الحساسة واحتمال تقليد العلامات التجارية في الرابط.')) ?></p></article>
    <article class="paper method-card"><span>04</span><h2><?= $e(Translator::agent('Sandbox Content Agent')) ?></h2><p><?= $e(tr('Extracts bounded HTML metadata without executing JavaScript, redirects, or downloads.', 'يستخرج بيانات HTML محدودة دون تنفيذ JavaScript أو التحويلات أو التنزيلات.')) ?></p></article>
    <article class="paper method-card"><span>05</span><h2><?= $e(Translator::agent('Risk Scoring Agent')) ?></h2><p><?= $e(tr('Adds published deterministic rule weights and caps the total at 100.', 'يجمع أوزان القواعد الحتمية المنشورة ويحد النتيجة عند 100.')) ?></p></article>
    <article class="paper method-card"><span>06</span><h2><?= $e(Translator::agent('Explanation Agent')) ?></h2><p><?= $e(tr('Restates evidence and recommendations without changing the score.', 'يعيد صياغة الأدلة والتوصيات دون تغيير النتيجة.')) ?></p></article>
    <article class="paper method-card method-orchestrator"><span>→</span><h2><?= $e(tr('Analysis Orchestrator', 'منسق التحليل')) ?></h2><p><?= $e(tr('Validates the URL, runs each specialist, combines results, and saves one case report.', 'يتحقق من الرابط ويشغّل الوكلاء ويجمع النتائج ويحفظ تقرير حالة واحدًا.')) ?></p></article>
</section>

<section class="paper boundaries">
    <div><span class="section-number"><?= $e(tr('CURRENT BOUNDARIES', 'الحدود الحالية')) ?></span><h2><?= $e(tr('What the system cannot confirm', 'ما الذي لا يستطيع النظام تأكيده؟')) ?></h2></div>
    <ul>
        <li><?= $e(tr('The sandbox never executes scripts, follows redirects, or downloads files.', 'لا تنفذ البيئة المعزولة السكربتات ولا تتبع التحويلات ولا تنزّل الملفات.')) ?></li>
        <li><?= $e(tr('A low score means few observed indicators, not proof of safety.', 'النتيجة المنخفضة تعني قلة المؤشرات المرصودة وليست إثباتًا للأمان.')) ?></li>
        <li><?= $e(tr('Mock reputation data does not describe the live internet.', 'بيانات السمعة التجريبية لا تصف حالة الإنترنت الفعلية.')) ?></li>
        <li><?= $e(tr('Ownership, domain age, and dynamic behavior need additional trusted sources.', 'تتطلب الملكية وعمر النطاق والسلوك الديناميكي مصادر موثوقة إضافية.')) ?></li>
    </ul>
</section>

<section class="safety-tips">
    <span class="section-number"><?= $e(tr('PRACTICAL DEFENSE', 'حماية عملية')) ?></span>
    <h2><?= $e(tr('Three habits that stop many phishing attempts', 'ثلاث عادات تمنع الكثير من محاولات التصيد')) ?></h2>
    <div class="note-grid">
        <article><span>01</span><h3><?= $e(tr('Pause on urgency', 'توقف عند الاستعجال')) ?></h3><p><?= $e(tr('Unexpected deadlines, threats, and prizes are designed to rush your decision.', 'المواعيد والتهديدات والجوائز المفاجئة مصممة لدفعك إلى قرار سريع.')) ?></p></article>
        <article><span>02</span><h3><?= $e(tr('Navigate independently', 'انتقل بشكل مستقل')) ?></h3><p><?= $e(tr('Open the official app or type the known address yourself.', 'افتح التطبيق الرسمي أو اكتب العنوان المعروف بنفسك.')) ?></p></article>
        <article><span>03</span><h3><?= $e(tr('Verify elsewhere', 'تحقق عبر قناة أخرى')) ?></h3><p><?= $e(tr('Confirm unusual requests through a trusted phone number or separate channel.', 'تحقق من الطلبات غير المعتادة عبر رقم موثوق أو قناة اتصال منفصلة.')) ?></p></article>
    </div>
</section>
