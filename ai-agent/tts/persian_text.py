"""Conservative Persian text normalization for Piper's eSpeak phonemizer."""

from __future__ import annotations

import re
import unicodedata
from collections.abc import Mapping
from datetime import date


DEFAULT_PRONUNCIATION_LEXICON: dict[str, str] = {
    # Product/model identifiers are spoken as codes, not as ordinary amounts.
    "KTS 1880": "کِی‌تی‌اِس، هِجده هَشتاد",
    "محصول KTS 1880": "مَحصولِ کِی‌تی‌اِس، هِجده هَشتاد",
    # Common words in the current store/accounting voice flow.
    "دارم": "دارَم",
    "محصول": "مَحصول",
    "بارگذاری": "بارگُذاری",
    "اطلاعاتش": "اِطّلاعاتش",
    "ثبت می‌کنم": "ثَبت می‌کنم",
    "فهرست محصولاتت": "فِهرِستِ محصولاتت",
    "اضافه بشه": "اضافه بِشِه",
}

_DIGITS = str.maketrans(
    "۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩",
    "01234567890123456789",
)
_LETTERS = str.maketrans({"ك": "ک", "ي": "ی", "ى": "ی"})

_ONES = (
    "صفر", "یک", "دو", "سه", "چهار", "پنج", "شش", "هفت", "هشت", "نه",
    "ده", "یازده", "دوازده", "سیزده", "چهارده", "پانزده", "شانزده",
    "هفده", "هجده", "نوزده",
)
_TENS = {
    20: "بیست", 30: "سی", 40: "چهل", 50: "پنجاه", 60: "شصت",
    70: "هفتاد", 80: "هشتاد", 90: "نود",
}
_HUNDREDS = {
    100: "صد", 200: "دویست", 300: "سیصد", 400: "چهارصد", 500: "پانصد",
    600: "ششصد", 700: "هفتصد", 800: "هشتصد", 900: "نهصد",
}
_SCALES = ("", "هزار", "میلیون", "میلیارد", "تریلیون")
_PERSIAN_MONTHS = (
    "", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
    "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
)
_GREGORIAN_MONTHS = (
    "", "ژانویه", "فوریه", "مارس", "آوریل", "مه", "ژوئن", "ژوئیه",
    "اوت", "سپتامبر", "اکتبر", "نوامبر", "دسامبر",
)
_ORDINALS = {
    1: "اول", 2: "دوم", 3: "سوم", 4: "چهارم", 5: "پنجم", 6: "ششم",
    7: "هفتم", 8: "هشتم", 9: "نهم", 10: "دهم", 11: "یازدهم",
    12: "دوازدهم", 13: "سیزدهم", 14: "چهاردهم", 15: "پانزدهم",
    16: "شانزدهم", 17: "هفدهم", 18: "هجدهم", 19: "نوزدهم", 20: "بیستم",
    30: "سی‌ام",
}
_ORDINAL_UNITS = {1: "یکم", 2: "دوم", 3: "سوم", 4: "چهارم", 5: "پنجم", 6: "ششم", 7: "هفتم", 8: "هشتم", 9: "نهم"}
_NUMBER = r"-?(?:\d{1,3}(?:[,٬]\d{3})+|\d+)(?:[.٫]\d+)?"
_TIME_RE = re.compile(r"(?<!\w)([01]?\d|2[0-3]):([0-5]\d)(?!\w)")
_ISO_DATE_RE = re.compile(r"(?<!\w)(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?!\w)")
_IRANIAN_DATE_RE = re.compile(r"(?<!\w)(\d{1,2})[/](\d{1,2})/(1[2345]\d{2})(?!\w)")
_PERCENT_RE = re.compile(rf"(?<!\w)(?P<number>{_NUMBER})\s*(?:%|٪)(?!\w)")
_UNIT_RE = re.compile(
    rf"(?<!\w)(?P<number>{_NUMBER})\s*(?P<unit>"
    r"تومان|ریال|کیلوگرم|کیلو|گرم|کیلومتر|متر|سانتی‌متر|میلی‌متر|"
    r"لیتر|میلی‌لیتر|ثانیه|دقیقه|ساعت|روز|سال|نفر|عدد|"
    r"(?:km|kg|cm|mm|ml|m|g|KB|MB|GB)"
    r")(?!\w)",
    re.IGNORECASE,
)
_GENERIC_NUMBER_RE = re.compile(rf"(?<!\w)(?P<number>{_NUMBER})(?!\w)")

_UNIT_WORDS = {
    "km": "کیلومتر", "kg": "کیلوگرم", "cm": "سانتی‌متر", "mm": "میلی‌متر",
    "ml": "میلی‌لیتر", "m": "متر", "g": "گرم", "kb": "کیلوبایت",
    "mb": "مگابایت", "gb": "گیگابایت",
}


def has_speakable_content(text: str) -> bool:
    """Return whether text contains a Unicode letter or number."""
    return any(unicodedata.category(char)[0] in {"L", "N"} for char in text)


