const imageInput = document.querySelector('#imageInput');
const dropzone = document.querySelector('#dropzone');
const fileMeta = document.querySelector('#fileMeta');
const fileName = document.querySelector('#fileName');
const fileSize = document.querySelector('#fileSize');
const removeFile = document.querySelector('#removeFile');
const editButton = document.querySelector('#editButton');
const setupNotice = document.querySelector('#setupNotice');
const settingsPanel = document.querySelector('#settingsPanel');
const avalaiUrl = document.querySelector('#avalaiUrl');
const avalaiToken = document.querySelector('#avalaiToken');
const avalaiTokenStatus = document.querySelector('#avalaiTokenStatus');
const creditToToman = document.querySelector('#creditToToman');
const settingsMessage = document.querySelector('#settingsMessage');
const saveSettings = document.querySelector('#saveSettings');
const emptyResult = document.querySelector('#emptyResult');
const loadingResult = document.querySelector('#loadingResult');
const errorResult = document.querySelector('#errorResult');
const errorText = document.querySelector('#errorText');
const comparison = document.querySelector('#comparison');
const sourcePreview = document.querySelector('#sourcePreview');
const resultPreview = document.querySelector('#resultPreview');
const downloadLink = document.querySelector('#downloadLink');
const costSummary = document.querySelector('#costSummary');
const apiBase = (document.documentElement.dataset.apiBase || '/api').replace(/\/$/, '');
const apiUrl = (endpoint) => `${apiBase}${endpoint}`;

let selectedFile = null;
let csrfToken = '';
let sourceUrl = '';
let outputUrl = '';
let outputUrlIsBlob = false;
let maxUploadMb = 15;
let avalaiReady = false;

function setState(state) {
  emptyResult.hidden = state !== 'empty';
  loadingResult.hidden = state !== 'loading';
  errorResult.hidden = state !== 'error';
  comparison.hidden = state !== 'result';
  if (state !== 'result') costSummary.hidden = true;
}

function refreshButton() {
  editButton.disabled = !selectedFile || !avalaiReady || editButton.classList.contains('is-loading');
}

function showSettingsMessage(message, kind = '') {
  settingsMessage.textContent = message;
  settingsMessage.className = 'settings-message' + (kind ? ' is-' + kind : '');
}

function showError(message) {
  errorText.textContent = message;
  setState('error');
}

function setFile(file) {
  if (!file) return;
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    showError('فرمت فایل باید JPG، PNG یا WebP باشد.');
    return;
  }
  if (file.size > maxUploadMb * 1024 * 1024) {
    showError('حجم فایل باید حداکثر ' + maxUploadMb + ' مگابایت باشد.');
    return;
  }
  selectedFile = file;
  if (sourceUrl) URL.revokeObjectURL(sourceUrl);
  sourceUrl = URL.createObjectURL(file);
  fileName.textContent = file.name;
  fileSize.textContent = formatBytes(file.size);
  fileMeta.hidden = false;
  dropzone.classList.add('has-file');
  sourcePreview.src = sourceUrl;
  setupNotice.hidden = avalaiReady;
  setState('empty');
  refreshButton();
}

function formatBytes(bytes) {
  return bytes < 1024 * 1024
    ? Math.max(1, Math.round(bytes / 1024)) + ' کیلوبایت'
    : (bytes / (1024 * 1024)).toFixed(1) + ' مگابایت';
}

function resetFile() {
  selectedFile = null;
  imageInput.value = '';
  fileMeta.hidden = true;
  dropzone.classList.remove('has-file');
  if (sourceUrl) URL.revokeObjectURL(sourceUrl);
  sourceUrl = '';
  refreshButton();
}

imageInput.addEventListener('change', () => setFile(imageInput.files?.[0]));
removeFile.addEventListener('click', resetFile);
for (const eventName of ['dragenter', 'dragover']) {
  dropzone.addEventListener(eventName, (event) => {
    event.preventDefault();
    dropzone.classList.add('is-dragging');
  });
}
for (const eventName of ['dragleave', 'drop']) {
  dropzone.addEventListener(eventName, (event) => {
    event.preventDefault();
    dropzone.classList.remove('is-dragging');
  });
}
dropzone.addEventListener('drop', (event) => setFile(event.dataTransfer?.files?.[0]));

