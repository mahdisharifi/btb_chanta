const baseUrlInput = document.querySelector('#base-url');
const tokenInput = document.querySelector('#api-token');
const modelSelect = document.querySelector('#model-select');
const connectionStatus = document.querySelector('#connection-status');
const activeModelLabel = document.querySelector('#active-model');
const chatLog = document.querySelector('#chat-log');
const welcomeMessage = document.querySelector('#welcome-message');
const messageInput = document.querySelector('#message-input');
const sendButton = document.querySelector('#send-button');
const recordButton = document.querySelector('#record-button');
const liveModeButton = document.querySelector('#live-mode-button');
const liveTranscriptPreview = document.querySelector('#live-transcript-preview');
const liveTranscriptText = document.querySelector('#live-transcript-text');
const voiceStatus = document.querySelector('#voice-status');
const micEnhancement = document.querySelector('#mic-enhancement');
const microphoneSelect = document.querySelector('#microphone-select');
const localEngineLabel = document.querySelector('#local-engine-label');
const ttsEngineSelect = document.querySelector('#tts-engine');
const mossReferenceInput = document.querySelector('#moss-reference-audio');
const mossReferenceField = document.querySelector('#moss-reference-field');
const mossStatus = document.querySelector('#moss-status');

let conversation = [];
let activeToken = '';
let busy = false;
let recorder = null;
let recordingStream = null;
let recordingMicrophoneLabel = '';
let audioChunks = [];
let liveTranscriptionSocket = null;
let liveConversationSocket = null;
let liveConversationStream = null;
let liveConversationActive = false;
let liveSpeechActive = false;
let liveTurnDispatching = false;
let activeTurnController = null;
let activeStreamPlayer = null;
let liveOutputAudioContext = null;
const liveTurnQueue = [];
let previousLivePartialWords = [];
let recordingAudioContext = null;
let recordingAudioProcessor = null;
let recordingAudioSilenceGain = null;
const audioUrls = new Set();

const SYSTEM_MESSAGE = [
  'به فارسی روان و محاوره‌ای پاسخ بده؛ طوری بنویس که شنیدنش طبیعی باشد.',
  'پاسخ را به شکل گفتار پیوسته و کوتاه بنویس؛ از تیتر، فهرست شماره‌دار، بولت و نشانه‌گذاری نمایشی پرهیز کن.',
  'لحن را گرم و انسانی نگه دار، اما احساسات را اغراق‌آمیز یا تصنعی نکن.',
  'فقط پاسخ نهایی و قابل‌نمایش به کاربر را برگردان؛ متن تحلیل، زنجیره‌ی استدلال، thinking یا reasoning را در پاسخ نهایی قرار نده.',
  'اگر پاسخ طولانی است، آن را مختصر و قابل‌شنیدن نگه دار.'
].join(' ');

function normalizedBaseUrl() {
  return baseUrlInput.value.trim().replace(/\/+$/, '');
}

function setConnectionStatus(text, kind = '') {
  connectionStatus.textContent = text;
  connectionStatus.dataset.kind = kind;
}

function setVoiceStatus(text) {
  voiceStatus.textContent = text;
}

function clearLiveTranscriptPreview() {
  previousLivePartialWords = [];
  if (liveTranscriptText) liveTranscriptText.textContent = '';
  if (liveTranscriptPreview) liveTranscriptPreview.hidden = true;
}

function updateLiveTranscriptPreview(rawText) {
  const words = String(rawText || '').trim().split(/\s+/).filter(Boolean);
  const stableWords = [];
  const length = Math.min(previousLivePartialWords.length, words.length);
  for (let index = 0; index < length; index += 1) {
    const previous = previousLivePartialWords[index].replace(/[.,!?؟،؛:…]+$/g, '');
    const current = words[index].replace(/[.,!?؟،؛:…]+$/g, '');
    if (previous !== current) break;
    stableWords.push(words[index]);
  }
  previousLivePartialWords = words;
  const stableText = stableWords.join(' ');
  if (!stableText || !liveTranscriptPreview || !liveTranscriptText) {
    clearLiveTranscriptPreview();
    previousLivePartialWords = words;
    return;
  }
  liveTranscriptText.textContent = stableText;
  liveTranscriptPreview.hidden = false;
}

function selectedModel() {
  return modelSelect.value.trim();
}

function refreshControls() {
  const ready = Boolean(selectedModel() && activeToken);
  messageInput.disabled = !ready || busy;
  sendButton.disabled = !ready || busy;
  recordButton.disabled = !ready || busy || liveConversationActive || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder;
  if (liveModeButton) {
    liveModeButton.disabled = liveConversationActive
      ? false
      : (!ready || busy || !navigator.mediaDevices?.getUserMedia);
    liveModeButton.classList.toggle('active', liveConversationActive);
    liveModeButton.textContent = liveConversationActive ? 'پایان مکالمه‌ی زنده' : 'شروع مکالمه‌ی زنده';
  }
  if (ttsEngineSelect) ttsEngineSelect.disabled = busy;
  if (mossReferenceInput) mossReferenceInput.disabled = busy;
  modelSelect.disabled = modelSelect.options.length === 0 || !modelSelect.options[0].value;
  activeModelLabel.textContent = selectedModel() || 'مدلی انتخاب نشده';
}

function appendMessage(role, text, isError = false) {
  chatLog.querySelector('#welcome-message')?.remove();
  const message = document.createElement('article');
  message.className = `message ${isError ? 'error' : role}`;
  if (!isError) {
    const label = document.createElement('span');
    label.className = 'message-label';
    label.textContent = role === 'user' ? 'شما' : 'دستیار';
    message.append(label);
  }
  const content = document.createElement('span');
  content.textContent = text;
  message.append(content);
  chatLog.append(message);
  chatLog.scrollTop = chatLog.scrollHeight;
  return content;
}

async function readError(response) {
  const body = await response.text();
  try {
    const parsed = JSON.parse(body);
    return parsed?.error?.message || parsed?.detail || parsed?.message || body || `HTTP ${response.status}`;
  } catch {
    return body || `HTTP ${response.status}`;
  }
}

