const { strict: assert } = require('node:assert');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync(require('node:path').join(__dirname, '../public/js/verify-mobile.js'), 'utf8');

function page(active = false) {
    let now = Date.parse('2026-11-08T12:00:00Z');
    const elements = new Map();
    const requests = [];
    const navigation = [];
    const replies = [];
    const timers = new Map();
    let timerId = 0;
    for (const id of ['otp-form', 'code', 'otp-entry', 'otp-expired', 'otp-action', 'otp-notice', 'otp-code-error', 'otp-clock', 'otp-timer-label']) {
        elements.set(id, {
            dataset: {}, value: '', hidden: false, textContent: '', disabled: false,
            listeners: {}, attrs: {},
            addEventListener(name, callback) { this.listeners[name] = callback; },
            setAttribute(name, value) { this.attrs[name] = value; },
            removeAttribute(name) { delete this.attrs[name]; },
            reportValidity() { return true; }, focus() {}, select() {},
        });
    }
    const form = elements.get('otp-form');
    form.dataset = {
        expiresAt: active ? new Date(now + 120000).toISOString() : '',
        serverNow: new Date(now).toISOString(), retryAfter: active ? '120' : '0',
        verifyAction: '/verify-mobile/verify', resendAction: '/verify-mobile/resend',
    };
    const document = { hidden: false, getElementById: id => elements.get(id), addEventListener() {} };
    const clock = class extends Date { static now() { return now; } };
    vm.runInNewContext(source, {
        document, window: { addEventListener() {}, location: { assign: url => navigation.push(url) } },
        Date: clock, FormData: class { constructor() { this.code = elements.get('code').value; } },
        setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
        clearTimeout(id) { timers.delete(id); },
        async fetch(url, options) {
            requests.push({ url, options });
            const reply = replies.shift();
            if (reply instanceof Error) throw reply;
            assert.ok(reply, 'Unexpected request');
            return { ok: reply.status < 400, status: reply.status, json: async () => reply.body };
        },
    });
    return {
        elements, requests, replies, navigation,
        async submit() {
            let prevented = false;
            await form.listeners.submit({ preventDefault() { prevented = true; } });
            assert.ok(prevented, 'Submission would reload the page');
        },
        advance(ms) {
            now += ms;
            const scheduled = [...timers.values()];
            timers.clear();
            for (const callback of scheduled) callback();
        },
        serverTime() { return new Date(now).toISOString(); },
        expiry() { return new Date(now + 120000).toISOString(); },
    };
}

(async () => {
    const ui = page();
    ui.replies.push({ status: 200, body: { success: true, codeExpiresAt: ui.expiry(), retryAfter: 120, serverNow: ui.serverTime() } });
    await ui.submit();
    assert.equal(ui.requests[0].url, '/verify-mobile/resend');
    assert.equal(ui.elements.get('otp-clock').textContent, '02:00');
    assert.equal(ui.elements.get('otp-entry').hidden, false);
    assert.equal(ui.elements.get('otp-action').disabled, false);
    assert.equal(ui.navigation.length, 0);
    ui.advance(10000);
    ui.elements.get('code').value = '\u06f1\u06f2\u06f3\u06f4\u06f5\u06f6';
    ui.replies.push({ status: 422, body: { success: false, message: 'wrong code', can_retry: true } });
    await ui.submit();
    assert.equal(ui.requests[1].url, '/verify-mobile/verify');
    assert.equal(ui.requests[1].options.body.code, '123456');
    assert.equal(ui.elements.get('otp-code-error').textContent, 'wrong code');
    assert.equal(ui.elements.get('otp-clock').textContent, '01:50');
    assert.equal(ui.navigation.length, 0);
    ui.replies.push({ status: 200, body: { success: true, redirect: '/register/role-selection' } });
    await ui.submit();
    assert.deepEqual(ui.navigation, ['/register/role-selection']);

    const expired = page(true);
    expired.advance(120001);
    assert.equal(expired.elements.get('otp-entry').hidden, true);
    expired.replies.push({ status: 429, body: { success: false, message: 'rate limited', retryAfter: 60, codeExpiresAt: null, serverNow: expired.serverTime() } });
    await expired.submit();
    assert.equal(expired.elements.get('otp-action').disabled, true);
    assert.equal(expired.elements.get('otp-clock').textContent, '01:00');
    assert.equal(expired.elements.get('otp-code-error').textContent, 'rate limited');
    assert.equal(expired.navigation.length, 0);
    expired.advance(60001);
    expired.replies.push(new Error('offline'));
    await expired.submit();
    assert.equal(expired.elements.get('otp-action').disabled, false);
    assert.equal(expired.elements.get('otp-code-error').hidden, false);
    assert.equal(expired.navigation.length, 0);
    console.log('PASS: resend without refresh, timer reset, inline verification errors, Persian digits, success navigation, cooldown and network recovery');
})().catch(error => { console.error(error); process.exitCode = 1; });
