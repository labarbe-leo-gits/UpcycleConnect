if (typeof initialWords === 'undefined') initialWords = [];

const tbody = document.querySelector('#badwords-table tbody');
const searchBox = document.getElementById('search-box');
const wordCountEl = document.getElementById('word-count');
const refreshBtn = document.getElementById('refresh-btn');
const addWordBtn = document.getElementById('add-word-btn');
const newWordInput = document.getElementById('new-word');
const prevBtn = document.getElementById('page-prev');
const nextBtn = document.getElementById('page-next');
const pageInfoEl = document.getElementById('page-info');
const searchSpinner = document.getElementById('search-spinner');

let words = Array.isArray(initialWords) ? initialWords.slice() : [];
let filtered = words.slice();
let currentPage = 0;
const perPage = 10;
let pendingDelete = null;

function showToast(message, timeout = 3000) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 220);
    }, timeout);
}

function updateWordCount() {
    if (wordCountEl) {
        wordCountEl.textContent = `${words.length} blocked ${words.length === 1 ? 'word' : 'words'}`;
    }
}

function updatePagination() {
    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    if (pageInfoEl) {
        pageInfoEl.textContent = filtered.length === 0 ? 'No results' : `Page ${currentPage + 1} of ${totalPages}`;
    }
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 0 || filtered.length === 0;
    }
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages - 1 || filtered.length === 0;
    }
}

function renderRows() {
    if (!tbody) return;
    tbody.innerHTML = '';

    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    if (currentPage >= totalPages) {
        currentPage = totalPages - 1;
    }
    if (currentPage < 0) {
        currentPage = 0;
    }

    if (filtered.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="2" style="text-align:center;padding:20px;color:#6b7280;">No matching blocked words.</td>';
        tbody.appendChild(tr);
        updatePagination();
        return;
    }

    const start = currentPage * perPage;
    const pageItems = filtered.slice(start, start + perPage);

    pageItems.forEach(word => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${word}</td>
            <td style="text-align:center;">
                <button class="btn-danger delete-word" data-word="${word}" aria-label="Delete ${word}">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        tr.addEventListener('mouseenter', () => tr.style.background = '#f9f9f9');
        tr.addEventListener('mouseleave', () => tr.style.background = '');
        tbody.appendChild(tr);
    });

    wireDeleteButtons();
    updatePagination();
}

function wireDeleteButtons() {
    document.querySelectorAll('.delete-word').forEach(button => {
        button.removeEventListener('click', handleDeleteClick);
        button.addEventListener('click', handleDeleteClick);
    });
}

function handleDeleteClick(event) {
    pendingDelete = event.currentTarget.dataset.word;
    const deleteName = document.getElementById('delete-word-name');
    if (deleteName) {
        deleteName.textContent = pendingDelete;
    }
    const modal = document.getElementById('delete-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-visible');
        document.body.classList.add('modal-open');
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('delete-modal');
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('is-visible');
    document.body.classList.remove('modal-open');
    setTimeout(() => {
        if (modal.getAttribute('aria-hidden') === 'true') {
            modal.style.display = 'none';
        }
    }, 220);
    pendingDelete = null;
}

async function fetchWordlist() {
    const response = await fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=load'
    });
    const json = await response.json();
    if (!json.ok) {
        throw new Error(json.error || 'Failed to load blocklist');
    }
    return Array.isArray(json.words) ? json.words : [];
}

async function refreshWordlist() {
    try {
        if (refreshBtn) refreshBtn.disabled = true;
        words = await fetchWordlist();
        currentPage = 0;
        applySearch();
        updateWordCount();
    } catch (error) {
        console.error(error);
        showToast('Unable to refresh blocklist');
    } finally {
        if (refreshBtn) refreshBtn.disabled = false;
    }
}

function applySearch() {
    const term = searchBox?.value.trim().toLowerCase() || '';
    filtered = term === '' ? words.slice() : words.filter(word => word.toLowerCase().includes(term));
    currentPage = 0;
    renderRows();
}

async function addWord() {
    const value = newWordInput?.value.trim();
    if (!value) {
        showToast('Please enter a word or phrase');
        return;
    }
    if (addWordBtn) addWordBtn.disabled = true;
    try {
        const body = new URLSearchParams({action: 'add', word: value});
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        });
        const json = await response.json();
        if (!json.ok) {
            showToast(json.error || 'Could not add word');
            return;
        }
        words = Array.isArray(json.words) ? json.words : words;
        newWordInput.value = '';
        applySearch();
        updateWordCount();
        showToast('Blocked word added');
    } catch (error) {
        console.error(error);
        showToast('Add failed');
    } finally {
        if (addWordBtn) addWordBtn.disabled = false;
    }
}

async function deleteWord(word) {
    try {
        const body = new URLSearchParams({action: 'delete', word});
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        });
        const json = await response.json();
        if (!json.ok) {
            showToast(json.error || 'Could not delete word');
            return;
        }
        words = Array.isArray(json.words) ? json.words : words.filter(w => w !== word);
        if (currentPage > 0 && currentPage >= Math.ceil(filtered.length / perPage)) {
            currentPage = Math.max(0, Math.ceil(filtered.length / perPage) - 1);
        }
        applySearch();
        updateWordCount();
        showToast('Blocked word removed');
    } catch (error) {
        console.error(error);
        showToast('Delete failed');
    }
}

function initButtons() {
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshWordlist);
    }
    if (addWordBtn) {
        addWordBtn.addEventListener('click', addWord);
    }
    if (newWordInput) {
        newWordInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addWord();
            }
        });
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 0) {
                currentPage -= 1;
                renderRows();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(filtered.length / perPage);
            if (currentPage < totalPages - 1) {
                currentPage += 1;
                renderRows();
            }
        });
    }
}

function initSearch() {
    if (!searchBox) return;
    searchBox.addEventListener('input', () => {
        if (searchSpinner) searchSpinner.style.display = 'inline-block';
        setTimeout(() => {
            applySearch();
            if (searchSpinner) searchSpinner.style.display = 'none';
        }, 100);
    });
}

function initDeleteModal() {
    const modal = document.getElementById('delete-modal');
    if (!modal) return;

    modal.addEventListener('click', event => {
        if (event.target.id === 'delete-modal') {
            closeDeleteModal();
        }
    });
    document.getElementById('delete-modal-close')?.addEventListener('click', closeDeleteModal);
    document.getElementById('delete-cancel')?.addEventListener('click', closeDeleteModal);
    document.getElementById('delete-confirm')?.addEventListener('click', async () => {
        if (!pendingDelete) return;
        await deleteWord(pendingDelete);
        closeDeleteModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
            closeDeleteModal();
        }
    });
}

function init() {
    updateWordCount();
    initButtons();
    initSearch();
    initDeleteModal();
    applySearch();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
