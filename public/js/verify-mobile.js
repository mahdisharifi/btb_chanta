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
    let expiresAt = 0;
    let resendAt = 0;
    let wasActive = false;
    let submitting = false;
    let verifyingRequest = false;
    let timer;

    function syncTiming(state) {
        const now = Date.now();
        const expiry = Date.parse(state.codeExpiresAt) || 0;
        const serverNow = Date.parse(state.serverNow) || now;
        expiresAt = now + Math.max(0, expiry - serverNow);
        resendAt = now + Math.max(0, Number(state.retryAfter) || 0) * 1000;
    }
    syncTiming({ codeExpiresAt: form.dataset.expiresAt, serverNow: form.dataset.serverNow, retryAfter: form.dataset.retryAfter });
    wasActive = expiresAt > Date.now();

    function normalizeCode() {
        input.value = input.value.replace(/[\u06f0-\u06f9]/g, c => String(c.charCodeAt(0) - 1776))
            .replace(/[\u0660-\u0669]/g, c => String(c.charCodeAt(0) - 1632)).replace(/[^0-9]/g, '').slice(0, 6);
    }

    function render() {
        const now = Date.now();
        const active = now < expiresAt;
        const remaining = Math.max(0, Math.ceil(((active ? expiresAt : resendAt) - now) / 1000));
        entry.hidden = !active;
        expired.hidden = active;
        input.disabled = !active || submitting;
        input.required = active;
        form.action = active ? form.dataset.verifyAction : form.dataset.resendAction;
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
            ? (verifyingRequest ? 'در حال بررسی کد…' : 'در حال ارسال کد…')
            : (active ? 'تأیید کد' : 'ارسال مجدد کد');
        return active || remaining > 0;
    }

    function resume() {
        clearTimeout(timer);
        if (render() && !document.hidden) timer = setTimeout(resume, 1000);
    }

    input.addEventListener('input', normalizeCode);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        const wasVerifying = wasActive;
        render();
        // Enter pressed at the expiry boundary must not request a new SMS.
        if (wasVerifying && !wasActive) { resume(); return; }
        if (action.disabled) return;
        normalizeCode();
        if (!form.reportValidity()) return;
        const payload = new FormData(form);
        const endpoint = form.action;
        verifyingRequest = wasActive;
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        error.hidden = true;
        input.setAttribute('aria-invalid', 'false');
        resume();
        try {
            const response = await fetch(endpoint, {
                method: 'POST', body: payload, credentials: 'same-origin', redirect: 'error',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.success && data.redirect) {
                window.location.assign(data.redirect);
                return;
            }
            if (!verifyingRequest && Object.hasOwn(data, 'retryAfter')) {
                syncTiming(data);
                resume();
            }
            if (response.ok && data.success && !verifyingRequest) {
                input.value = '';
                notice.textContent = 'کد تأیید جدید ارسال شد. کد ۶ رقمی پیامک‌شده را وارد کنید.';
                return;
            }
            if (data.can_retry === false) { expiresAt = 0; render(); }
            const message = response.status === 419 || response.status === 401
                ? 'نشست شما پایان یافته است. برای ادامه دوباره وارد حساب شوید.'
                : response.status === 429 && !data.retryAfter
                    ? 'درخواست‌های زیادی ارسال شده است. کمی صبر کنید و دوباره تلاش کنید.'
                    : data.errors?.code?.[0] || data.message || 'انجام درخواست ممکن نشد. دوباره تلاش کنید.';
            error.textContent = message;
            error.hidden = false;
            if (verifyingRequest) input.setAttribute('aria-invalid', 'true');
        } catch {
            error.textContent = 'ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';
            error.hidden = false;
        } finally {
            submitting = false;
            form.removeAttribute('aria-busy');
            resume();
            if (wasActive) { input.focus(); input.select(); }
        }
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
