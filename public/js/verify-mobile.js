(() => {
    const form = document.getElementById('otp-form');
    if (!form) return;
    const input = document.getElementById('code');
    const entry = document.getElementById('otp-entry');
    const expired = document.getElementById('otp-expired');
    const action = document.getElementById('otp-action');
    const notice = document.getElementById('otp-notice');
    const error = document.getElementById('otp-code-error');
    const clock = document.getElementById('otp-clock');
    const label = document.getElementById('otp-timer-label');
    const started = Date.now();
    const serverNow = Date.parse(form.dataset.serverNow);
    const expiry = Date.parse(form.dataset.expiresAt) || 0;
    const expiresAt = started + Math.max(0, expiry - serverNow);
    const resendAt = started + Math.max(0, Number(form.dataset.retryAfter) || 0) * 1000;
    let wasActive = expiry > serverNow;
    let submitting = false;
    let timer;

    function normalizeCode() {
        input.value = input.value.replace(/[۰-۹]/g, c => String(c.charCodeAt(0) - 1776))
            .replace(/[٠-٩]/g, c => String(c.charCodeAt(0) - 1632)).replace(/[^0-9]/g, '').slice(0, 6);
    }

    function render() {
        const now = Date.now();
        const active = now < expiresAt;
        const remaining = Math.max(0, Math.ceil(((active ? expiresAt : resendAt) - now) / 1000));
        entry.hidden = !active;
        expired.hidden = active;
        input.disabled = !active || submitting;
        input.required = active;
        if (!active) form.action = form.dataset.resendAction;
        if (wasActive && !active) {
            notice.textContent = 'اعتبار کد به پایان رسید. برای ادامه، کد جدید دریافت کنید.';
            error.hidden = true;
            input.value = '';
        }
        wasActive = active;
        label.textContent = active ? 'اعتبار کد:' : remaining > 0 ? 'ارسال مجدد تا:' : 'آمادهٔ ارسال کد جدید';
        clock.hidden = !active && remaining === 0;
        const time = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
        if (clock.textContent !== time) clock.textContent = time;
        action.disabled = submitting || (!active && remaining > 0);
        action.textContent = submitting
            ? (active ? 'در حال بررسی کد…' : 'در حال ارسال کد…')
            : (active ? 'تأیید کد' : 'ارسال مجدد کد');
        return active || remaining > 0;
    }

    function resume() {
        clearTimeout(timer);
        const running = render();
        if (running && !document.hidden && !submitting) timer = setTimeout(resume, 1000);
    }

    input.addEventListener('input', normalizeCode);
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        // An Enter press at expiry must not unexpectedly request an SMS.
        const wasVerifying = wasActive;
        render();
        if (wasVerifying && !wasActive) { event.preventDefault(); resume(); return; }
        if (action.disabled) { event.preventDefault(); return; }
        normalizeCode();
        if (!form.reportValidity()) { event.preventDefault(); return; }
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        action.disabled = true;
        action.textContent = wasActive ? 'در حال بررسی کد…' : 'در حال ارسال کد…';
        // Keep the code enabled until the browser serializes the submitted form.
        clearTimeout(timer);
    });
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('pageshow', () => {
        submitting = false;
        form.removeAttribute('aria-busy');
        resume();
    });
    window.addEventListener('pagehide', () => clearTimeout(timer));
    resume();
})();
