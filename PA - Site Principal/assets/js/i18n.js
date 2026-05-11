const I18N_STORAGE_KEY = 'locale';
const I18N_DEFAULT_LOCALE = 'en';
const LANGUAGE_LIST_ROUTE = '/pages/common/language-list.php';
const TRANSLATION_FILE_ROUTE = '/pages/common/translation-file.php';

async function fetchJson(path) {
  const response = await fetch(path, { headers: { 'Accept': 'application/json' } });
  if (!response.ok) {
    throw new Error(`Failed to fetch ${path}: ${response.status}`);
  }
  return response.json();
}

function getSavedLocale() {
  return localStorage.getItem(I18N_STORAGE_KEY) || I18N_DEFAULT_LOCALE;
}

function setSavedLocale(locale) {
  localStorage.setItem(I18N_STORAGE_KEY, locale);
}

function getTranslationValue(key, translations, fallback) {
  if (translations && Object.prototype.hasOwnProperty.call(translations, key)) {
    return translations[key];
  }
  if (fallback && Object.prototype.hasOwnProperty.call(fallback, key)) {
    return fallback[key];
  }
  return null;
}

function translateElement(el, translations, fallback) {
  const key = el.dataset.i18n;
  if (!key) {
    return;
  }
  const value = getTranslationValue(key, translations, fallback);
  if (value === null) {
    return;
  }
  if (el.hasAttribute('data-i18n-placeholder')) {
    el.placeholder = value;
  } else if (el.hasAttribute('data-i18n-value')) {
    el.value = value;
  } else if (el.hasAttribute('data-i18n-title')) {
    el.title = value;
  } else if (el.hasAttribute('data-i18n-aria-label')) {
    el.setAttribute('aria-label', value);
  } else if (el.hasAttribute('data-i18n-alt')) {
    el.alt = value;
  } else if (el.hasAttribute('data-i18n-html')) {
    el.innerHTML = value;
  } else {
    el.textContent = value;
  }
}

function translateAttributes(translations, fallback) {
  document.querySelectorAll('[data-i18n]').forEach(el => translateElement(el, translations, fallback));
  document.querySelectorAll('[data-i18n-title]').forEach(el => {
    const key = el.dataset.i18nTitle;
    const value = getTranslationValue(key, translations, fallback);
    if (value !== null) {
      el.title = value;
    }
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.dataset.i18nPlaceholder;
    const value = getTranslationValue(key, translations, fallback);
    if (value !== null) {
      el.placeholder = value;
    }
  });
  document.querySelectorAll('[data-i18n-aria-label]').forEach(el => {
    const key = el.dataset.i18nAriaLabel;
    const value = getTranslationValue(key, translations, fallback);
    if (value !== null) {
      el.setAttribute('aria-label', value);
    }
  });
  document.querySelectorAll('[data-i18n-alt]').forEach(el => {
    const key = el.dataset.i18nAlt;
    const value = getTranslationValue(key, translations, fallback);
    if (value !== null) {
      el.alt = value;
    }
  });
}

function translateTitle(translations, fallback) {
  const titleText = document.title && document.title.trim();
  if (!titleText) {
    return;
  }
  const reverseMap = buildReverseMap(fallback);
  const key = reverseMap[titleText];
  if (!key) {
    return;
  }
  const translated = getTranslationValue(key, translations, fallback);
  if (translated !== null && translated !== titleText) {
    document.title = translated;
  }
}

function buildReverseMap(fallback) {
  const reverseMap = {};
  if (!fallback) {
    return reverseMap;
  }
  Object.keys(fallback).forEach(key => {
    const text = fallback[key];
    if (text && typeof text === 'string') {
      reverseMap[text.trim()] = key;
    }
  });
  return reverseMap;
}

function normalizeTextNode(node) {
  return node.nodeValue ? node.nodeValue.trim() : '';
}

function translateTextNodes(translations, fallback) {
  const reverseMap = buildReverseMap(fallback);
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      if (!node.nodeValue || !node.nodeValue.trim()) {
        return NodeFilter.FILTER_REJECT;
      }
      const parentTag = node.parentNode && node.parentNode.nodeName;
      if (parentTag === 'SCRIPT' || parentTag === 'STYLE' || parentTag === 'NOSCRIPT' || parentTag === 'TEXTAREA') {
        return NodeFilter.FILTER_REJECT;
      }
      return NodeFilter.FILTER_ACCEPT;
    }
  });

  const textNodes = [];
  while (walker.nextNode()) {
    textNodes.push(walker.currentNode);
  }

  textNodes.forEach(node => {
    const trimmed = normalizeTextNode(node);
    if (!trimmed) {
      return;
    }
    const key = reverseMap[trimmed];
    if (!key) {
      return;
    }
    const translated = getTranslationValue(key, translations, fallback);
    if (translated === null || translated === trimmed) {
      return;
    }
    node.nodeValue = node.nodeValue.replace(trimmed, translated);
  });
}

function renderLanguageOptions(languages, currentLocale) {
  const select = document.getElementById('language-select');
  if (!select) {
    return;
  }
  const existing = new Set();
  select.innerHTML = '';
  languages.forEach(lang => {
    if (!lang || !lang.code || existing.has(lang.code)) {
      return;
    }
    const option = document.createElement('option');
    option.value = lang.code;
    option.textContent = lang.name || lang.code;
    option.selected = lang.code === currentLocale;
    select.appendChild(option);
    existing.add(lang.code);
  });
  if (!existing.has(currentLocale)) {
    const fallbackOption = document.createElement('option');
    fallbackOption.value = currentLocale;
    fallbackOption.textContent = currentLocale.toUpperCase();
    fallbackOption.selected = true;
    select.appendChild(fallbackOption);
  }
  select.addEventListener('change', () => {
    const chosen = select.value || I18N_DEFAULT_LOCALE;
    setSavedLocale(chosen);
    window.location.reload();
  });
}

async function initializeI18n() {
  const locale = getSavedLocale();
  let languages = [];
  try {
    languages = await fetchJson(LANGUAGE_LIST_ROUTE);
    if (!Array.isArray(languages)) {
      languages = [];
    }
  } catch (error) {
    console.warn('Unable to load language list', error);
  }

  renderLanguageOptions(languages, locale);

  let fallback = {};
  try {
    fallback = await fetchJson(`${TRANSLATION_FILE_ROUTE}?code=${encodeURIComponent(I18N_DEFAULT_LOCALE)}`);
  } catch (error) {
    console.warn('Unable to load fallback language file', error);
  }

  let translations = fallback;
  if (locale !== I18N_DEFAULT_LOCALE) {
    try {
      translations = await fetchJson(`${TRANSLATION_FILE_ROUTE}?code=${encodeURIComponent(locale)}`);
    } catch (error) {
      console.warn(`Unable to load translations for ${locale}, falling back to ${I18N_DEFAULT_LOCALE}`, error);
      translations = fallback;
    }
  }

  translateAttributes(translations, fallback);
  translateTextNodes(translations, fallback);
  translateTitle(translations, fallback);
  window.currentLocale = locale;
  window.currentTranslations = translations || {};
  window.currentFallback = fallback || {};
  window.translatePage = function () {
    translateAttributes(window.currentTranslations || {}, window.currentFallback || {});
    translateTextNodes(window.currentTranslations || {}, window.currentFallback || {});
    translateTitle(window.currentTranslations || {}, window.currentFallback || {});
  };
}

document.addEventListener('DOMContentLoaded', () => {
  initializeI18n().catch(error => console.error('i18n init failed', error));
});
