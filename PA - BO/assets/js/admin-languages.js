document.addEventListener('DOMContentLoaded', () => {
  const languageCards = document.getElementById('language-cards');
  const languageStatus = document.getElementById('language-status');
  const openCreateBtn = document.getElementById('open-create-language-btn');
  const createModal = document.getElementById('language-create-modal');
  const closeCreateBtn = document.getElementById('close-create-language-modal');
  const cancelCreateBtn = document.getElementById('cancel-create-language');
  const translationsModal = document.getElementById('translations-modal');
  const closeTranslationsBtn = document.getElementById('close-translations-modal');
  const deleteModal = document.getElementById('language-delete-modal');
  const closeDeleteBtn = document.getElementById('close-delete-language-modal');
  const deleteLanguageName = document.getElementById('delete-language-name');
  const confirmDeleteBtn = document.getElementById('confirm-delete-language');
  const cancelDeleteBtn = document.getElementById('cancel-delete-language');
  const languageForm = document.getElementById('language-form');
  const codeInput = document.getElementById('language-code');
  const nameInput = document.getElementById('language-name');
  const translationsTableBody = document.getElementById('translations-table-body');
  const translationsLanguageLabel = document.getElementById('translations-language-label');
  const translationSearchInput = document.getElementById('translation-search-input');
  const translationClearSearch = document.getElementById('translation-clear-search');
  const translationPageIndicator = document.getElementById('translation-page-indicator');
  const translationPageTotal = document.getElementById('translation-page-total');
  const prevPageBtn = document.getElementById('translation-prev-btn');
  const nextPageBtn = document.getElementById('translation-next-btn');
  const languagePrevBtn = document.getElementById('language-prev-btn');
  const languageNextBtn = document.getElementById('language-next-btn');
  const languagePageIndicator = document.getElementById('language-page-indicator');
  const languagePageTotal = document.getElementById('language-page-total');

  let languages = [];
  let currentTranslations = [];
  let filteredTranslations = [];
  let currentLanguageCode = '';
  let currentLanguageName = '';
  let selectedDeleteLanguageCode = '';
  let selectedDeleteLanguageName = '';
  let languagePage = 0;
  let currentPage = 0;
  const languagePageSize = 3;
  const pageSize = 10;

  function apiRequest(action, method = 'GET', body = null) {
    const url = `languages-api.php?${action}`;
    const options = { method, headers: {} };
    if (body !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    return fetch(url, options).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || `Status ${response.status}`);
      }
      return data;
    });
  }

  function setStatus(message, type = '') {
    languageStatus.textContent = message;
    languageStatus.className = 'toast';
    if (type === 'success') {
      languageStatus.classList.add('toast-success');
    } else if (type === 'error') {
      languageStatus.classList.add('toast-error');
    }
  }

  function escapeHtml(value) {
    if (value == null) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function getFlagUrl(code) {
    const normalized = String(code || '').trim().toLowerCase();
    if (!normalized) {
      return '';
    }

    const flagMap = {
      en: 'gb',
      'en-us': 'us',
      'en-gb': 'gb',
      fr: 'fr',
      ru: 'ru',
      es: 'es',
      de: 'de',
      it: 'it',
      pt: 'pt',
      'pt-br': 'br',
      zh: 'cn',
      ja: 'jp',
      ko: 'kr',
      nl: 'nl',
      sv: 'se',
      no: 'no',
      da: 'dk',
      fi: 'fi'
    };

    const baseCode = normalized.replace('_', '-');
    if (flagMap[baseCode]) {
      return `https://flagcdn.com/80x60/${flagMap[baseCode]}.png`;
    }

    const parts = baseCode.split('-');
    if (parts.length > 1 && /^[a-z]{2}$/.test(parts[1])) {
      return `https://flagcdn.com/80x60/${parts[1]}.png`;
    }

    if (flagMap[parts[0]]) {
      return `https://flagcdn.com/80x60/${flagMap[parts[0]]}.png`;
    }

    return '';
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('is-visible');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }

  function renderLanguageCards() {
    languageCards.innerHTML = '';
    if (!languages.length) {
      languageCards.innerHTML = '<div class="empty-list">No languages available yet.</div>';
      languagePageIndicator.value = 0;
      languagePageTotal.textContent = 'of 0';
      if (languagePrevBtn) languagePrevBtn.disabled = true;
      if (languageNextBtn) languageNextBtn.disabled = true;
      return;
    }

    const pageCount = Math.ceil(languages.length / languagePageSize);
    if (languagePage >= pageCount) {
      languagePage = pageCount - 1;
    }
    const start = languagePage * languagePageSize;
    const end = Math.min(start + languagePageSize, languages.length);
    const visibleLanguages = languages.slice(start, end);

    visibleLanguages.forEach(lang => {
      const card = document.createElement('article');
      card.className = 'language-card';
      card.innerHTML = `
        <div class="language-card-header">
          <div class="language-card-flag"><img src="${getFlagUrl(lang.code)}" alt="${escapeHtml(lang.code)} flag" onerror="this.style.display='none'" /></div>
          <div>
            <p class="language-card-name">${escapeHtml(lang.name)}</p>
            <p class="language-card-code">${escapeHtml(lang.code)}</p>
          </div>
        </div>
        <p class="language-card-description">Manage locale strings for this language.</p>
        <div class="language-card-actions">
          <button type="button" class="btn btn-secondary" data-code="${escapeHtml(lang.code)}" data-name="${escapeHtml(lang.name)}">Manage translations</button>
          <button type="button" class="btn btn-secondary" data-delete-code="${escapeHtml(lang.code)}" data-delete-name="${escapeHtml(lang.name)}">Delete</button>
        </div>
      `;
      const manageButton = card.querySelector('[data-code]');
      const deleteButton = card.querySelector('[data-delete-code]');
      if (manageButton) {
        manageButton.addEventListener('click', () => openTranslationsModal(lang.code, lang.name));
      }
      if (deleteButton) {
        deleteButton.disabled = languages.length <= 1;
        deleteButton.addEventListener('click', () => openDeleteModal(lang.code, lang.name));
      }
      languageCards.appendChild(card);
    });

    const languagePageCount = Math.ceil(languages.length / languagePageSize);
    if (languagePageIndicator) {
      languagePageIndicator.value = languagePage + 1;
      languagePageIndicator.min = 1;
      languagePageIndicator.max = languagePageCount;
      languagePageIndicator.disabled = false;
    }
    if (languagePageTotal) {
      languagePageTotal.textContent = `of ${languagePageCount}`;
    }
    if (languagePrevBtn) {
      languagePrevBtn.disabled = languagePage === 0;
    }
    if (languageNextBtn) {
      languageNextBtn.disabled = languagePage >= languagePageCount - 1;
    }
  }

  async function loadLanguages() {
    try {
      setStatus('Loading languages...', '');
      const data = await apiRequest('action=list');
      languages = Array.isArray(data) ? data : [];
      languagePage = 0;
      renderLanguageCards();
      setStatus(`Loaded ${languages.length} language${languages.length === 1 ? '' : 's'}`);
    } catch (error) {
      setStatus(`Unable to load languages: ${error.message}`, 'error');
      languageCards.innerHTML = '<div class="empty-list">Unable to load languages.</div>';
    }
  }

  async function loadTranslations(code, name) {
    if (!code) return;
    currentLanguageCode = code;
    currentLanguageName = name;
    currentPage = 0;
    translationsLanguageLabel.textContent = `${name} (${code})`;
    translationsTableBody.innerHTML = '<tr><td colspan="3">Loading translations...</td></tr>';
    openModal(translationsModal);

    try {
      const data = await apiRequest(`action=translations&code=${encodeURIComponent(code)}`, 'GET');
      const translations = data && typeof data === 'object' ? data : {};
      currentTranslations = Object.entries(translations)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([key, value]) => ({ key, value: String(value ?? '') }));
      filteredTranslations = [...currentTranslations];
      if (translationSearchInput) {
        translationSearchInput.value = '';
      }
      renderTranslationPage();
    } catch (error) {
      translationsTableBody.innerHTML = `<tr><td colspan="3">Failed to load translations: ${escapeHtml(error.message)}</td></tr>`;
      setStatus(`Unable to load translations: ${error.message}`, 'error');
    }
  }

  function renderTranslationPage() {
    if (!currentTranslations.length) {
      translationsTableBody.innerHTML = '<tr><td colspan="3">No translations available for this language.</td></tr>';
      translationPageIndicator.value = 0;
      translationPageTotal.textContent = 'of 0';
      translationPageIndicator.disabled = true;
      prevPageBtn.disabled = true;
      nextPageBtn.disabled = true;
      return;
    }

    const pageCount = Math.ceil(filteredTranslations.length / pageSize);
    if (currentPage >= pageCount) {
      currentPage = pageCount - 1;
    }
    const start = currentPage * pageSize;
    const end = Math.min(start + pageSize, filteredTranslations.length);
    const pageItems = filteredTranslations.slice(start, end);

    translationsTableBody.innerHTML = '';
    pageItems.forEach(item => {
      const row = document.createElement('tr');

      const keyCell = document.createElement('td');
      keyCell.textContent = item.key;
      keyCell.style.wordBreak = 'break-all';

      const valueCell = document.createElement('td');
      const valueWrapper = document.createElement('div');
      valueWrapper.className = 'translation-value-wrapper';

      const valueText = document.createElement('span');
      valueText.className = 'translation-value-text';
      valueText.textContent = item.value;

      const valueInput = document.createElement('input');
      valueInput.type = 'text';
      valueInput.className = 'language-input';
      valueInput.value = item.value;
      valueInput.style.display = 'none';
      valueInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
          event.preventDefault();
          valueInput.blur();
        }
      });
      valueInput.addEventListener('blur', async () => {
        const newValue = valueInput.value.trim();
        if (newValue === item.value) {
          valueInput.style.display = 'none';
          valueText.style.display = 'block';
          return;
        }
        try {
          await apiRequest('action=update', 'POST', {
            code: currentLanguageCode,
            key: item.key,
            value: newValue
          });
          item.value = newValue;
          valueText.textContent = newValue;
          setStatus('Translation saved', 'success');
        } catch (error) {
          setStatus(`Save failed: ${error.message}`, 'error');
          valueInput.value = item.value;
        } finally {
          valueInput.style.display = 'none';
          valueText.style.display = 'block';
        }
      });

      valueWrapper.appendChild(valueText);
      valueWrapper.appendChild(valueInput);
      valueCell.appendChild(valueWrapper);

      const actionsCell = document.createElement('td');
      actionsCell.className = 'translation-actions';

      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'btn btn-secondary';
      editBtn.title = 'Edit translation';
      editBtn.innerHTML = '<i class="fa-solid fa-pencil"></i>';
      editBtn.addEventListener('click', () => {
        valueText.style.display = 'none';
        valueInput.style.display = 'block';
        valueInput.focus();
        valueInput.select();
      });

      actionsCell.appendChild(editBtn);

      row.appendChild(keyCell);
      row.appendChild(valueCell);
      row.appendChild(actionsCell);
      translationsTableBody.appendChild(row);
    });

    translationPageIndicator.value = currentPage + 1;
    translationPageIndicator.min = 1;
    translationPageIndicator.max = pageCount;
    translationPageIndicator.disabled = false;
    translationPageTotal.textContent = `of ${pageCount}`;
    prevPageBtn.disabled = currentPage === 0;
    nextPageBtn.disabled = currentPage >= pageCount - 1;
  }

  function openDeleteModal(code, name) {
    if (!code) return;
    if (languages.length <= 1) {
      setStatus('Cannot delete the last remaining language.', 'error');
      return;
    }
    selectedDeleteLanguageCode = code;
    selectedDeleteLanguageName = name;
    deleteLanguageName.textContent = `${name} (${code})`;
    openModal(deleteModal);
  }

  async function confirmDeleteLanguage() {
    if (!selectedDeleteLanguageCode) return;
    try {
      await apiRequest('action=delete', 'POST', { code: selectedDeleteLanguageCode });
      closeModal(deleteModal);
      setStatus(`Language ${selectedDeleteLanguageName} deleted`, 'success');
      selectedDeleteLanguageCode = '';
      selectedDeleteLanguageName = '';
      await loadLanguages();
    } catch (error) {
      setStatus(`Delete failed: ${error.message}`, 'error');
    }
  }

  function openTranslationsModal(code, name) {
    loadTranslations(code, name);
  }

  openCreateBtn.addEventListener('click', () => {
    codeInput.value = '';
    nameInput.value = '';
    setStatus('');
    openModal(createModal);
  });

  closeCreateBtn.addEventListener('click', () => closeModal(createModal));
  cancelCreateBtn.addEventListener('click', () => closeModal(createModal));
  closeTranslationsBtn.addEventListener('click', () => closeModal(translationsModal));
  closeDeleteBtn.addEventListener('click', () => closeModal(deleteModal));
  cancelDeleteBtn.addEventListener('click', () => closeModal(deleteModal));
  confirmDeleteBtn.addEventListener('click', confirmDeleteLanguage);

  [createModal, translationsModal, deleteModal].forEach(modal => {
    modal.addEventListener('click', event => {
      if (event.target === modal) {
        closeModal(modal);
      }
    });
  });

  prevPageBtn.addEventListener('click', () => {
    if (currentPage > 0) {
      currentPage -= 1;
      renderTranslationPage();
    }
  });

  nextPageBtn.addEventListener('click', () => {
    const pageCount = Math.ceil(filteredTranslations.length / pageSize);
    if (currentPage < pageCount - 1) {
      currentPage += 1;
      renderTranslationPage();
    }
  });

  if (languagePrevBtn) {
    languagePrevBtn.addEventListener('click', () => {
      if (languagePage > 0) {
        languagePage -= 1;
        renderLanguageCards();
      }
    });
  }

  if (languageNextBtn) {
    languageNextBtn.addEventListener('click', () => {
      const pageCount = Math.ceil(languages.length / languagePageSize);
      if (languagePage < pageCount - 1) {
        languagePage += 1;
        renderLanguageCards();
      }
    });
  }

  if (languagePageIndicator) {
    languagePageIndicator.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const pageCount = Math.ceil(languages.length / languagePageSize);
        const requested = Number(languagePageIndicator.value);
        if (Number.isInteger(requested) && requested >= 1 && requested <= pageCount) {
          languagePage = requested - 1;
          renderLanguageCards();
        } else {
          languagePageIndicator.value = languagePage + 1;
        }
      }
    });

    languagePageIndicator.addEventListener('blur', () => {
      const pageCount = Math.ceil(languages.length / languagePageSize);
      const requested = Number(languagePageIndicator.value);
      if (Number.isInteger(requested) && requested >= 1 && requested <= pageCount) {
        languagePage = requested - 1;
        renderLanguageCards();
      } else {
        languagePageIndicator.value = languagePage + 1;
      }
    });
  }

  function updateSearchFilter() {
    const query = translationSearchInput ? translationSearchInput.value.trim().toLowerCase() : '';
    filteredTranslations = currentTranslations.filter(item => {
      return item.key.toLowerCase().includes(query) || item.value.toLowerCase().includes(query);
    });
    currentPage = 0;
    renderTranslationPage();
  }

  if (translationSearchInput) {
    translationSearchInput.addEventListener('input', () => {
      updateSearchFilter();
    });
  }

  if (translationClearSearch) {
    translationClearSearch.addEventListener('click', () => {
      if (translationSearchInput) {
        translationSearchInput.value = '';
        updateSearchFilter();
      }
    });
  }

  translationPageIndicator.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      const pageCount = Math.ceil(filteredTranslations.length / pageSize);
      const requested = Number(translationPageIndicator.value);
      if (Number.isInteger(requested) && requested >= 1 && requested <= pageCount) {
        currentPage = requested - 1;
        renderTranslationPage();
      } else {
        translationPageIndicator.value = currentPage + 1;
      }
    }
  });

  translationPageIndicator.addEventListener('blur', () => {
    const pageCount = Math.ceil(filteredTranslations.length / pageSize);
    const requested = Number(translationPageIndicator.value);
    if (Number.isInteger(requested) && requested >= 1 && requested <= pageCount) {
      currentPage = requested - 1;
      renderTranslationPage();
    } else {
      translationPageIndicator.value = currentPage + 1;
    }
  });

  languageForm.addEventListener('submit', async event => {
    event.preventDefault();
    const code = codeInput.value.trim();
    const name = nameInput.value.trim();
    if (!code || !name) {
      setStatus('Code and name are required', 'error');
      return;
    }
    try {
      await apiRequest('action=create', 'POST', { code, name });
      setStatus('Language created', 'success');
      closeModal(createModal);
      await loadLanguages();
    } catch (error) {
      setStatus(`Create failed: ${error.message}`, 'error');
    }
  });

  loadLanguages();
});