async function loadModels() {
  const base = normalizedBaseUrl();
  const token = tokenInput.value.trim();
  if (!base || !token) {
    setConnectionStatus('نشانی API و توکن را وارد کن.', 'error');
    return;
  }

  activeToken = token;
  setConnectionStatus('در حال گرفتن فهرست مدل‌ها…');
  modelSelect.disabled = true;
  document.querySelector('#load-models').disabled = true;

  try {
    const response = await fetch(`${base}/models`, {
      method: 'GET',
      headers: { Authorization: `Bearer ${activeToken}` }
    });
    if (!response.ok) throw new Error(await readError(response));
    const payload = await response.json();
    const rawModels = Array.isArray(payload) ? payload : (payload.data || payload.models || []);
    const models = rawModels
      .map(item => typeof item === 'string' ? { id: item } : item)
      .filter(item => item && (item.id || item.name || item.model))
      .map(item => ({
        id: String(item.id || item.model || item.name),
        label: String(item.name || item.display_name || item.id || item.model)
      }));

    if (!models.length) throw new Error('پاسخ API فهرست مدلی نداشت.');

    modelSelect.replaceChildren();
    for (const item of models) {
      const option = document.createElement('option');
      option.value = item.id;
      option.textContent = item.label === item.id ? item.id : `${item.label} — ${item.id}`;
      modelSelect.append(option);
    }
    modelSelect.disabled = false;
    setConnectionStatus(`${models.length} مدل دریافت شد. مدل موردنظرت را انتخاب کن.`, 'success');
    refreshControls();
  } catch (error) {
    modelSelect.replaceChildren(new Option('فهرست مدل‌ها دریافت نشد', ''));
    activeToken = '';
    setConnectionStatus(`گرفتن مدل‌ها ناموفق بود: ${error.message}`, 'error');
    refreshControls();
  } finally {
    document.querySelector('#load-models').disabled = false;
  }
}

function contentAsText(content) {
  if (typeof content === 'string') return content;
  if (!Array.isArray(content)) return '';
  return content
    .filter(part => {
      if (!part || /^(analysis|reasoning|thinking|internal_reasoning)$/i.test(String(part.type || ''))) return false;
      return part.type === 'text' || (!part.type && typeof part.text === 'string');
    })
    .map(part => part.text || '')
    .join('\n');
}

function finalTextOnly(rawText) {
  let text = String(rawText || '');

  // Models that put internal reasoning in tagged blocks must not have those blocks spoken.
  text = text.replace(/<(think|analysis|reasoning|scratchpad)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, '');
  text = text.replace(/<(think|analysis|reasoning|scratchpad)\b[^>]*>[\s\S]*$/gi, '');
  for (const tag of ['think', 'analysis', 'reasoning', 'scratchpad']) {
    const closeTag = new RegExp(`<\\/${tag}\\s*>`, 'ig');
    let lastClose = -1;
    for (const match of text.matchAll(closeTag)) lastClose = match.index + match[0].length;
    if (lastClose >= 0 && !new RegExp(`<${tag}\\b`, 'i').test(text)) text = text.slice(lastClose);
  }

  const finalHeading = text.match(/(?:^|\n)\s*(?:final(?:\s+answer)?|پاسخ\s+نهایی)\s*[:：]\s*/i);
  if (finalHeading) text = text.slice(finalHeading.index + finalHeading[0].length);

  // If a provider puts an unclosed analysis channel directly in content, fail closed.
  if (/^\s*(?:analysis|reasoning|thinking)\s*[:：]/i.test(text)) return '';

  text = text.replace(/<\|(?:analysis|reasoning|think|final)\|>/gi, '');
  const finalChannel = text.match(/<\|channel\|>\s*final\b([\s\S]*)$/i);
  if (finalChannel) text = finalChannel[1];
  else if (/<\|channel\|>\s*(?:analysis|reasoning|thinking)\b/i.test(text)) return '';
  text = text.replace(/\n{3,}/g, '\n\n').trim();
  return text;
}

function extractFinalAnswer(payload) {
  const message = payload?.choices?.[0]?.message;
  if (!message) throw new Error('پاسخ API قالب گفت‌وگوی قابل‌شناسایی نداشت.');

  const channel = String(message.channel || message.type || '').toLowerCase();
  if (/(analysis|reasoning|thinking)/.test(channel) || message.is_reasoning === true) {
    throw new Error('این بخش از پاسخ reasoning است؛ برای جلوگیری از خواندن آن، صدا ساخته نشد.');
  }

  // Deliberately read only message.content. Fields such as reasoning_content/reasoning are ignored.
  const answer = finalTextOnly(contentAsText(message.content));
  if (!answer) throw new Error('مدل پاسخ نهایی در content برنگرداند؛ reasoning خوانده نمی‌شود.');
  return answer;
}

