(function() {
    'use strict';

    let easyMDE;
    const titleInput = document.getElementById('tip-title');
    const pollQuestion = document.getElementById('poll-question');
    const pollOptionsList = document.getElementById('poll-options-list');
    const addPollOptionBtn = document.getElementById('add-poll-option-btn');
    const saveTipBtn = document.getElementById('save-tip-btn');
    const errorEl = document.getElementById('tip-error');

    function initEditor() {
        easyMDE = new EasyMDE({
            element: document.getElementById('tip-description'),
            spellChecker: false,
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', 'table', 'code', '|',
                'preview', 'side-by-side', 'fullscreen'
            ],
            placeholder: 'Write your tip content here...',
            status: false,
        });
    }

    function addPollOption(value = '') {
        const optionWrapper = document.createElement('div');
        optionWrapper.className = 'poll-option-row';
        optionWrapper.style.display = 'flex';
        optionWrapper.style.alignItems = 'center';
        optionWrapper.style.gap = '8px';
        optionWrapper.style.marginBottom = '8px';

        const optionInput = document.createElement('input');
        optionInput.type = 'text';
        optionInput.className = 'form-control';
        optionInput.placeholder = 'Option text';
        optionInput.value = value;
        optionInput.style.flex = '1';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-secondary';
        removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
        removeBtn.addEventListener('click', () => optionWrapper.remove());

        optionWrapper.appendChild(optionInput);
        optionWrapper.appendChild(removeBtn);
        pollOptionsList.appendChild(optionWrapper);

        return optionInput;
    }

    function getPollOptions() {
        const inputs = pollOptionsList.querySelectorAll('input');
        const values = [];
        inputs.forEach(input => {
            const txt = input.value.trim();
            if (txt) values.push(txt);
        });
        return values;
    }

    function clearPollOptions() {
        pollOptionsList.innerHTML = '';
    }

    function setPollOptions(optionTexts = []) {
        clearPollOptions();
        if (!Array.isArray(optionTexts) || optionTexts.length === 0) {
            addPollOption();
            addPollOption();
            return;
        }

        optionTexts.forEach(text => addPollOption(text));
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    function clearError() {
        errorEl.style.display = 'none';
        errorEl.textContent = '';
    }


    const loader = document.getElementById('initial-loader');

    function showLoader() {
        if (loader) {
            loader.style.display = 'flex';
        }
    }

    function hideLoader() {
        if (loader) {
            loader.style.display = 'none';
        }
    }

    function getQueryParam(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name);
    }

    const editingTipId = getQueryParam('id');

    async function loadTipForEdit(id) {
        if (!id) return;
        showLoader();
        try {
            const response = await fetch(`tips-api?id=${encodeURIComponent(id)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                const body = await response.json().catch(() => null);
                throw new Error((body && body.error) ? body.error : 'Unable to load tip for editing');
            }
            const tip = await response.json();
            console.debug('Tip loaded for edit:', tip);

            const titleValue = tip.title || tip.Title || '';
            const descriptionValue = tip.description || tip.Description || '';
            titleInput.value = titleValue;
            easyMDE.value(descriptionValue);

            const pollData = tip.poll || tip.Poll || null;
            const pollId = (tip.poll_id || tip.pollId || tip.PollID || (pollData ? pollData.id || pollData.ID : null)) || null;

            if (pollData && (pollData.question || pollData.Question)) {
                pollQuestion.value = pollData.question || pollData.Question || '';
            } else {
                pollQuestion.value = '';
            }

            let optionValues = [];
            if (pollData && Array.isArray(pollData.options)) {
                optionValues = pollData.options.map(opt => opt.text || opt.Text || opt.option_text || '');
            }

            if (optionValues.length) {
                setPollOptions(optionValues);
            } else if (pollId) {
                // fallback: if poll ID available but API did not include options, keep two fields.
                setPollOptions([]);
            } else {
                setPollOptions([]);
            }

            saveTipBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Tip';
            document.querySelector('.section-header h1').textContent = 'Edit Tip';
        } catch (err) {
            showError(err.message || 'Unable to fetch tip details');
        } finally {
            hideLoader();
        }
    }

    saveTipBtn.addEventListener('click', async function() {
        clearError();

        const title = titleInput.value.trim();
        const description = easyMDE.value().trim();
        const pollQ = pollQuestion.value.trim();
        const pollOpts = getPollOptions();

        if (!title || !description) {
            showError('Title and description are required.');
            return;
        }

        if (pollQ && pollOpts.length < 2) {
            showError('Poll requires at least two options.');
            return;
        }

        saveTipBtn.disabled = true;
        saveTipBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        try {
            const tipPayload = {
                title: title,
                description: description,
                poll_question: pollQ,
                poll_options: pollOpts,
                created_by: window.CURRENT_USER_ID,
                updated_by: window.CURRENT_USER_ID,
            };

            const method = editingTipId ? 'PATCH' : 'POST';
            if (editingTipId) {
                tipPayload.id = editingTipId;
            }

            const tipResp = await fetch('tips-api', {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(tipPayload)
            });

            if (!tipResp.ok) {
                const tipError = await tipResp.json().catch(() => ({}));
                throw new Error(tipError.error || 'Could not save tip');
            }

            if (typeof window.showToast === 'function') {
                window.showToast(editingTipId ? 'Tip updated successfully!' : 'Tip created successfully!', 'success');
            }

            window.location.href = '../partials/tips';

        } catch (error) {
            showError(error.message || 'Unable to save tip');
        } finally {
            saveTipBtn.disabled = false;
            saveTipBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + (editingTipId ? 'Update Tip' : 'Create Tip');
            hideLoader();
        }
    });

    addPollOptionBtn.addEventListener('click', () => addPollOption());

    document.addEventListener('DOMContentLoaded', function() {
        initEditor();

        if (editingTipId) {
            loadTipForEdit(editingTipId);
        } else {
            addPollOption();
            addPollOption();
        }
    });
})();
