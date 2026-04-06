document.addEventListener('DOMContentLoaded', async function () {
    const loader = document.getElementById('initial-loader');
    if (loader) loader.style.display = 'none';

    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('session_id') || '';
    const status = params.get('status') || '';
    const loading = document.getElementById('success-loading');
    const result = document.getElementById('success-result');

    function renderSuccess() {
        loading.classList.add('hidden');
        result.classList.remove('hidden');
        result.innerHTML = `
            <div class="success-card">
                <div class="success-icon"><i class="fas fa-bullhorn"></i></div>
                <h1>Promotion activated!</h1>
                <p>Your offer is now promoted. It may take a few minutes to appear.</p>
                <div class="success-actions">
                    <a href="offers" class="btn btn-primary">
                        <i class="fas fa-list"></i> Back to offers
                    </a>
                </div>
            </div>`;
    }

    function renderError(message) {
        loading.classList.add('hidden');
        result.classList.remove('hidden');
        result.innerHTML = `
            <div class="error-card">
                <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h1>Verification failed</h1>
                <p>${message}</p>
                <a href="offers" class="btn btn-outline">Back</a>
            </div>`;
    }

    if (status === 'success') {
        renderSuccess();
        return;
    }

    if (!sessionId) {
        renderError('Missing payment session.');
        return;
    }

    try {
        const res = await fetch(
            'promote-success?session_id=' + encodeURIComponent(sessionId),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        if (!res.ok) throw new Error('Request failed');
        const data = await res.json();

        if (data.success) {
            renderSuccess();
        } else {
            renderError(data.error ? data.error.replace(/</g, '&lt;') : 'Verification failed.');
        }
    } catch (e) {
        renderError('Network error. Please try again.');
    }
});