function cleanForSpeech(rawText) {
  let text = String(rawText || '');
  text = text.replace(/```[\s\S]*?```/g, '');
  text = text.replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1');
  text = text.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
  text = text.replace(/https?:\/\/\S+/gi, '');
  text = text.replace(/^\s*\[[ xX]\]\s*/gm, '');
  text = text.replace(/^\s*(?:[-*+•‣–—]|\d+[.)])\s+/gm, '');
  text = text.replace(/^\s{0,3}#{1,6}\s*/gm, '');
  text = text.replace(/^\s*>\s?/gm, '');
  text = text.replace(/(\*\*|__)(.*?)\1/g, '$2');
  text = text.replace(/(\*|_)([^\n]*?)\1/g, '$2');
  text = text.replace(/~~(.*?)~~/g, '$1');
  text = text.replace(/`+/g, '');
  text = text.replace(/[\\*_#~]/g, '');
  text = text.replace(/\n+/g, '. ');
  text = text.replace(/([،؛.?!])\s*\.\s*/g, '$1 ');
  return text.replace(/\s{2,}/g, ' ').trim();
}

async function synthesizeAndPlay(text) {
  const engine = ttsEngineSelect?.value || 'piper';
  let response;
  if (engine === 'moss') {
    setVoiceStatus('در حال ساخت صدای پاسخ با MOSS؛ روی CPU ممکن است طول بکشد…');
    const form = new FormData();
    form.append('text', text);
    if (mossReferenceInput?.files?.[0]) {
      form.append('prompt_audio', mossReferenceInput.files[0]);
    }
    response = await fetch('/api/synthesize/moss', { method: 'POST', body: form });
  } else {
    setVoiceStatus('در حال ساخت صدای پاسخ با Piper…');
    response = await fetch('/api/synthesize', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text })
    });
  }
  if (!response.ok) throw new Error(await readError(response));
  const audioBlob = await response.blob();
  const objectUrl = URL.createObjectURL(audioBlob);
  audioUrls.add(objectUrl);
  const player = document.createElement('div');
  player.className = 'audio-wrap';
  const audio = document.createElement('audio');
  audio.className = 'reply-audio';
  audio.controls = true;
  audio.src = objectUrl;
  audio.preload = 'auto';
  player.append(audio);
  chatLog.append(player);
  chatLog.scrollTop = chatLog.scrollHeight;
  try {
    await audio.play();
    setVoiceStatus(`پاسخ نهایی با صدای ${engine === 'moss' ? 'MOSS' : 'Piper'} پخش می‌شود.`);
  } catch {
    setVoiceStatus('صدای پاسخ آماده است؛ برای پخش، دکمه‌ی پلیر را بزن.');
  }
}

class StreamingPcmPlayer {
  constructor(sharedContext = null, { keepReplay = true } = {}) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) throw new Error('مرورگر پخش صوت جریانی را پشتیبانی نمی‌کند.');
    this.context = sharedContext || new AudioContextClass();
    this.ownsContext = !sharedContext;
    this.keepReplay = keepReplay;
    this.nextPlayAt = 0;
    this.resumePromise = this.context.resume();
    this.format = null;
    this.audioParts = [];
    this.audioByteLength = 0;
    this.playbackPending = new Uint8Array(0);
    this.activeSources = new Set();
    this.stopped = false;
  }

  async consume(response) {
    if (!response.body) throw new Error('مرورگر جریان صوت را دریافت نکرد.');
    const sampleRate = Number(response.headers.get('X-Audio-Sample-Rate'));
    const channels = Number(response.headers.get('X-Audio-Channels'));
    if (!sampleRate || !channels || response.headers.get('X-Audio-Codec') !== 'pcm_s16le') {
      throw new Error('قالب جریان صوت از سرویس شناخته نشد.');
    }
    if (this.format && (this.format.sampleRate !== sampleRate || this.format.channels !== channels)) {
      throw new Error('قالب صوت بین بخش‌های پاسخ تغییر کرد.');
    }
    this.format = { sampleRate, channels };
    await this.resumePromise;
    const reader = response.body.getReader();
    const frameBytes = channels * 2;
    let carry = new Uint8Array(0);
    let scheduledFrames = 0;
    const playbackBlockBytes = Math.max(frameBytes, Math.floor(sampleRate * channels * 2 * 0.12 / frameBytes) * frameBytes);

    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      const bytes = new Uint8Array(carry.length + value.length);
      bytes.set(carry);
      bytes.set(value, carry.length);
      const completeLength = Math.floor(bytes.length / frameBytes) * frameBytes;
      carry = bytes.slice(completeLength);
      const frames = completeLength / frameBytes;
      if (!frames) continue;
      const completePcm = bytes.slice(0, completeLength);
      if (this.keepReplay) {
        this.audioParts.push(completePcm);
        this.audioByteLength += completePcm.length;
      }
      this.playbackPending = this.#appendBytes(this.playbackPending, completePcm);
      while (this.playbackPending.length >= playbackBlockBytes) {
        const playbackBlock = this.playbackPending.slice(0, playbackBlockBytes);
        this.playbackPending = this.playbackPending.slice(playbackBlockBytes);
        scheduledFrames += this.#schedulePcm(playbackBlock, sampleRate, channels);
      }
    }
    if (carry.length) throw new Error('جریان صوت در میانه‌ی نمونه قطع شد.');
    if (this.playbackPending.length) {
      scheduledFrames += this.#schedulePcm(this.playbackPending, sampleRate, channels);
      this.playbackPending = new Uint8Array(0);
    }
    if (!scheduledFrames) throw new Error('موتور صدا هیچ داده‌ای تولید نکرد.');
  }

  #appendBytes(left, right) {
    const merged = new Uint8Array(left.length + right.length);
    merged.set(left);
    merged.set(right, left.length);
    return merged;
  }

  #schedulePcm(pcmBytes, sampleRate, channels) {
    if (this.stopped) return 0;
    const frameBytes = channels * 2;
    const frames = Math.floor(pcmBytes.length / frameBytes);
    if (!frames) return 0;
    const audioBuffer = this.context.createBuffer(channels, frames, sampleRate);
    const channelViews = Array.from({ length: channels }, (_, channel) => audioBuffer.getChannelData(channel));
    const data = new DataView(pcmBytes.buffer, pcmBytes.byteOffset, frames * frameBytes);
    for (let frame = 0; frame < frames; frame += 1) {
      for (let channel = 0; channel < channels; channel += 1) {
        channelViews[channel][frame] = data.getInt16((frame * channels + channel) * 2, true) / 32768;
      }
    }

    const source = this.context.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(this.context.destination);
    this.activeSources.add(source);
    source.addEventListener('ended', () => this.activeSources.delete(source), { once: true });
    const now = this.context.currentTime;
    const startAt = this.nextPlayAt > now + 0.005 ? this.nextPlayAt : now + 0.015;
    source.start(startAt);
    this.nextPlayAt = startAt + audioBuffer.duration;
    return frames;
  }

  addNaturalGap(seconds = 0.09) {
    if (this.format && this.keepReplay) {
      const silenceBytes = Math.round(this.format.sampleRate * seconds) * this.format.channels * 2;
      if (silenceBytes > 0) {
        this.audioParts.push(new Uint8Array(silenceBytes));
        this.audioByteLength += silenceBytes;
      }
    }
    const now = this.context.currentTime;
    this.nextPlayAt = (this.nextPlayAt > now ? this.nextPlayAt : now + 0.015) + seconds;
  }

  toWavBlob() {
    if (!this.format || !this.audioByteLength) return null;
    const { sampleRate, channels } = this.format;
    const wav = new ArrayBuffer(44 + this.audioByteLength);
    const view = new DataView(wav);
    const writeAscii = (offset, value) => {
      for (let index = 0; index < value.length; index += 1) view.setUint8(offset + index, value.charCodeAt(index));
    };
    writeAscii(0, 'RIFF');
    view.setUint32(4, 36 + this.audioByteLength, true);
    writeAscii(8, 'WAVE');
    writeAscii(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, channels, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * channels * 2, true);
    view.setUint16(32, channels * 2, true);
    view.setUint16(34, 16, true);
    writeAscii(36, 'data');
    view.setUint32(40, this.audioByteLength, true);
    const pcm = new Uint8Array(wav, 44);
    let offset = 0;
    for (const part of this.audioParts) {
      pcm.set(part, offset);
      offset += part.length;
    }
    return new Blob([wav], { type: 'audio/wav' });
  }

  async waitForPlayback() {
    await this.resumePromise;
    const remaining = Math.max(0, this.nextPlayAt - this.context.currentTime);
    if (remaining) await new Promise(resolve => setTimeout(resolve, Math.ceil(remaining * 1000)));
  }

  async close() {
    this.stopped = true;
    if (this.ownsContext) {
      try { await this.context.close(); } catch { /* already closed */ }
    }
  }

  async stop() {
    if (this.stopped) return;
    this.stopped = true;
    for (const source of this.activeSources) {
      try { source.stop(); } catch { /* the source may have finished */ }
    }
    this.activeSources.clear();
    if (this.ownsContext) {
      try { await this.context.close(); } catch { /* already closed */ }
    }
  }
}

function nextSpeechSegments(text, flush = false) {
  const segments = [];
  let pending = text;
  while (pending.trim()) {
    let boundary = pending.search(/[.!?؟؛](?=\s|$)/);
    if (boundary < 0 && pending.length > 88) {
      const clause = Math.max(pending.slice(0, 105).lastIndexOf('،'), pending.slice(0, 105).lastIndexOf('؛'));
      if (clause >= 42) boundary = clause;
      else if (pending.length > 120) {
        const wordBoundary = pending.slice(0, 105).lastIndexOf(' ');
        if (wordBoundary >= 72) boundary = wordBoundary;
      }
    }
    if (boundary < 0) break;
    const end = boundary + (/[.!?؟؛،]/.test(pending[boundary]) ? 1 : 0);
    segments.push(pending.slice(0, end));
    pending = pending.slice(end).replace(/^\s+/, '');
  }
  if (flush && pending.trim()) {
    segments.push(pending.trim());
    pending = '';
  }
  return { segments, pending };
}

async function* readOpenAiSse(response) {
  if (!response.body) throw new Error('مدل جریان پاسخ را برنگرداند.');
  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let finished = false;

  while (!finished) {
    const { value, done } = await reader.read();
    buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
    buffer = buffer.replace(/\r\n/g, '\n');
    let boundary;
    while ((boundary = buffer.indexOf('\n\n')) >= 0) {
      const block = buffer.slice(0, boundary);
      buffer = buffer.slice(boundary + 2);
      const data = block.split('\n')
        .filter(line => line.startsWith('data:'))
        .map(line => line.slice(5).trimStart())
        .join('\n');
      if (!data) continue;
      if (data === '[DONE]') {
        finished = true;
        break;
      }
      try { yield JSON.parse(data); } catch { /* Ignore non-JSON keepalive events. */ }
    }
    if (done) {
      if (buffer.trim()) {
        const data = buffer.split('\n')
          .filter(line => line.startsWith('data:'))
          .map(line => line.slice(5).trimStart())
          .join('\n');
        if (data && data !== '[DONE]') {
          try { yield JSON.parse(data); } catch { /* Ignore an incomplete final event. */ }
        }
      }
      break;
    }
  }
}

function streamDeltaText(delta) {
  if (!delta || typeof delta !== 'object') return '';
  const channel = String(delta.channel || delta.type || '').toLowerCase();
  if (/(analysis|reasoning|thinking|internal_reasoning)/.test(channel) || delta.is_reasoning === true) return '';
  return typeof delta.content === 'string' ? delta.content : contentAsText(delta.content);
}

function enqueueSpeechSegment(player, text, state) {
  const segment = text.trim();
  if (!segment) return;
  state.chain = state.chain.then(async () => {
    if (state.error || state.signal?.aborted) return;
    const form = new FormData();
    form.append('text', segment);
    form.append('engine', state.engine);
    if (state.engine === 'moss' && state.referenceFile) {
      form.append('prompt_audio', state.referenceFile);
    }
    const response = await fetch('/api/synthesize/stream', { method: 'POST', body: form, signal: state.signal });
    if (!response.ok) throw new Error(await readError(response));
    await player.consume(response);
    player.addNaturalGap();
  }).catch(error => {
    if (error.name === 'AbortError' || state.signal?.aborted) return;
    state.error ||= error;
    setVoiceStatus(`متن پاسخ آماده است؛ جریان TTS متوقف شد: ${error.message}`);
  });
}

function updateTtsEngineSettings() {
  const useMoss = ttsEngineSelect?.value === 'moss';
  if (mossReferenceField) mossReferenceField.hidden = !useMoss;
  if (mossStatus) mossStatus.hidden = !useMoss;
}

async function refreshMossStatus() {
  if (!mossStatus || ttsEngineSelect?.value !== 'moss') return;
  mossStatus.textContent = 'در حال بررسی سرویس MOSS…';
  mossStatus.dataset.kind = '';
  try {
    const response = await fetch('/api/status');
    if (!response.ok) throw new Error('وضعیت سرویس خوانده نشد.');
    const payload = await response.json();
    const moss = payload.moss;
    if (!moss) throw new Error('وضعیت MOSS در دسترس نیست.');
    mossStatus.textContent = moss.ready
      ? 'MOSS آماده است. صدای پیش‌فرض فارسی Mana در دسترس است.'
      : (moss.available ? `MOSS در حال آماده‌شدن است: ${moss.status}` : moss.status);
    mossStatus.dataset.kind = moss.ready ? 'success' : (moss.available ? '' : 'error');
  } catch (error) {
    mossStatus.textContent = error.message || 'وضعیت MOSS دریافت نشد.';
    mossStatus.dataset.kind = 'error';
  }
}

function appendRecordedAudio(blob) {
  chatLog.querySelector('#welcome-message')?.remove();
  const objectUrl = URL.createObjectURL(blob);
  audioUrls.add(objectUrl);
  const player = document.createElement('div');
  player.className = 'audio-wrap user-audio-wrap';
  const label = document.createElement('span');
  label.className = 'audio-label';
  label.textContent = 'صدای ضبط‌شده‌ی شما';
  const audio = document.createElement('audio');
  audio.className = 'reply-audio';
  audio.controls = true;
  audio.src = objectUrl;
  audio.preload = 'metadata';
  player.append(label, audio);
  chatLog.append(player);
  chatLog.scrollTop = chatLog.scrollHeight;
}

function appendStreamingReplay(player) {
  const audioBlob = player.toWavBlob();
  if (!audioBlob) return;
  const objectUrl = URL.createObjectURL(audioBlob);
  audioUrls.add(objectUrl);
  const playerElement = document.createElement('div');
  playerElement.className = 'audio-wrap';
  const label = document.createElement('span');
  label.className = 'audio-label';
  label.textContent = 'تکرار صدای پاسخ';
  const audio = document.createElement('audio');
  audio.className = 'reply-audio';
  audio.controls = true;
  audio.src = objectUrl;
  audio.preload = 'metadata';
  playerElement.append(label, audio);
  chatLog.append(playerElement);
  chatLog.scrollTop = chatLog.scrollHeight;
}

async function sendMessage(rawText, { live = false } = {}) {
  const text = rawText.trim();
  if (!text || busy) return;
  if (!selectedModel() || !activeToken) {
    setConnectionStatus('ابتدا توکن را وارد کن و مدل را انتخاب کن.', 'error');
    return;
  }

  busy = true;
  const turnController = new AbortController();
  activeTurnController = turnController;
  refreshControls();
  appendMessage('user', text);
  conversation.push({ role: 'user', content: text });
  if (!live) messageInput.value = '';
  setVoiceStatus('در حال دریافت پاسخ جریانی؛ صدا با رسیدن جمله‌ها شروع می‌شود…');

  let assistantContent = null;
  let player = null;
  let rawAnswer = '';
  let modelTextStreamed = false;
  let speechCursor = 0;
  const speechState = {
    chain: Promise.resolve(),
    error: null,
    engine: ttsEngineSelect?.value || 'piper',
    referenceFile: mossReferenceInput?.files?.[0] || null,
    signal: turnController.signal
  };

  try {
    // Create and unlock audio during the user's click, before the first network wait.
    player = new StreamingPcmPlayer(liveConversationActive ? liveOutputAudioContext : null, { keepReplay: !live });
    activeStreamPlayer = player;
    const chatUrl = `${normalizedBaseUrl()}/chat/completions`;
    const chatRequest = stream => ({
      method: 'POST',
      headers: {
        Authorization: `Bearer ${activeToken}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        model: selectedModel(),
        stream,
        messages: [{ role: 'system', content: SYSTEM_MESSAGE }, ...conversation]
      })
    });
    let response = await fetch(chatUrl, { ...chatRequest(true), signal: turnController.signal });
    if (!response.ok && [400, 404, 405, 422, 501].includes(response.status)) {
      response = await fetch(chatUrl, { ...chatRequest(false), signal: turnController.signal });
    }
    if (!response.ok) throw new Error(await readError(response));

    assistantContent = appendMessage('assistant', '');
    const updateAnswer = (flushSpeech = false) => {
      const visibleAnswer = finalTextOnly(rawAnswer);
      assistantContent.textContent = visibleAnswer || '…';
      chatLog.scrollTop = chatLog.scrollHeight;
      const speechText = cleanForSpeech(visibleAnswer);
      if (speechText.length < speechCursor) return;
      const pending = speechText.slice(speechCursor);
      const ready = nextSpeechSegments(pending, flushSpeech);
      const consumed = pending.length - ready.pending.length;
      for (const segment of ready.segments) enqueueSpeechSegment(player, segment, speechState);
      speechCursor += consumed;
    };

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('text/event-stream')) {
      // Keep compatibility with gateways that ignore stream=true.
      const payloadText = await response.text();
      let payload;
      try { payload = JSON.parse(payloadText); } catch {
        throw new Error('درگاه API پاسخ را با قالب جریانی یا JSON برنگرداند.');
      }
      rawAnswer = extractFinalAnswer(payload);
      updateAnswer(true);
    } else {
      modelTextStreamed = true;
      for await (const payload of readOpenAiSse(response)) {
        const choice = payload?.choices?.[0];
        const delta = choice?.delta || (typeof choice?.text === 'string' ? { content: choice.text } : null);
        const deltaText = streamDeltaText(delta);
        if (!deltaText) continue;
        rawAnswer += deltaText;
        updateAnswer(false);
      }
      updateAnswer(true);
    }

    const finalAnswer = finalTextOnly(rawAnswer);
    if (!finalAnswer) throw new Error('مدل پاسخ نهایی قابل‌خواندن برنگرداند؛ متن reasoning پخش نشد.');
    assistantContent.textContent = finalAnswer;
    conversation.push({ role: 'assistant', content: finalAnswer });
    await speechState.chain;
    if (turnController.signal.aborted) throw new DOMException('گفتار کاربر پاسخ را قطع کرد.', 'AbortError');
    appendStreamingReplay(player);
    if (speechState.error) {
      setVoiceStatus(`پاسخ متنی آماده است؛ جریان TTS اجرا نشد: ${speechState.error.message}`);
      player.waitForPlayback().catch(() => {}).finally(() => {
        player.close();
        if (activeStreamPlayer === player) activeStreamPlayer = null;
      });
    } else {
      setVoiceStatus(modelTextStreamed
        ? `متن و صدای پاسخ با ${speechState.engine === 'moss' ? 'MOSS' : 'Piper'} جریانی دریافت شد.`
        : `درگاه متن را یکجا فرستاد؛ صدای پاسخ با ${speechState.engine === 'moss' ? 'MOSS' : 'Piper'} جریانی پخش شد.`);
      player.waitForPlayback().catch(() => {}).finally(() => {
        player.close();
        if (activeStreamPlayer === player) activeStreamPlayer = null;
      });
    }
  } catch (error) {
    if (error.name === 'AbortError' || turnController.signal.aborted) {
      const partialAnswer = finalTextOnly(rawAnswer);
      if (partialAnswer) {
        if (assistantContent) assistantContent.textContent = partialAnswer;
        const lastMessage = conversation[conversation.length - 1];
        if (lastMessage?.role === 'assistant') lastMessage.content = partialAnswer;
        else conversation.push({ role: 'assistant', content: partialAnswer });
      } else {
        assistantContent?.parentElement?.remove();
      }
      if (player) await player.stop();
      setVoiceStatus(live ? 'صدای دستیار قطع شد؛ دارم حرف شما را می‌شنوم…' : 'پاسخ متوقف شد.');
    } else {
      conversation.pop();
      assistantContent?.parentElement?.remove();
      appendMessage('assistant', `درخواست ناموفق بود: ${error.message}`, true);
      setVoiceStatus('دریافت پاسخ یا شروع پخش جریانی ناموفق بود.');
      if (player) await player.close();
    }
    if (activeStreamPlayer === player) activeStreamPlayer = null;
  } finally {
    busy = false;
    if (activeTurnController === turnController) activeTurnController = null;
    refreshControls();
    if (!liveConversationActive) messageInput.focus();
    if (liveTurnQueue.length) dispatchLiveTurn();
  }
}

