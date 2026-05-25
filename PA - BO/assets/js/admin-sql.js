(function () {
    'use strict';

    const queryInput = document.getElementById('sql-query');
    const runButton = document.getElementById('sql-run-btn');
    const aiButton = document.getElementById('sql-ai-btn');
    const suggestionsContainer = document.getElementById('sql-suggestions');
    const suggestionsList = document.getElementById('sql-suggestions-list');
    const errorBox = document.getElementById('sql-error');
    const resultContainer = document.getElementById('sql-result');

    const authModal = document.getElementById('sql-auth-modal');
    const authForm = document.getElementById('sql-auth-form');
    const authPasswordInput = document.getElementById('sql-password');
    const authTotpInput = document.getElementById('sql-mfa-code');
    const authClose = document.getElementById('sql-auth-modal-close');
    const authCancel = document.getElementById('sql-auth-cancel');
    const authTitle = document.getElementById('sql-auth-title');
    const authSubtitle = document.getElementById('sql-auth-subtitle');
    const authError = document.getElementById('sql-auth-error');
    const authSubmit = document.getElementById('sql-auth-submit');

    const aiModal = document.getElementById('sql-ai-modal');
    const aiForm = document.getElementById('sql-ai-form');
    const aiPromptInput = document.getElementById('sql-ai-prompt');
    const aiError = document.getElementById('sql-ai-error');
    const aiClose = document.getElementById('sql-ai-modal-close');
    const aiCancel = document.getElementById('sql-ai-cancel');
    const aiSubmit = document.getElementById('sql-ai-submit');

    let pendingQuery = null;
    let pendingPassword = '';
    let authStep = 'password';

    document.addEventListener('DOMContentLoaded', function () {
        if (runButton) {
            runButton.addEventListener('click', onRunClick);
        }
        if (aiButton) {
            aiButton.addEventListener('click', openAiModal);
        }
        if (authForm) {
            authForm.addEventListener('submit', onAuthSubmit);
        }
        if (authCancel) {
            authCancel.addEventListener('click', closeAuthModal);
        }
        if (authClose) {
            authClose.addEventListener('click', closeAuthModal);
        }
        if (aiForm) {
            aiForm.addEventListener('submit', onAiFormSubmit);
        }
        if (aiCancel) {
            aiCancel.addEventListener('click', closeAiModal);
        }
        if (aiClose) {
            aiClose.addEventListener('click', closeAiModal);
        }
        const loader = document.getElementById('initial-loader');
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            mainContent.style.visibility = 'visible';
        }
        if (loader) {
            loader.style.display = 'none';
        }
    });

    function onRunClick() {
        if (!queryInput) return;

        const query = queryInput.value.trim();
        if (!query) {
            showError('Please enter a SQL query.');
            return;
        }

        const requiresElevated = /^\s*(alter|update|delete|insert|create|drop|truncate|replace|rename|grant|revoke|set|use|lock|unlock|describe|desc|explain|show|call|prepare|execute|declare)\b/i.test(query);

        if (requiresElevated) {
            pendingQuery = query;
            pendingPassword = '';
            authStep = 'password';
            openAuthModal();
            return;
        }

        executeQuery({ query });
    }

    function openAuthModal() {
        if (!authModal) return;
        authModal.classList.add('is-open');
        authModal.setAttribute('aria-hidden', 'false');
        if (authForm) {
            authForm.reset();
        }
        authStep = 'password';
        renderAuthStep();
        showAuthError('', false);
    }

    function closeAuthModal() {
        if (!authModal) return;
        authModal.classList.remove('is-open');
        authModal.setAttribute('aria-hidden', 'true');
        showAuthError('', false);
    }

    function renderAuthStep() {
        const passwordStep = document.querySelector('.auth-step-password');
        const totpStep = document.querySelector('.auth-step-totp');
        if (authStep === 'password') {
            authTitle.textContent = 'Admin authorization';
            authSubtitle.textContent = 'Enter your admin password to continue.';
            authSubmit.textContent = 'Continue';
            if (passwordStep) passwordStep.style.display = 'block';
            if (totpStep) totpStep.style.display = 'none';
            authPasswordInput.focus();
        } else {
            authTitle.textContent = 'Second factor required';
            authSubtitle.textContent = 'Enter the MFA code sent to your authenticator app.';
            authSubmit.textContent = 'Run query';
            if (passwordStep) passwordStep.style.display = 'none';
            if (totpStep) totpStep.style.display = 'block';
            authTotpInput.focus();
        }
    }

    function onAuthSubmit(event) {
        event.preventDefault();
        if (authStep === 'password') {
            const password = authPasswordInput.value.trim();
            if (!password) {
                showAuthError('Please enter your admin password.');
                return;
            }
            pendingPassword = password;
            authStep = 'totp';
            renderAuthStep();
            return;
        }

        const totpCode = authTotpInput.value.trim();
        if (!totpCode) {
            showAuthError('Please enter the MFA code.');
            return;
        }

        closeAuthModal();
        executeQuery({ query: pendingQuery, password: pendingPassword, mfa_code: totpCode });
    }

    function executeQuery(payload) {
        if (!queryInput) return;
        showError('Running query…', false);
        renderLoading();

        fetch('sql-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (!data || data.error) {
                    showError(data?.error || 'Query failed.');
                    renderEmpty();
                    return;
                }
                showError('', false);
                renderTable(data.columns || [], data.rows || []);
                if (data.truncated) {
                    renderInfo('Results truncated to 1000 rows. Refine your query to reduce output.');
                }
            })
            .catch(err => {
                console.error(err);
                showError('Unable to execute query.');
                renderEmpty();
            });
    }

    function openAiModal() {
        if (!aiModal) return;
        aiModal.classList.add('is-open');
        aiModal.setAttribute('aria-hidden', 'false');
        if (aiForm) {
            aiForm.reset();
        }
        showAiError('', false);
        aiPromptInput?.focus();
    }

    function closeAiModal() {
        if (!aiModal) return;
        aiModal.classList.remove('is-open');
        aiModal.setAttribute('aria-hidden', 'true');
        showAiError('', false);
    }

    function onAiFormSubmit(event) {
        event.preventDefault();
        if (!aiPromptInput) return;

        const userPrompt = aiPromptInput.value.trim();
        if (!userPrompt) {
            showAiError('Please describe the query you want.');
            return;
        }

        askAiForPrompt(userPrompt);
    }

    function askAiForPrompt(userPrompt) {
        if (!aiButton) return;
        aiButton.disabled = true;
        aiSubmit.disabled = true;
        aiSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Asking AI…';
        showError('Asking AI for suggestions...', false);
        showAiError('', false);

        fetch('sql-ai.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ prompt: userPrompt })
        })
            .then(r => r.json())
            .then(data => {
                aiButton.disabled = false;
                aiSubmit.disabled = false;
                aiSubmit.innerHTML = 'Ask AI';
                if (!data || data.error) {
                    showAiError(data?.error || 'AI suggestion failed.');
                    return;
                }
                closeAiModal();
                let queries = Array.isArray(data.queries) ? data.queries : [];
                queries = queries.map(q => (typeof q === 'string' ? q.trim().replace(/\s+/g, ' ') : '')).filter(q => {
                    if (!q) return false;
                    if (!/\bSELECT\b/i.test(q)) return false;
                    if (!/\bFROM\b/i.test(q)) return false;
                    if (/\b(create|drop|insert|update|delete|alter|truncate|constraint|procedure|function)\b/i.test(q)) return false;
                    if (/^SELECT\s+queries\b/i.test(q)) return false;
                    return true;
                });
                const seen = new Set();
                queries = queries.filter(q => {
                    if (seen.has(q)) return false;
                    seen.add(q);
                    return true;
                }).slice(0, 5);
                renderSuggestions(queries);
                showError('', false);
            })
            .catch(err => {
                console.error(err);
                aiButton.disabled = false;
                aiSubmit.disabled = false;
                aiSubmit.innerHTML = 'Ask AI';
                showAiError('Unable to contact AI service.');
            });
    }

    function showAiError(message, isError = true) {
        if (!aiError) return;
        if (!message) {
            aiError.style.display = 'none';
            aiError.textContent = '';
            return;
        }
        aiError.textContent = message;
        aiError.style.display = 'block';
        aiError.style.background = isError ? '#fef2f2' : '#eff6ff';
        aiError.style.borderColor = isError ? '#fecaca' : '#93c5fd';
        aiError.style.color = isError ? '#991b1b' : '#1d4ed8';
    }

    function renderSuggestions(queries) {
        if (!suggestionsContainer || !suggestionsList) return;
        suggestionsList.innerHTML = '';
        if (!queries || queries.length === 0) {
            suggestionsContainer.style.display = 'none';
            return;
        }
        suggestionsContainer.style.display = 'block';
        queries.forEach(q => {
            const el = document.createElement('div');
            el.className = 'sql-suggestion';

            const txt = document.createElement('div');
            txt.className = 'sql-suggestion-text';
            txt.textContent = q;

            const useBtn = document.createElement('button');
            useBtn.className = 'btn-secondary';
            useBtn.textContent = 'Use';
            useBtn.addEventListener('click', function () {
                if (queryInput) queryInput.value = q;
                window.scrollTo({ top: queryInput.offsetTop - 80, behavior: 'smooth' });
            });

            el.appendChild(txt);
            el.appendChild(useBtn);
            suggestionsList.appendChild(el);
        });
    }

    function showError(message, isError = true) {
        if (!errorBox) return;
        if (!message) {
            errorBox.style.display = 'none';
            errorBox.textContent = '';
            return;
        }
        errorBox.textContent = message;
        errorBox.style.display = 'block';
        errorBox.style.background = isError ? '#fef2f2' : '#eff6ff';
        errorBox.style.borderColor = isError ? '#fecaca' : '#93c5fd';
        errorBox.style.color = isError ? '#991b1b' : '#1d4ed8';
    }

    function showAuthError(message, isError = true) {
        if (!authError) return;
        if (!message) {
            authError.style.display = 'none';
            authError.textContent = '';
            return;
        }
        authError.textContent = message;
        authError.style.display = 'block';
        authError.style.background = isError ? '#fef2f2' : '#eff6ff';
        authError.style.borderColor = isError ? '#fecaca' : '#93c5fd';
        authError.style.color = isError ? '#991b1b' : '#1d4ed8';
    }

    function renderLoading() {
        if (!resultContainer) return;
        resultContainer.innerHTML = '<p>Loading results…</p>';
    }

    function renderEmpty() {
        if (!resultContainer) return;
        resultContainer.innerHTML = '<p>No results.</p>';
    }

    function renderInfo(message) {
        if (!resultContainer) return;
        const info = document.createElement('div');
        info.style.marginTop = '10px';
        info.style.padding = '10px 12px';
        info.style.background = '#f8fafc';
        info.style.border = '1px solid #cbd5e1';
        info.style.borderRadius = '8px';
        info.style.color = '#0f172a';
        info.textContent = message;
        resultContainer.appendChild(info);
    }

    function renderTable(columns, rows) {
        if (!resultContainer) return;
        if (!Array.isArray(columns) || !Array.isArray(rows)) {
            renderEmpty();
            return;
        }

        const table = document.createElement('table');
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');

        columns.forEach(column => {
            const th = document.createElement('th');
            th.textContent = String(column || '');
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        rows.forEach(row => {
            const tr = document.createElement('tr');
            columns.forEach(column => {
                const td = document.createElement('td');
                const value = row[column] ?? '';
                td.textContent = formatCellValue(value);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        resultContainer.innerHTML = '';
        resultContainer.appendChild(table);
    }

    function formatCellValue(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'object') return JSON.stringify(value);
        return String(value);
    }
})();
