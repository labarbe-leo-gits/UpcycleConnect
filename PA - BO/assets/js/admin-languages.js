document.addEventListener('DOMContentLoaded', () => {
  const languageSelect = document.getElementById('language-select');
  const languagesList = document.getElementById('languages-list');
  const translationEditor = document.getElementById('translation-editor');
  const languageForm = document.getElementById('language-form');
  const languageStatus = document.getElementById('language-status');
  const translationsTableBody = document.getElementById('translations-table-body');
  const selectedLanguageInput = document.getElementById('selected-language-code');

  async function apiRequest(action, method = 'GET', body = null) {
    const url = `languages-api.php?${action}`;
    const options = { method, headers: {} };
    if (body !== null) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    const response = await fetch(url, options);
    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.error || `Status ${response.status}`);
    }
    return response.json();
  }

  async function loadLanguages() {
    try {
      const data = await apiRequest('action=list');
      const languages = Array.isArray(data) ? data : [];
      languagesList.innerHTML = '';
      languageSelect.innerHTML = '<option value="">Select a language</option>';
      languages.forEach(lang => {
        const row = document.createElement('tr');
        row.innerHTML = `<td style="padding:10px 8px;">${lang.code}</td><td style="padding:10px 8px;">${lang.name}</td><td style="padding:10px 8px;color:#2563eb;font-weight:700;cursor:pointer;">Select</td>`;
        row.addEventListener('click', () => loadTranslations(lang.code, lang.name));
        languagesList.appendChild(row);
        const option = document.createElement('option');
        option.value = lang.code;
        option.textContent = `${lang.name} (${lang.code})`;
        languageSelect.appendChild(option);
      });
    } catch (error) {
      languageStatus.textContent = `Unable to load languages: ${error.message}`;
      languageStatus.classList.add('toast-error');
    }
  }

  async function loadTranslations(code, name) {
    if (!code) {
      return;
    }
    selectedLanguageInput.textContent = `${name} (${code})`;
    translationsTableBody.innerHTML = '<tr><td colspan="2">Loading translations...</td></tr>';
    try {
      const data = await apiRequest(`action=translations&code=${encodeURIComponent(code)}`, 'GET');
      const translations = data && typeof data === 'object' ? data : {};
      translationsTableBody.innerHTML = '';
      Object.entries(translations).sort(([a], [b]) => a.localeCompare(b)).forEach(([key, value]) => {
        const row = document.createElement('tr');
        const input = document.createElement('input');
        input.type = 'text';
        input.value = value;
        input.className = 'language-input';
        input.addEventListener('blur', async () => {
          try {
            await apiRequest('action=update', 'POST', { code, key, value: input.value });
            languageStatus.textContent = 'Translation saved';
            languageStatus.className = 'toast toast-success';
          } catch (err) {
            languageStatus.textContent = `Save failed: ${err.message}`;
            languageStatus.className = 'toast toast-error';
          }
        });
        row.innerHTML = `<td>${key}</td><td></td>`;
        row.children[1].appendChild(input);
        translationsTableBody.appendChild(row);
      });
    } catch (error) {
      translationsTableBody.innerHTML = `<tr><td colspan="2">Failed to load translations: ${error.message}</td></tr>`;
    }
  }

  languageForm.addEventListener('submit', async event => {
    event.preventDefault();
    const code = document.getElementById('language-code').value.trim();
    const name = document.getElementById('language-name').value.trim();
    if (!code || !name) {
      languageStatus.textContent = 'Code and name are required';
      languageStatus.className = 'toast toast-error';
      return;
    }
    try {
      await apiRequest('action=create', 'POST', { code, name });
      languageStatus.textContent = 'Language created';
      languageStatus.className = 'toast toast-success';
      languageForm.reset();
      await loadLanguages();
    } catch (err) {
      languageStatus.textContent = `Create failed: ${err.message}`;
      languageStatus.className = 'toast toast-error';
    }
  });

  languageSelect.addEventListener('change', () => {
    const selected = languageSelect.value;
    if (selected) {
      const option = languageSelect.selectedOptions[0];
      loadTranslations(selected, option ? option.textContent : selected);
    }
  });

  loadLanguages();
});