async function transcribeRecording(blob) {
  const form = new FormData();
  const mime = blob.type || 'audio/webm';
  const extension = mime.includes('mp4') ? 'm4a' : (mime.includes('wav') ? 'wav' : 'webm');
  form.append('file', blob, `speech.${extension}`);

  const response = await fetch('/api/transcribe', { method: 'POST', body: form });
  if (!response.ok) throw new Error(await readError(response));
  const result = await response.json();
  return {
    text: String(result.text || '').trim(),
    model: String(result.model || 'Whisper'),
    audio: result.audio || {}
  };
}

function describeRecordingQuality(audio) {
  const duration = Number(audio.duration_seconds || 0).toLocaleString('fa-IR', { maximumFractionDigits: 1 });
  const level = Number(audio.active_level_dbfs ?? -120);
  const quietPercent = Number(audio.quiet_percent || 0);
  const notes = [];
  if (audio.clipped) notes.push('صدا احتمالاً بریده/اعوجاج‌دار ضبط شده');
  if (level < -42) notes.push('بلندی صدای گفتار کم است؛ فاصله از میکروفن را کمتر کن یا تقویت خودکار را مقایسه کن');
  if (quietPercent > 65) notes.push('بخش زیادی از ضبط سکوت است؛ ضبط را نزدیک‌تر به شروع و پایان گفتار متوقف کن');
  if (!notes.length) notes.push('سطح ضبط مناسب به نظر می‌رسد');
  return `مدت ${duration} ثانیه؛ ${notes.join('؛ ')}`;
}

