(function exposeSpeechText(root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  root.PersianSpeechText = api;
})(typeof globalThis === 'undefined' ? this : globalThis, function createSpeechTextTools() {
  const SENTENCE_END = /[.!?؟؛…]/u;
  const SENTENCE_BOUNDARY = /[.!?؟؛…](?=\s|$)/u;
  const WORD = /[\p{L}\p{N}]+(?:‌[\p{L}\p{N}]+)*/gu;
  const DELIVERY_PROFILES = Object.freeze({
    warm: Object.freeze({ id: 'warm', label: 'گرم و صمیمی' }),
    business: Object.freeze({ id: 'business', label: 'حرفه‌ای و صمیمی' }),
    empathetic: Object.freeze({ id: 'empathetic', label: 'همدلانه' }),
    reassuring: Object.freeze({ id: 'reassuring', label: 'آرامش‌بخش' }),
    upbeat: Object.freeze({ id: 'upbeat', label: 'پرانرژی' })
  });
  const REASSURANCE_CUES = Object.freeze([
    'نگران', 'می ترسم', 'ترسیدم', 'استرس دارم', 'مضطربم', 'دلهره دارم', 'وحشت دارم'
  ]);
  const EMPATHY_CUES = Object.freeze([
    'ناراحت', 'غمگین', 'کلافه', 'عصبانی', 'ناامید', 'حالم بده', 'خسته شدم', 'داغون',
    'اذیت شدم', 'خیلی سخته برام'
  ]);
  const UPBEAT_CUES = Object.freeze([
    'تبریک', 'موفق شدم', 'قبول شدم', 'بالاخره درست شد', 'عالی شد', 'چه خبر خوبی',
    'خوشحالم', 'فوق العاده شد', 'بردیم', 'جواب داد'
  ]);
  const BUSINESS_CUES = Object.freeze([
    'حسابداری', 'صورت حساب', 'صورتحساب', 'مانده حساب', 'فاکتور', 'تسویه', 'پرداخت',
    'دریافت', 'چک', 'مغایرت', 'مالیات', 'مشتری', 'فروش', 'تامین کننده', 'تأمین کننده', 'موجودی',
    'سفارش', 'محصول', 'قیمت گذاری', 'شعبه', 'پنل سایت', 'سایت', 'فروشگاه', 'سبد خرید',
    'انبار', 'درگاه پرداخت'
  ]);
  const BUSINESS_ISSUE_CUES = Object.freeze([
    'مغایرت حساب', 'فاکتور اشتباه', 'پرداخت ناموفق', 'پرداخت عقب افتاده', 'تسویه نشده',
    'چک برگشتی', 'حساب بدهکار', 'کسری حساب', 'واریز نشده', 'جریمه مالیاتی',
    'موجودی اشتباه', 'خطای سایت', 'سایت خراب', 'مشکل درگاه', 'درگاه کار نمی کند',
    'قیمت اشتباه', 'سفارش اشتباه', 'حساب مشتری به هم ریخته', 'ثبت اشتباه فاکتور'
  ]);
  const RESOLVED_CUES = Object.freeze([
    'مشکل حل شد', 'خطا رفع شد', 'مغایرت حل شد', 'فاکتور درست شد', 'فاکتور صادر شد',
    'درگاه وصل شد', 'سایت بالا اومد', 'پرداخت موفق شد', 'تسویه انجام شد', 'حساب تسویه شد',
    'سفارش ثبت شد', 'موجودی تکمیل شد'
  ]);

  function normalizeDeliveryText(value) {
    return String(value || '')
      .normalize('NFKC')
      .replace(/[\u064B-\u065F\u0670\u0640]/gu, '')
      .replace(/[يى]/gu, 'ی')
      .replace(/ك/gu, 'ک')
      .replace(/[\u200c\u200d]/gu, ' ')
      .replace(/[؟!?.,،؛:()[\]«»"']/gu, ' ')
      .replace(/\s+/gu, ' ')
      .trim()
      .toLocaleLowerCase('fa');
  }

  function hasUnnegatedCue(text, cues) {
    for (const cue of cues) {
      let offset = 0;
      while (offset < text.length) {
        const index = text.indexOf(cue, offset);
        if (index < 0) break;
        const before = text.slice(Math.max(0, index - 8), index);
        const after = text.slice(index + cue.length, index + cue.length + 14);
        const negatedBefore = /ن(?:می)?\s*$/u.test(before);
        const negatedAfter = /^\s*(?:نیست(?:م|ی|یم|ند)?|نبود(?:م|ی|یم|ند)?|نخواهم|نمی(?:\s+|$))/u.test(after);
        if (!negatedBefore && !negatedAfter) return true;
        offset = index + cue.length;
      }
    }
    return false;
  }

  function inferDeliveryProfile(userText) {
    const text = normalizeDeliveryText(userText);
    if (hasUnnegatedCue(text, REASSURANCE_CUES)) return DELIVERY_PROFILES.reassuring;
    if (hasUnnegatedCue(text, EMPATHY_CUES)) return DELIVERY_PROFILES.empathetic;
    if (hasUnnegatedCue(text, BUSINESS_ISSUE_CUES) && !hasUnnegatedCue(text, RESOLVED_CUES)) {
      return DELIVERY_PROFILES.reassuring;
    }
    if (hasUnnegatedCue(text, UPBEAT_CUES) || hasUnnegatedCue(text, RESOLVED_CUES)) {
      return DELIVERY_PROFILES.upbeat;
    }
    if (hasUnnegatedCue(text, BUSINESS_CUES)) return DELIVERY_PROFILES.business;
    return DELIVERY_PROFILES.warm;
  }

  function hasSpeakableContent(value) {
    return /[\p{L}\p{N}]/u.test(String(value || ''));
  }

  function wordCount(value) {
    return [...String(value || '').matchAll(WORD)].length;
  }

  function normalizePunctuation(value) {
    return String(value || '')
      .replace(/\.{3,}|…+/gu, '…')
      .replace(/[!?؟]{2,}/gu, marks => (marks.includes('?') || marks.includes('؟') ? '؟' : '!'))
      .replace(/([!?؟])\1+/gu, '$1')
      .replace(/([.!?؟؛،])(?:\s*\1)+/gu, '$1')
      .replace(/[ \t]+([،؛:])/gu, '$1')
      .replace(/([،؛:])(?=\S)/gu, '$1 ')
      .replace(/[ \t]{2,}/gu, ' ')
      .trim();
  }

  function joinLinesWithSpeechPauses(value) {
    const lines = String(value || '')
      .replace(/\r\n?/gu, '\n')
      .split(/\n+/u)
      .map(line => line.trim())
      .filter(Boolean);
    if (!lines.length) return '';

    let text = lines[0];
    for (const line of lines.slice(1)) {
      text += SENTENCE_END.test(text.slice(-1)) ? ` ${line}` : `. ${line}`;
    }
    return text;
  }

  function cleanForSpeech(rawText) {
    let text = String(rawText || '');
    text = text.replace(/```[\s\S]*?```/gu, '');
    text = text.replace(/!\[([^\]]*)\]\([^)]+\)/gu, '$1');
    text = text.replace(/\[([^\]]+)\]\([^)]+\)/gu, '$1');
    text = text.replace(/https?:\/\/\S+/giu, '');
    text = text.replace(/^\s*\[[ xX]\]\s*/gmu, '');
    text = text.replace(/^\s*(?:[-*+•‣–—]|[\d۰-۹٠-٩]+[.)])\s+/gmu, '');
    text = text.replace(/^\s{0,3}#{1,6}\s*/gmu, '');
    text = text.replace(/^\s*>\s?/gmu, '');
    text = text.replace(/(\*\*|__)(.*?)\1/gu, '$2');
    text = text.replace(/(\*|_)([^\n]*?)\1/gu, '$2');
    text = text.replace(/~~(.*?)~~/gu, '$1');
    text = text.replace(/`+/gu, '');
    text = text.replace(/[\\*_#~]/gu, '');
    text = normalizePunctuation(joinLinesWithSpeechPauses(text));
    return text;
  }

  function firstBoundary(text) {
    const match = SENTENCE_BOUNDARY.exec(text);
    if (!match) return -1;
    let end = match.index + match[0].length;
    while (end < text.length && /[.!?؟؛…]/u.test(text[end])) end += 1;
    return end;
  }

  function nextSpeechSegments(text, flush = false) {
    const segments = [];
    let pending = String(text || '').replace(/^\s+/u, '');

    while (pending.trim()) {
      let end = firstBoundary(pending);
      if (end >= 0) {
        let candidate = pending.slice(0, end).trim();
        let remainder = pending.slice(end).replace(/^\s+/u, '');

        if (!hasSpeakableContent(candidate)) {
          pending = remainder;
          continue;
        }

        // Keep one-word interjections with the following short sentence when
        // streaming has not ended yet; a final one-word answer is still valid.
        while (wordCount(candidate) < 2 && hasSpeakableContent(remainder)) {
          const nextEnd = firstBoundary(remainder);
          if (nextEnd < 0) {
            if (!flush) break;
            candidate = `${candidate} ${remainder}`.trim();
            remainder = '';
            break;
          }
          candidate = `${candidate} ${remainder.slice(0, nextEnd).trim()}`.trim();
          remainder = remainder.slice(nextEnd).replace(/^\s+/u, '');
        }

        if (wordCount(candidate) < 2 && hasSpeakableContent(remainder) && !flush) break;
        if (hasSpeakableContent(candidate)) segments.push(candidate);
        pending = remainder;
        continue;
      }

      // Preserve the existing latency-oriented soft breaks for long unpunctuated
      // clauses. Sentence punctuation remains the preferred boundary.
      if (pending.length > 88) {
        const window = pending.slice(0, 105);
        const clause = Math.max(window.lastIndexOf('،'), window.lastIndexOf('؛'));
        if (clause >= 42 && wordCount(pending.slice(0, clause + 1)) >= 2) {
          end = clause + 1;
        } else if (pending.length > 120) {
          const wordBoundary = window.lastIndexOf(' ');
          if (wordBoundary >= 72 && wordCount(pending.slice(0, wordBoundary)) >= 2) {
            end = wordBoundary;
          }
        }
      }

      if (end < 0) break;
      const candidate = pending.slice(0, end).trim();
      if (hasSpeakableContent(candidate)) segments.push(candidate);
      pending = pending.slice(end).replace(/^\s+/u, '');
    }

    if (flush && hasSpeakableContent(pending)) {
      const finalSegment = pending.trim();
      if (hasSpeakableContent(finalSegment)) segments.push(finalSegment);
      pending = '';
    }

    return { segments, pending };
  }

  return {
    cleanForSpeech,
    hasSpeakableContent,
    inferDeliveryProfile,
    nextSpeechSegments,
    normalizePunctuation,
    wordCount
  };
});
