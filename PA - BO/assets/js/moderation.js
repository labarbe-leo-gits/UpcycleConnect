let filtered = words.slice();

if (typeof showToast !== 'function') {
    function showToast(msg, timeout){
        const t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(()=>{t.style.transition='opacity 0.3s';t.style.opacity='0';}, timeout||3000);
        setTimeout(()=>t.remove(), (timeout||3000)+300);
    }
}
let displayed = 0;
const perPage = 10;
const tbody = document.querySelector('#badwords-table tbody');
function renderRows() {
    tbody.innerHTML = '';
    if (filtered.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="2" style="text-align:center;padding:20px;color:#6b7280;">No results</td>';
        tbody.appendChild(tr);
        document.getElementById('load-more-btn').style.display = 'none';
        return;
    }
    const slice = filtered.slice(displayed, displayed + perPage);
    slice.forEach(w => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${w}</td><td style="text-align:center;"><span class="delete-word btn-danger" data-word="${w}"><i class="fa-solid fa-trash"></i></span></td>`;
        tr.addEventListener('mouseenter', () => tr.style.background = '#f9f9f9');
        tr.addEventListener('mouseleave', () => tr.style.background = '');
        tbody.appendChild(tr);
    });
    displayed += slice.length;
    if (displayed >= filtered.length) {
        document.getElementById('load-more-btn').style.display = 'none';
    } else {
        document.getElementById('load-more-btn').style.display = '';
    }
}

let pendingDelete = null;
function wireDelete() {
    document.querySelectorAll('.delete-word').forEach(el => {
        el.addEventListener('click', () => {
            pendingDelete = el.dataset.word;
            document.getElementById('delete-word-name').textContent = pendingDelete;
            const overlay = document.getElementById('delete-modal');
            overlay.setAttribute('aria-hidden', 'false');
            overlay.classList.add('is-visible');
            document.body.classList.add('modal-open');
        });
    });
}

function closeModal() {
    const overlay = document.getElementById('delete-modal');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.classList.remove('is-visible');
    document.body.classList.remove('modal-open');
    pendingDelete = null;
}

document.getElementById('delete-modal').addEventListener('click', e => {
    if (e.target.id === 'delete-modal') {
        closeModal();
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('delete-modal').getAttribute('aria-hidden') === 'false') {
        closeModal();
    }
});

document.getElementById('delete-modal-close').addEventListener('click', closeModal);
document.getElementById('delete-cancel').addEventListener('click', closeModal);
document.getElementById('delete-confirm').addEventListener('click', () => {
    if (!pendingDelete) return;
    fetch('', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=delete&word=${encodeURIComponent(pendingDelete)}`})
        .then(r=>r.json()).then(resp=>{
            if (resp.ok) location.reload();
        });
});

document.getElementById('load-more-btn').addEventListener('click', () => {
    const btn = document.getElementById('load-more-btn');
    const spinner = document.getElementById('load-more-spinner');
    btn.disabled = true;
    spinner.style.display = 'inline-block';
    setTimeout(() => {
        renderRows();
        wireDelete();
        btn.disabled = false;
        spinner.style.display = 'none';
        if (displayed >= filtered.length) btn.style.display='none';
    }, 200);
});

document.getElementById('sync-btn').addEventListener('click', () => {
    const status = document.getElementById('sync-status');
    const spinner = document.getElementById('sync-spinner');
    const text = document.getElementById('sync-text');
    const btn = document.getElementById('sync-btn');
    status.style.visibility = 'hidden';
    btn.disabled = true;
    text.style.display = 'none';
    spinner.style.display = 'inline-block';
    fetch('', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=sync'})
.then(r=>r.json())
        .then(resp=>{
            spinner.style.display = 'none';
            text.style.display = '';
            btn.disabled = false;
            if (resp.ok) {
                status.style.visibility = '';
            status.textContent = `synced (${resp.count} words)`;
                if (typeof showToast === 'function') showToast('Sync successful ('+resp.count+' words)');
                setTimeout(()=>location.reload(),500);
            } else {
                status.style.visibility = '';
                status.textContent = resp.error || 'error';
                if (typeof showToast === 'function') showToast('Sync failed: '+(resp.error||'unknown'));
            }
        }).catch(err=>{
            spinner.style.display = 'none';
            text.style.display = '';
            btn.disabled = false;
            status.style.visibility = '';
            status.textContent='error';
            console.error(err);
            if (typeof showToast === 'function') showToast('Sync error');
        });
});

renderRows();
wireDelete();

const searchBox = document.getElementById('search-box');
const spinner = document.getElementById('search-spinner');
let searchTimeout;
let spinnerVisibleSince = 0;
searchBox.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    if (spinner.style.display !== 'inline-block') {
        spinner.style.display = 'inline-block';
        spinnerVisibleSince = Date.now();
    }
    searchTimeout = setTimeout(() => {
        const term = searchBox.value.trim().toLowerCase();
        if (term === '') {
            filtered = words.slice();
        } else {
            filtered = words.filter(w => w.toLowerCase().includes(term));
        }
        displayed = 0;
        tbody.innerHTML = '';
        renderRows();
        wireDelete();
        const elapsed = Date.now() - spinnerVisibleSince;
        const remaining = 300 - elapsed;
        if (remaining > 0) {
            setTimeout(() => { spinner.style.display = 'none'; }, remaining);
        } else {
            spinner.style.display = 'none';
        }
    }, 150);
});