class StreamingPcm16Resampler {
  constructor(inputRate) {
    this.ratio = inputRate / 16000;
    this.carry = new Float32Array(0);
    this.position = 0;
  }

  process(input) {
    const data = new Float32Array(this.carry.length + input.length);
    data.set(this.carry);
    data.set(input, this.carry.length);
    const output = [];
    let cursor = this.position;
    while (cursor + this.ratio <= data.length) {
      const end = cursor + this.ratio;
      let weightedSum = 0;
      let totalWeight = 0;
      for (let source = Math.floor(cursor); source < Math.ceil(end); source += 1) {
        const weight = Math.max(0, Math.min(end, source + 1) - Math.max(cursor, source));
        weightedSum += data[source] * weight;
        totalWeight += weight;
      }
      const sample = totalWeight ? weightedSum / totalWeight : 0;
      output.push(Math.round(Math.max(-1, Math.min(1, sample)) * 32767));
      cursor = end;
    }
    const consumed = Math.floor(cursor);
    this.position = cursor - consumed;
    this.carry = data.slice(consumed);
    return Int16Array.from(output);
  }
}

async function connectLiveTranscription() {
  const socketScheme = location.protocol === 'https:' ? 'wss:' : 'ws:';
  const socket = new WebSocket(`${socketScheme}//${location.host}/api/transcribe/stream`);
  socket.binaryType = 'arraybuffer';
  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      socket.close();
      reject(new Error('اتصال زنده‌ی تشخیص گفتار دیر پاسخ داد.'));
    }, 5000);
    socket.addEventListener('open', () => {
      clearTimeout(timeout);
      resolve();
    }, { once: true });
    socket.addEventListener('error', () => {
      clearTimeout(timeout);
      reject(new Error('اتصال زنده‌ی تشخیص گفتار برقرار نشد.'));
    }, { once: true });
  });
  liveTranscriptionSocket = socket;
  socket.addEventListener('message', event => {
    let payload;
    try { payload = JSON.parse(event.data); } catch { return; }
    if (payload.type === 'partial' && payload.text) {
      updateLiveTranscriptPreview(payload.text);
      setVoiceStatus('در حال تشخیص گفتار؛ پیش‌نویس موقت است و متن نهایی بعد از توقف می‌آید.');
    } else if (payload.type === 'error') {
      setVoiceStatus(`پیش‌نمایش زنده قطع شد؛ متن نهایی بعد از توقف تشخیص داده می‌شود: ${payload.message}`);
    }
  });
  socket.addEventListener('close', () => {
    if (liveTranscriptionSocket === socket) liveTranscriptionSocket = null;
    if (recorder?.state === 'recording') {
      setVoiceStatus('پیش‌نمایش زنده قطع شد؛ متن نهایی بعد از توقف ضبط همچنان انجام می‌شود.');
    }
  });
  return socket;
}

