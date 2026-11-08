const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function field() {
    return { value: '', listeners: {}, addEventListener(name, fn) { this.listeners[name] = fn; },
        setCustomValidity(message) { this.validationMessage = message; }, setAttribute() {} };
}
const password = field();
const confirmation = field();
const rows = ['length', 'lowercase', 'uppercase', 'number', 'symbol', 'match'].map(rule => ({
    dataset: { passwordRule: rule }, icon: {}, state: {},
    querySelector(selector) { return selector === '[data-rule-icon]' ? this.icon : this.state; },
}));
const status = { style: {}, textContent: '' };
const form = { querySelector(id) { return id === '#password' ? password : confirmation; }, addEventListener() {} };
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../public/js/register-password.js'), 'utf8'), {
    document: { getElementById(id) { return id === 'registration-form' ? form : status; }, querySelectorAll() { return rows; } },
    window: { addEventListener() {} },
});
const update = () => password.listeners.input();
assert.ok(rows.every(row => row.dataset.met === 'false'));
password.value = 'Abcdefg1!';
confirmation.value = password.value;
update();
assert.ok(rows.every(row => row.dataset.met === 'true' && row.icon.textContent === '✓'));
assert.equal(password.validationMessage, '');
assert.equal(confirmation.validationMessage, '');
assert.ok(status.textContent);
for (const [value, rule] of [['Ab1!', 'length'], ['ABCDEFG1!', 'lowercase'], ['abcdefg1!', 'uppercase'], ['Abcdefgh!', 'number'], ['Abcdefgh1', 'symbol']]) {
    password.value = value;
    confirmation.value = value;
    update();
    assert.equal(rows.find(row => row.dataset.passwordRule === rule).dataset.met, 'false');
    assert.ok(password.validationMessage);
}
password.value = 'Abcdefg1!';
confirmation.value = 'different';
update();
assert.equal(rows.find(row => row.dataset.passwordRule === 'match').dataset.met, 'false');
assert.ok(confirmation.validationMessage);
console.log('PASS: password checklist, live ticks, invalidation after edits and confirmation mismatch');