def _under_thousand(number: int) -> str:
    if number < 20:
        return _ONES[number]
    if number < 100:
        tens = (number // 10) * 10
        remainder = number % 10
        return _TENS[tens] if not remainder else f"{_TENS[tens]} و {_ONES[remainder]}"

    hundreds = (number // 100) * 100
    remainder = number % 100
    return _HUNDREDS[hundreds] if not remainder else f"{_HUNDREDS[hundreds]} و {_under_thousand(remainder)}"


def integer_to_persian_words(number: int) -> str:
    if number < 0:
        return f"منفی {integer_to_persian_words(-number)}"
    if number < 1000:
        return _under_thousand(number)

    groups: list[str] = []
    scale_index = 0
    while number:
        group = number % 1000
        if group:
            if scale_index >= len(_SCALES):
                raise ValueError("عدد از محدودهٔ قابل‌خواندن پشتیبانی‌شده بزرگ‌تر است.")
            scale = _SCALES[scale_index]
            if scale_index == 1 and group == 1:
                groups.append(scale)
            else:
                phrase = _under_thousand(group)
                groups.append(f"{phrase} {scale}".strip())
        number //= 1000
        scale_index += 1
    return " و ".join(reversed(groups))


def _number_words(value: str) -> str:
    cleaned = value.replace(",", "").replace("٬", "")
    cleaned = cleaned.replace("٫", ".")
    negative = cleaned.startswith("-")
    if negative:
        cleaned = cleaned[1:]

    if "." in cleaned:
        whole, fractional = cleaned.split(".", 1)
        phrase = f"{integer_to_persian_words(int(whole or '0'))} ممیز {' '.join(_ONES[int(digit)] for digit in fractional)}"
    else:
        phrase = integer_to_persian_words(int(cleaned))
    return f"منفی {phrase}" if negative else phrase


def _ordinal(day: int) -> str:
    if day in _ORDINALS:
        return _ORDINALS[day]
    tens = (day // 10) * 10
    units = day % 10
    tens_ordinal = {20: "بیستم", 30: "سی‌ام"}.get(tens)
    if not tens_ordinal:
        return integer_to_persian_words(day)
    if not units:
        return tens_ordinal
    return f"{_TENS[tens]} و {_ORDINAL_UNITS[units]}"


def _replace_date(match: re.Match[str]) -> str:
    year, month, day = (int(part) for part in match.groups())
    if 1300 <= year <= 1599 and 1 <= month <= 12 and 1 <= day <= (31 if month <= 6 else 30 if month <= 11 else 29):
        month_name = _PERSIAN_MONTHS[month]
    elif 1900 <= year <= 2199:
        try:
            date(year, month, day)
        except ValueError:
            return match.group(0)
        month_name = _GREGORIAN_MONTHS[month]
    else:
        return match.group(0)
    return f"{_ordinal(day)} {month_name} {integer_to_persian_words(year)}"


def _replace_iranian_date(match: re.Match[str]) -> str:
    day, month, year = (int(part) for part in match.groups())
    if month < 1 or month > 12 or day < 1 or day > (31 if month <= 6 else 30 if month <= 11 else 29):
        return match.group(0)
    return f"{_ordinal(day)} {_PERSIAN_MONTHS[month]} {integer_to_persian_words(year)}"


def _replace_time(match: re.Match[str]) -> str:
    hour, minute = (int(part) for part in match.groups())
    prefix = match.string[: match.start()].rstrip()
    hour_words = integer_to_persian_words(hour)
    if minute:
        time_words = f"{hour_words} و {integer_to_persian_words(minute)} دقیقه"
    else:
        time_words = hour_words
    if prefix.endswith("ساعت"):
        return time_words
    return f"ساعت {time_words}"


def apply_pronunciation_lexicon(text: str, lexicon: Mapping[str, str] | None = None) -> str:
    """Apply whole-word pronunciation overrides, preferring longer phrases."""
    for source, replacement in sorted((lexicon or {}).items(), key=lambda item: len(item[0]), reverse=True):
        if not source:
            continue
        flags = re.IGNORECASE if re.search(r"[A-Za-z]", source) else 0
        pattern = re.compile(rf"(?<![\w\u200c]){re.escape(source)}(?![\w\u200c])", flags)
        text = pattern.sub(lambda _match, replacement=replacement: replacement, text)
    return text


def normalize_persian_for_piper(
    text: str,
    *,
    pronunciation_lexicon: Mapping[str, str] | None = None,
) -> str:
    """Normalize orthography and common numeric contexts without rewriting wording."""
    value = unicodedata.normalize("NFC", str(text or ""))
    value = value.translate(_LETTERS).translate(_DIGITS)
    value = value.replace("\u00a0", " ").replace("\u202f", " ")
    # Resolve known product codes before generic number-to-word conversion.
    value = apply_pronunciation_lexicon(value, DEFAULT_PRONUNCIATION_LEXICON)
    value = apply_pronunciation_lexicon(value, pronunciation_lexicon)
    value = _ISO_DATE_RE.sub(_replace_date, value)
    value = _IRANIAN_DATE_RE.sub(_replace_iranian_date, value)
    value = _TIME_RE.sub(_replace_time, value)
    value = _PERCENT_RE.sub(lambda match: f"{_number_words(match.group('number'))} درصد", value)

    def replace_unit(match: re.Match[str]) -> str:
        unit = match.group("unit")
        unit_word = _UNIT_WORDS.get(unit.lower(), unit)
        return f"{_number_words(match.group('number'))} {unit_word}"

    value = _UNIT_RE.sub(replace_unit, value)
    value = _GENERIC_NUMBER_RE.sub(lambda match: _number_words(match.group("number")), value)
    value = apply_pronunciation_lexicon(value, pronunciation_lexicon)
    value = re.sub(r"[ \t]+", " ", value)
    value = re.sub(r" *\n *", "\n", value)
    return value.strip()