async function startLiveAudioCapture(stream, socket) {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) throw new Error('مرورگر ورودی صوت زنده را پشتیبانی نمی‌کند.');
  if (socket.readyState !== WebSocket.OPEN) throw new Error('اتصال مکالمه‌ی زنده پیش از فعال‌شدن میکروفون قطع شد.');
  recordingAudioContext = new AudioContextClass();
  const audioContext = recordingAudioContext;
  const resampler = new StreamingPcm16Resampler(audioContext.sampleRate);
  await audioContext.resume();
  const source = audioContext.createMediaStreamSource(stream);
  recordingAudioProcessor = audioContext.createScriptProcessor(4096, 1, 1);
  recordingAudioSilenceGain = audioContext.createGain();
  recordingAudioSilenceGain.gain.value = 0;
  recordingAudioProcessor.onaudioprocess = event => {
    if (socket.readyState !== WebSocket.OPEN) return;
    const pcm = resampler.process(event.inputBuffer.getChannelData(0));
    if (pcm.length) socket.send(pcm.buffer);
  };
  source.connect(recordingAudioProcessor);
  recordingAudioProcessor.connect(recordingAudioSilenceGain);
  recordingAudioSilenceGain.connect(recordingAudioContext.destination);
}

async function stopLiveAudioCapture(socketToClose = undefined) {
  recordingAudioProcessor?.disconnect();
  recordingAudioSilenceGain?.disconnect();
  recordingAudioProcessor = null;
  recordingAudioSilenceGain = null;
  if (recordingAudioContext) {
    try { await recordingAudioContext.close(); } catch { /* already closed */ }
    recordingAudioContext = null;
  }
  const socket = socketToClose === undefined ? liveTranscriptionSocket : socketToClose;
  if (socket === liveTranscriptionSocket) liveTranscriptionSocket = null;
  if (socket === liveConversationSocket) liveConversationSocket = null;
  if (socket && socket.readyState < WebSocket.CLOSING) {
    if (socket.readyState === WebSocket.OPEN) socket.send(JSON.stringify({ type: 'stop' }));
    socket.close();
  }
}

function interruptLiveTurn() {
  if (activeTurnController && !activeTurnController.signal.aborted) {
    activeTurnController.abort();
  }
  if (activeStreamPlayer) {
    const player = activeStreamPlayer;
    activeStreamPlayer = null;
    return player.stop().catch(() => {});
  }
  return Promise.resolve();
}

function queueLiveTurn(text) {
  const utterance = String(text || '').trim();
  if (!utterance || !liveConversationActive) return;
  liveTurnQueue.push(utterance);
  dispatchLiveTurn();
}

async function dispatchLiveTurn() {
  if (!liveConversationActive || busy || liveTurnDispatching || !liveTurnQueue.length) return;
  liveTurnDispatching = true;
  const text = liveTurnQueue.shift();
  try {
    await sendMessage(text, { live: true });
  } finally {
    liveTurnDispatching = false;
    if (liveTurnQueue.length) dispatchLiveTurn();
  }
}

