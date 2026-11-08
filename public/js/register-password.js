(() => {
    const form = document.getElementById('registration-form');
    if (!form) return;
    const password = form.querySelector('#password');
    const confirmation = form.querySelector('#password_confirmation');
    const rows = document.querySelectorAll('[data-password-rule]');
    const status = document.getElementById('password-rule-status');

    function update() {
        const value = password.value;
        const rules = {
            length: Array.from(value).length >= 8,
            lowercase: /\p{Ll}/u.test(value),
            uppercase: /\p{Lu}/u.test(value),
            number: /\p{N}/u.test(value),
            symbol: /[\p{Z}\p{S}\p{P}]/u.test(value),
            match: value.length > 0 && confirmation.value === value,
        };
        for (const row of rows) {
            const met = String(rules[row.dataset.passwordRule]);
            if (row.dataset.met === met) continue;
            row.dataset.met = met;
            row.querySelector('[data-rule-icon]').textContent = met === 'true' ? '✓' : '○';
            row.querySelector('[data-rule-state]').textContent = met === 'true' ? 'رعایت شده' : 'رعایت نشده';
        }
        const validPassword = rules.length && rules.lowercase && rules.uppercase && rules.number && rules.symbol;
        password.setCustomValidity(value && !validPassword ? 'رمز عبور باید تمام شرایط نمایش‌داده‌شده را داشته باشد.' : '');
        confirmation.setCustomValidity(confirmation.value && !rules.match ? 'تکرار رمز عبور یکسان نیست.' : '');
        confirmation.setAttribute('aria-invalid', String(confirmation.value.length > 0 && !rules.match));
        const message = validPassword && rules.match ? 'تمام شرایط رمز عبور رعایت شده است.' : '';
        if (status.textContent !== message) status.textContent = message;
        status.style.color = '#15803d';
    }

    for (const field of [password, confirmation]) {
        field.addEventListener('input', update);
        field.addEventListener('change', update);
    }
    form.addEventListener('submit', event => {
        update();
        if (!form.reportValidity()) event.preventDefault();
    });
    window.addEventListener('pageshow', update);
    update();
})();
