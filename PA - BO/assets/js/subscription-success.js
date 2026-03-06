document.addEventListener('DOMContentLoaded', async function () {
    const loader    = document.getElementById('initial-loader');
    if (loader) loader.style.display = 'none';

    const sessionId = new URLSearchParams(window.location.search).get('session_id') || '';
    const loading   = document.getElementById('success-loading');
    const result    = document.getElementById('success-result');

    try {
        const res = await fetch(
            'subscription-success' + (sessionId ? '?session_id=' + encodeURIComponent(sessionId) : ''),
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        if (!res.ok) throw new Error('Request failed');
        const data = await res.json();

        loading.classList.add('hidden');
        result.classList.remove('hidden');

        if (data.success) {
            result.innerHTML = `
                <div class="success-card">
                    <div class="success-icon"><i class="fas fa-crown"></i></div>
                    <h1>Welcome to the Premium Club!</h1>
                    <p>Your subscription is now active. All advanced features are unlocked.</p>
                    <div class="success-actions">
                        <a href="dashboard-premium" class="btn btn-primary">
                            <i class="fas fa-chart-bar"></i> Go to dashboard
                        </a>
                        <a href="subscription" class="btn btn-outline">Manage my subscription</a>
                    </div>
                </div>`;
        } else {
            const msg = data.error ? data.error.replace(/</g, '&lt;') : 'Verification failed.';
            result.innerHTML = `
                <div class="error-card">
                    <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <h1>Verification failed</h1>
                    <p>${msg}</p>
                    <a href="subscription" class="btn btn-outline">Back</a>
                </div>`;
        }
    } catch (e) {
        loading.classList.add('hidden');
        result.classList.remove('hidden');
        result.innerHTML = `
            <div class="error-card">
                <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h1>Verification failed</h1>
                <p>Network error. Please try again.</p>
                <a href="subscription" class="btn btn-outline">Back</a>
            </div>`;
    }
});