async function connectLiveConversation() {
  const socketScheme = location.protocol === 'https:' ? 'wss:' : 'ws:';
  const socket = new WebSocket(`${socketScheme}//${location.host}/api/conversation/stream`);
  socket.binaryType = 'arraybuffer';
  liveConversationSocket = socket;
  socket.addEventListener('message', event => {
    let payload;
    try { payload = JSON.parse(event.data); } catch { return; }
    if (payload.type === 'ready') {
      setVoiceStatus('مکالمه‌ی زنده وصل شد؛ صحبت کن. بعد از مکث کوتاه، پاسخ شروع می‌شود.');
    } else if (payload.type === 'speech_start') {
      liveSpeechActive = true;
      clearLiveTranscriptPreview();
      interruptLiveTurn();
      setVoiceStatus('صدای شما دریافت شد؛ پاسخ قبلی قطع شد و دارم گوش می‌دهم…');
    } else if (payload.type === 'partial') {
      updateLiveTranscriptPreview(payload.text);
      setVoiceStatus('در حال شنیدن؛ پیش‌نویس موقت است و پس از مکث، متن نهایی به مدل می‌رود…');
    } else if (payload.type === 'final') {
      liveSpeechActive = false;
      clearLiveTranscriptPreview();
      const transcript = String(payload.text || '').trim();
      if (transcript) {
        setVoiceStatus(`گفتار تشخیص داده شد (${payload.model || 'STT'}). در حال پاسخ…`);
        queueLiveTurn(transcript);
      } else {
        setVoiceStatus('گفتاری تشخیص داده نشد؛ دوباره بگو.');
      }
    } else if (payload.type === 'error') {
      setVoiceStatus(`تشخیص زنده گفتار خطا داد: ${payload.message}`);
    }
  });
  socket.addEventListener('close', () => {
    if (liveConversationSocket !== socket) return;
    liveConversationSocket = null;
    if (liveConversationActive) {
      liveConversationActive = false;
      liveSpeechActive = false;
      clearLiveTranscriptPreview();
      interruptLiveTurn();
      liveTurnQueue.length = 0;
      liveConversationStream?.getTracks().forEach(track => track.stop());
      liveConversationStream = null;
      stopLiveAudioCapture(null).catch(() => {});
      liveOutputAudioContext?.close().catch(() => {});
      liveOutputAudioContext = null;
      setVoiceStatus('اتصال مکالمه‌ی زنده قطع شد؛ دوباره شروعش کن.');
      refreshControls();
    }
  });

  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      socket.close();
      reject(new Error('اتصال مکالمه‌ی زنده دیر پاسخ داد.'));
    }, 5000);
    socket.addEventListener('open', () => {
      clearTimeout(timeout);
      resolve();
    }, { once: true });
    socket.addEventListener('error', () => {
      clearTimeout(timeout);
      reject(new Error('اتصال مکالمه‌ی زنده برقرار نشد.'));
    }, { once: true });
    socket.addEventListener('close', () => {
      clearTimeout(timeout);
      reject(new Error('سرور اتصال مکالمه‌ی زنده را بست.'));
    }, { once: true });
  });
  return socket;
}

async function startLiveMode() {
  try {
    clearLiveTranscriptPreview();
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) throw new Error('مرورگر پخش صوت زنده را پشتیبانی نمی‌کند.');
    liveOutputAudioContext = new AudioContextClass();
    const outputReady = liveOutputAudioContext.resume();
    liveConversationStream = await navigator.mediaDevices.getUserMedia({
      audio: {
        ...(microphoneSelect.value ? { deviceId: { exact: microphoneSelect.value } } : {}),
        channelCount: 1,
        echoCancellation: micEnhancement.checked,
        noiseSuppression: micEnhancement.checked,
        autoGainControl: micEnhancement.checked
      }
    });
    const audioTrack = liveConversationStream.getAudioTracks()[0];
    await refreshMicrophoneOptions(audioTrack?.getSettings?.().deviceId || '');
    const socket = await connectLiveConversation();
    liveConversationActive = true;
    liveSpeechActive = false;
    await outputReady;
    await startLiveAudioCapture(liveConversationStream, socket);
    refreshControls();
    setVoiceStatus('مکالمه‌ی زنده فعال است؛ صحبت کن. برای پایان، همین دکمه را بزن.');
  } catch (error) {
    liveConversationActive = false;
    liveConversationStream?.getTracks().forEach(track => track.stop());
    liveConversationStream = null;
    await stopLiveAudioCapture(liveConversationSocket);
    liveConversationSocket = null;
    try { await liveOutputAudioContext?.close(); } catch { /* already closed */ }
    liveOutputAudioContext = null;
    refreshControls();
    setVoiceStatus(`شروع مکالمه‌ی زنده ناموفق بود: ${error.message}`);
  }
}

async function stopLiveMode() {
  liveConversationActive = false;
  liveSpeechActive = false;
  clearLiveTranscriptPreview();
  liveTurnQueue.length = 0;
  await interruptLiveTurn();
  const socket = liveConversationSocket;
  liveConversationSocket = null;
  await stopLiveAudioCapture(socket);
  liveConversationStream?.getTracks().forEach(track => track.stop());
  liveConversationStream = null;
  try { await liveOutputAudioContext?.close(); } catch { /* already closed */ }
  liveOutputAudioContext = null;
  refreshControls();
  setVoiceStatus('مکالمه‌ی زنده پایان یافت.');
}

async function toggleLiveMode() {
  if (liveConversationActive) await stopLiveMode();
  else await startLiveMode();
}

async function refreshMicrophoneOptions(activeDeviceId = '') {
  if (!navigator.mediaDevices?.enumerateDevices || !microphoneSelect) return;
  try {
    const devices = (await navigator.mediaDevices.enumerateDevices())
      .filter(device => device.kind === 'audioinput' && device.deviceId);
    const selectedDeviceId = activeDeviceId || microphoneSelect.value;
    microphoneSelect.replaceChildren(new Option('میکروفون پیش‌فرض', ''));
    devices.forEach((device, index) => {
      const label = device.label || `میکروفون ${index + 1}`;
      microphoneSelect.add(new Option(label, device.deviceId));
    });
    if (devices.some(device => device.deviceId === selectedDeviceId)) {
      microphoneSelect.value = selectedDeviceId;
    }
    microphoneSelect.disabled = devices.length <= 1;
  } catch {
    microphoneSelect.disabled = true;
  }
}

