const navToggle = document.querySelector('[data-nav-toggle]');
if (navToggle) {
    navToggle.addEventListener('click', () => {
        const nav = document.getElementById('site-nav');
        const open = nav.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', String(open));
    });
}

const analysisForm = document.querySelector('[data-analysis-form]');
const arabic = document.documentElement.lang === 'ar';
if (analysisForm) {
    analysisForm.addEventListener('submit', () => {
        const button = analysisForm.querySelector('button[type="submit"]');
        if (analysisForm.checkValidity()) {
            button.disabled = true;
            button.textContent = arabic ? 'جاري فتح الحالة…' : 'Opening case…';
        }
    });
}

const progress = document.querySelector('[data-analysis-progress]');
if (progress) {
    const steps = [...progress.querySelectorAll('[data-step]')];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const delay = reducedMotion ? 25 : 420;
    let index = 0;
    const advance = () => {
        if (index > 0) {
            steps[index - 1].classList.remove('is-active');
            steps[index - 1].classList.add('is-complete');
            steps[index - 1].querySelector('.step-status').textContent = arabic ? 'مكتمل' : 'Complete';
        }
        if (index < steps.length) {
            steps[index].classList.add('is-active');
            steps[index].querySelector('.step-status').textContent = arabic ? 'جارٍ العمل' : 'Working';
            index += 1;
            window.setTimeout(advance, delay);
            return;
        }
        window.setTimeout(() => window.location.assign(progress.dataset.resultUrl), reducedMotion ? 25 : 350);
    };
    advance();
}

document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(arabic ? 'حذف سجل التحليل؟ لا يمكن التراجع عن هذا الإجراء.' : 'Delete this analysis record? This cannot be undone.')) {
            event.preventDefault();
        }
    });
});
