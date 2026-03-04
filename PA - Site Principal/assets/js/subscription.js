(function () {
    function wireButtons() {
        const btnSubscribe = document.getElementById('btn-subscribe');
        const btnManage    = document.getElementById('btn-manage');

        if (btnSubscribe) {
            btnSubscribe.addEventListener('click', async () => {
                btnSubscribe.disabled = true;
                btnSubscribe.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                try {
                    const res  = await fetch('create-subscription-checkout', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({})
                    });
                    const data = await res.json();
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnSubscribe.disabled = false;
                        btnSubscribe.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                    }
                } catch (e) {
                    alert('Network error.');
                    btnSubscribe.disabled = false;
                    btnSubscribe.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                }
            });
        }
        if (btnManage) {
            btnManage.addEventListener('click', async () => {
                btnManage.disabled = true;
                btnManage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                try {
                    const res  = await fetch(btnManage.dataset.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({})
                    });
                    const data = await res.json();
                    if (data.portal_url) {
                        window.location.href = data.portal_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnManage.disabled = false;
                        btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage my subscription';
                    }
                } catch (e) {
                    alert('Network error.');
                    btnManage.disabled = false;
                    btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage my subscription';
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        const loader = document.getElementById('initial-loader');
        if (loader) loader.style.display = 'none';

        try {
            const res  = await fetch('subscription-api', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('Request failed');
            const data = await res.json();

            document.getElementById('sub-loading').classList.add('hidden');

            if (data.is_premium) {
                document.getElementById('sub-premium').classList.remove('hidden');
            } else {
                const priceEl = document.getElementById('price-display');
                if (priceEl && data.price_display) priceEl.textContent = data.price_display;
                document.getElementById('sub-freemium').classList.remove('hidden');
            }

            wireButtons();
        } catch (e) {
            document.getElementById('sub-loading').innerHTML =
                '<p class="empty-state">Unable to load subscription status.</p>';
        }
    });
})();