async function toggleRecording() {
  if (recorder?.state === 'recording') {
    recorder.stop();
    recordButton.classList.remove('recording');
    recordButton.setAttribute('aria-label', 'ضبط پیام صوتی');
    busy = true;
    refreshControls();
    setVoiceStatus('ضبط تمام شد؛ متن نهایی با موتور تشخیص دقیق می‌شود…');
    return;
  }

  try {
    clearLiveTranscriptPreview();
    recordingStream = await navigator.mediaDevices.getUserMedia({
      audio: {
        ...(microphoneSelect.value ? { deviceId: { exact: microphoneSelect.value } } : {}),
        channelCount: 1,
        echoCancellation: micEnhancement.checked,
        noiseSuppression: micEnhancement.checked,
        autoGainControl: micEnhancement.checked
      }
    });
    const audioTrack = recordingStream.getAudioTracks()[0];
    recordingMicrophoneLabel = audioTrack?.label || microphoneSelect.selectedOptions[0]?.textContent || 'میکروفون پیش‌فرض';
    await refreshMicrophoneOptions(audioTrack?.getSettings?.().deviceId || '');
    let liveSocket = null;
    try {
      liveSocket = await connectLiveTranscription();
    } catch (error) {
      setVoiceStatus(`${error.message} ضبط ادامه دارد و متن بعد از توقف تبدیل می‌شود.`);
    }
    const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
      ? 'audio/webm;codecs=opus'
      : undefined;
    recorder = new MediaRecorder(recordingStream, mimeType ? { mimeType } : undefined);
    audioChunks = [];
    recorder.addEventListener('dataavailable', event => {
      if (event.data?.size) audioChunks.push(event.data);
    });
    recorder.addEventListener('stop', async () => {
      await stopLiveAudioCapture();
      clearLiveTranscriptPreview();
      recordingStream?.getTracks().forEach(track => track.stop());
      recordingStream = null;
      recordButton.classList.remove('recording');
      recordButton.setAttribute('aria-label', 'ضبط پیام صوتی');
      const audioBlob = new Blob(audioChunks, { type: recorder.mimeType || 'audio/webm' });
      audioChunks = [];
      if (!audioBlob.size) {
        setVoiceStatus('صدایی ضبط نشد. دوباره تلاش کن.');
        busy = false;
        refreshControls();
        return;
      }
      appendRecordedAudio(audioBlob);
      busy = true;
      refreshControls();
      setVoiceStatus('در حال نهایی‌کردن متن گفتار فارسی…');
      try {
        const recognition = await transcribeRecording(audioBlob);
        if (!recognition.text) throw new Error('گفتاری تشخیص داده نشد.');
        messageInput.value = recognition.text;
        busy = false;
        refreshControls();
        const quality = describeRecordingQuality(recognition.audio);
        setVoiceStatus(`با ${recognition.model} تشخیص داده شد؛ ورودی: ${recordingMicrophoneLabel}. ${quality}. متن را بررسی/اصلاح کن و بعد «ارسال» را بزن.`);
        messageInput.focus();
      } catch (error) {
        clearLiveTranscriptPreview();
        appendMessage('assistant', `تبدیل گفتار ناموفق بود: ${error.message}`, true);
        setVoiceStatus('صدای ضبط‌شده را در بالا گوش کن؛ گفتار به مدل ارسال نشد.');
        busy = false;
        refreshControls();
      }
    }, { once: true });
    recorder.start();
    if (liveSocket) {
      try {
        await startLiveAudioCapture(recordingStream, liveSocket);
      } catch (error) {
        setVoiceStatus(`پیش‌نمایش زنده فعال نشد؛ تشخیص نهایی بعد از توقف انجام می‌شود: ${error.message}`);
      }
    }
    messageInput.disabled = true;
    sendButton.disabled = true;
    recordButton.classList.add('recording');
    recordButton.setAttribute('aria-label', 'پایان ضبط');
    if (liveSocket?.readyState === WebSocket.OPEN) {
      setVoiceStatus('در حال ضبط؛ پیش‌نمایش متن حین صحبت ظاهر می‌شود. برای پایان دوباره دکمه را بزن.');
    } else {
      setVoiceStatus('در حال ضبط؛ برای پایان دوباره دکمه را بزن.');
    }
  } catch (error) {
    clearLiveTranscriptPreview();
    await stopLiveAudioCapture();
    recordingStream?.getTracks().forEach(track => track.stop());
    recordingStream = null;
    refreshControls();
    setVoiceStatus(`دسترسی به میکروفون ممکن نشد: ${error.message}`);
  }
}

document.querySelector('#load-models').addEventListener('click', loadModels);
modelSelect.addEventListener('change', refreshControls);
tokenInput.addEventListener('input', () => {
  activeToken = '';
  refreshControls();
});
baseUrlInput.addEventListener('input', () => {
  activeToken = '';
  setConnectionStatus('نشانی API تغییر کرد؛ دوباره فهرست مدل‌ها را بگیر.');
  refreshControls();
});
document.querySelector('#send-button').addEventListener('click', () => sendMessage(messageInput.value));
messageInput.addEventListener('keydown', event => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    sendMessage(messageInput.value);
  }
});
recordButton.addEventListener('click', toggleRecording);
liveModeButton?.addEventListener('click', toggleLiveMode);
microphoneSelect.addEventListener('change', () => {
  const name = microphoneSelect.selectedOptions[0]?.textContent || 'میکروفون پیش‌فرض';
  setVoiceStatus(`ورودی ضبط بعدی: ${name}.`);
});
document.querySelector('#clear-chat').addEventListener('click', () => {
  conversation = [];
  for (const url of audioUrls) URL.revokeObjectURL(url);
  audioUrls.clear();
  chatLog.replaceChildren(welcomeMessage.cloneNode(true));
  setVoiceStatus('گفت‌وگو پاک شد.');
});
document.querySelector('#settings-toggle').addEventListener('click', event => {
  const body = document.querySelector('#settings-body');
  const expanded = event.currentTarget.getAttribute('aria-expanded') === 'true';
  event.currentTarget.setAttribute('aria-expanded', String(!expanded));
  body.hidden = expanded;
  event.currentTarget.textContent = expanded ? 'بازکردن' : 'جمع‌کردن';
});

fetch('/api/status')
  .then(response => response.json())
  .then(status => {
    if (localEngineLabel) {
      localEngineLabel.textContent = `موتور محلی: ${status.stt_model || 'نامشخص'}`;
    }
    const sttReady = status.stt_engine === 'taraneh' || (
      status.stt_model_present
      && status.stt_config_present
      && status.stt_tokenizer_present
      && status.stt_vocabulary_present
    );
    const ttsReady = status.tts_model_present
      && status.tts_config_present
      && status.tts_espeak_bridge_present
      && status.tts_espeak_data_present;
    if (!sttReady) {
      setVoiceStatus('فایل‌های مدل گفتار کامل نیستند.');
    } else if (status.taraneh_model_present && !status.taraneh_dependencies_present) {
      setVoiceStatus('وزن Taraneh پیدا شد، اما بسته‌های اختیاری آن نصب نیست؛ فعلاً Whisper اجرا می‌شود.');
    } else if (!ttsReady) {
      setVoiceStatus('نصب Piper کامل نیست؛ بسته‌ی Piper باید از مخزن کلون‌شده نصب شود.');
    }
    if (mossStatus && status.moss) {
      const moss = status.moss;
      mossStatus.textContent = moss.ready
        ? 'MOSS آماده است. صدای پیش‌فرض فارسی Mana در دسترس است.'
        : (moss.available ? `MOSS در حال آماده‌شدن است: ${moss.status}` : moss.status);
      mossStatus.dataset.kind = moss.ready ? 'success' : (moss.available ? '' : 'error');
    }
  })
  .catch(() => setVoiceStatus('سرویس محلی گفتار در دسترس نیست.'));

ttsEngineSelect?.addEventListener('change', () => {
  updateTtsEngineSettings();
  refreshMossStatus();
});
updateTtsEngineSettings();
refreshControls();

window.addEventListener('beforeunload', () => {
  for (const url of audioUrls) URL.revokeObjectURL(url);
});