async function initialize() {
  try {
    const sessionResponse = await fetch(apiUrl('/session'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const session = await sessionResponse.json();
    csrfToken = session.csrf || '';

    const response = await fetch(apiUrl('/config'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const config = await response.json();
    const avalai = config.avalai || {};
    maxUploadMb = config.maxUploadMb || 15;
    document.querySelector('#maxUpload').textContent = maxUploadMb;
    avalaiUrl.value = avalai.baseUrl || 'https://api.avalai.ir';
    avalaiTokenStatus.textContent = avalai.hasApiKey
      ? 'کلید در سرور ذخیره شده است؛ برای حفظ آن این فیلد را خالی بگذار.'
      : 'هنوز کلیدی ذخیره نشده است.';
    creditToToman.value = avalai.pricing?.creditToToman ?? 268050;
    avalaiReady = Boolean(avalai.ready);
    setupNotice.hidden = avalaiReady;
    settingsPanel.hidden = avalaiReady;
    refreshButton();
  } catch (error) {
    setupNotice.hidden = false;
    showError('سرور تست در دسترس نیست؛ صفحه را دوباره بارگذاری کن.');
  }
}

document.querySelector('#openSettings').addEventListener('click', () => {
  settingsPanel.hidden = false;
  settingsPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  avalaiToken.focus({ preventScroll: true });
});
document.querySelector('#closeSettings').addEventListener('click', () => { settingsPanel.hidden = true; });
document.querySelector('#toggleAvalAiToken').addEventListener('click', (event) => {
  const isPassword = avalaiToken.type === 'password';
  avalaiToken.type = isPassword ? 'text' : 'password';
  event.currentTarget.textContent = isPassword ? 'پنهان' : 'نمایش';
});

saveSettings.addEventListener('click', async () => {
  showSettingsMessage('در حال ذخیره‌سازی...');
  saveSettings.disabled = true;
  try {
    const response = await fetch(apiUrl('/config'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({
        avalai: {
          baseUrl: avalaiUrl.value.trim(),
          apiKey: avalaiToken.value,
          pricing: { creditToToman: creditToToman.value },
        },
      }),
    });
    const config = await response.json();
    if (!response.ok) throw new Error(config.error || config.message || Object.values(config.errors || {}).flat()[0] || 'ذخیرهٔ تنظیمات انجام نشد.');
    avalaiUrl.value = config.avalai.baseUrl;
    creditToToman.value = config.avalai.pricing.creditToToman;
    avalaiToken.value = '';
    avalaiTokenStatus.textContent = 'کلید در سرور ذخیره شد؛ برای حفظ آن فیلد را خالی بگذار.';
    avalaiReady = Boolean(config.avalai.ready);
    setupNotice.hidden = avalaiReady;
    refreshButton();
    showSettingsMessage('تنظیمات ذخیره شد؛ ابزار آمادهٔ تست است.', 'success');
  } catch (error) {
    showSettingsMessage(error.message || 'ذخیرهٔ تنظیمات انجام نشد.', 'error');
  } finally {
    saveSettings.disabled = false;
  }
});

async function normalizeToSquare(source) {
  const image = new Image();
  if (source.startsWith('https://')) image.crossOrigin = 'anonymous';
  image.src = source;
  await image.decode();

  const canvas = document.createElement('canvas');
  canvas.width = 1200;
  canvas.height = 1200;
  const context = canvas.getContext('2d', { alpha: false });
  if (!context) throw new Error('مرورگر امکان آماده‌سازی فایل تصویر را نداد.');
  context.fillStyle = '#FFFFFF';
  context.fillRect(0, 0, 1200, 1200);
  const scale = Math.min(1200 / image.naturalWidth, 1200 / image.naturalHeight);
  const width = Math.round(image.naturalWidth * scale);
  const height = Math.round(image.naturalHeight * scale);
  context.drawImage(image, Math.round((1200 - width) / 2), Math.round((1200 - height) / 2), width, height);
  const blob = await new Promise((resolve, reject) => canvas.toBlob((value) => value ? resolve(value) : reject(new Error('ساخت فایل PNG ممکن نشد.')), 'image/png'));
  return URL.createObjectURL(blob);
}

editButton.addEventListener('click', async () => {
  if (!selectedFile || !avalaiReady) return;
  const form = new FormData();
  form.append('image', selectedFile);
  form.append('model', 'muse-image-1.0');
  form.append('product_name', document.querySelector('#productName').value.trim());

  editButton.classList.add('is-loading');
  editButton.querySelector('.button-label').textContent = 'در حال ویرایش تصویر...';
  refreshButton();
  setState('loading');
  setupNotice.hidden = true;

  try {
    const response = await fetch(apiUrl('/edit'), {
      method: 'POST',
      body: form,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || result.message || Object.values(result.errors || {}).flat()[0] || 'ویرایش عکس انجام نشد.');

    if (outputUrlIsBlob && outputUrl) URL.revokeObjectURL(outputUrl);
    try {
      outputUrl = await normalizeToSquare(result.image);
      outputUrlIsBlob = true;
      document.querySelector('#outputSize').textContent = '۱۲۰۰ × ۱۲۰۰ پیکسل';
      downloadLink.download = 'product-edited-1200x1200.png';
      downloadLink.removeAttribute('target');
    } catch (normalizationError) {
      outputUrl = result.image;
      outputUrlIsBlob = false;
      document.querySelector('#outputSize').textContent = 'ابعاد تصویر مدل';
      downloadLink.download = 'product-edited.png';
      downloadLink.target = outputUrl.startsWith('https://') ? '_blank' : '';
      downloadLink.rel = 'noopener';
    }
    resultPreview.src = outputUrl;
    downloadLink.href = outputUrl;
    document.querySelector('#modelResult').textContent = 'ویرایش انجام شد';
    if (result.cost) renderCost(result.cost);
    setState('result');
  } catch (error) {
    showError(error.message || 'اتصال به سرویس انجام نشد.');
  } finally {
    editButton.classList.remove('is-loading');
    editButton.querySelector('.button-label').textContent = 'ویرایش عکس محصول';
    refreshButton();
  }
});

initialize();

function formatInteger(value) {
  return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 }).format(Number(value || 0));
}

function formatDecimal(value, digits = 6) {
  return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: digits }).format(Number(value || 0));
}

function renderCost(cost) {
  document.querySelector('#inputTokensValue').textContent = '۰٫۰۱ اعتبار';
  document.querySelector('#outputTokensValue').textContent = '۱۵٪';
  document.querySelector('#creditCostValue').textContent = formatDecimal(cost.totalCredits) + ' اعتبار';
  document.querySelector('#tomanCostValue').textContent = formatInteger(cost.totalToman) + ' تومان';
  const badge = document.querySelector('#costBadge');
  badge.textContent = cost.exact ? 'هزینهٔ دقیق' : 'تخمینی';
  badge.classList.toggle('is-exact', Boolean(cost.exact));
  const fundingSource = cost.exactSource === 'grant' ? ' این درخواست از اعتبار گرنت پوشش داده شد.'
    : cost.exactSource === 'credit_package' ? ' این درخواست از بستهٔ اعتباری پوشش داده شد.' : '';
  document.querySelector('#costStatus').textContent = cost.exact
    ? 'هزینه از تراکنش آوال‌ای‌آی؛ هر اعتبار ' + formatInteger(cost.creditToToman) + ' تومان.' + fundingSource
    : 'برآورد تعرفهٔ فعلی؛ هر اعتبار ' + formatInteger(cost.creditToToman) + ' تومان.';
  document.querySelector('#costBreakdown').textContent = cost.description || '۰٫۰۱ اعتبار پایه + کارمزد ۱۵٪.';
  costSummary.hidden = false;
}
