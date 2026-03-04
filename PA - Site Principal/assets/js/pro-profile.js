(function () {
    var loaded = false;

    function fmt(n, d) {
        return Number(n).toLocaleString('en-GB', { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    function renderPremium(priceDisplay) {
        var el = document.getElementById('acc-sub-content');
        el.innerHTML =
            '<div class="acc-sub-actions">'
            + '<div class="acc-sub-premium-status"><i class="fas fa-crown"></i> Premium active</div>'
            + '</div>'
            + '<p style="font-size:14px;color:#065f46;margin:0 0 16px;">You have access to all advanced UpcycleConnect features.</p>'
            + '<div class="acc-sub-actions">'
            + '<a href="dashboard" class="sub-quick-btn primary"><i class="fas fa-chart-bar"></i> Go to Dashboard</a>'
            + '<button id="acc-btn-manage" class="sub-quick-btn" data-url="create-billing-portal"><i class="fas fa-cog"></i> Manage subscription</button>'
            + '</div>';

        var btnManage = document.getElementById('acc-btn-manage');
        if (btnManage) {
            btnManage.addEventListener('click', function () {
                btnManage.disabled = true;
                btnManage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                fetch(btnManage.dataset.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.portal_url) {
                        window.location.href = data.portal_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnManage.disabled = false;
                        btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage subscription';
                    }
                }).catch(function () {
                    alert('Network error.');
                    btnManage.disabled = false;
                    btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage subscription';
                });
            });
        }
    }

    function renderFree(priceDisplay) {
        var display = priceDisplay || '€29.99 / month';
        var el = document.getElementById('acc-sub-content');
        el.innerHTML =
            '<p class="acc-sub-free-cta">You are on the <strong>Free</strong> plan. Upgrade to unlock advanced analytics and tools.</p>'
            + '<div class="acc-sub-actions">'
            + '<a href="subscription" class="sub-quick-btn primary"><i class="fas fa-crown"></i> Go Premium</a>'
            + '<a href="subscription" class="sub-quick-btn"><i class="fas fa-info-circle"></i> Learn more</a>'
            + '</div>'
            

        var btnSub = document.getElementById('acc-btn-subscribe');
        if (btnSub) {
            btnSub.addEventListener('click', function () {
                btnSub.disabled = true;
                btnSub.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                fetch('create-subscription-checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnSub.disabled = false;
                        btnSub.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                    }
                }).catch(function () {
                    alert('Network error.');
                    btnSub.disabled = false;
                    btnSub.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                });
            });
        }
    }

    function loadSubscription() {
        if (loaded) return;
        loaded = true;
        fetch('subscription-api', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            document.getElementById('acc-sub-skeleton').style.display = 'none';
            document.getElementById('acc-sub-content').style.display  = '';

            var quickLabel = document.getElementById('sub-quick-label');
            var quickBtn   = document.getElementById('sub-quick-access');

            if (data.is_premium) {
                if (quickLabel) quickLabel.textContent = 'My subscription';
                if (quickBtn)   quickBtn.classList.add('primary');
                renderPremium(data.price_display);
            } else {
                if (quickLabel) quickLabel.textContent = 'Go Premium';
                renderFree(data.price_display);
            }
        }).catch(function () {
            document.getElementById('acc-sub-skeleton').style.display = 'none';
            document.getElementById('acc-sub-content').style.display  = '';
            document.getElementById('acc-sub-content').innerHTML =
                '<p style="color:#9ca3af;font-size:14px;">Unable to load subscription status.</p>';
        });
    }

    var toggle = document.querySelector('#acc-subscription .accordion-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var body    = this.closest('.profile-accordion').querySelector('.accordion-body');
            var chevron = this.querySelector('.accordion-chevron');
            var open    = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', open ? 'false' : 'true');
            body.style.display = open ? 'none' : '';
            if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
            if (!open) loadSubscription();
        });
    }
})();


(function () {
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = this.getAttribute('data-tab');
            if (tab === 'business') {
                document.querySelectorAll('.tab-content').forEach(function (tc) { tc.style.display = 'none'; });
                var el = document.getElementById('business-tab');
                if (el) el.style.display = '';
            }
        });
    });
})();

(function () {
    var currentSecret = '';

    function postMFA(formType, extra) {
        var fd = new FormData();
        fd.append('form_type', formType);
        if (extra) Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); });
    }

    function setFeedback(el, msg, isError) {
        if (!el) return;
        el.innerHTML = '<div class="' + (isError ? 'error-message' : 'success-message') + '">' + msg + '</div>';
    }

    var setupBtn = document.getElementById('mfa-setup-btn');
    if (setupBtn) {
        setupBtn.addEventListener('click', function () {
            setupBtn.disabled = true;
            setupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            postMFA('mfa_setup').then(function (data) {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                if (!data.success) { setFeedback(document.getElementById('mfa-feedback'), data.message || 'Error.', true); return; }
                currentSecret = data.secret;
                document.getElementById('mfa-secret-display').textContent = data.secret;
                var qrEl = document.getElementById('mfa-qr-code');
                qrEl.innerHTML = '';
                (function renderQR() {
                    if (typeof QRCode === 'undefined') { setTimeout(renderQR, 100); return; }
                    new QRCode(qrEl, { text: data.otp_url, width: 200, height: 200 });
                })();
                document.getElementById('mfa-setup-panel').style.display = 'block';
                setupBtn.style.display = 'none';
            }).catch(function () {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var enableBtn = document.getElementById('mfa-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            var code = (document.getElementById('mfa-verify-code').value || '').trim();
            if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Please enter a valid 6-digit code.', true);
                return;
            }
            enableBtn.disabled = true;
            enableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            postMFA('mfa_enable', { secret: currentSecret, code: code }).then(function (data) {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-setup-feedback'), '2FA enabled successfully! Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-setup-feedback'), data.message || 'Invalid code.', true);
                }
            }).catch(function () {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var disableBtn = document.getElementById('mfa-disable-btn');
    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to disable 2FA? This will make your account less secure.')) return;
            disableBtn.disabled = true;
            disableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Disabling...';
            postMFA('mfa_disable').then(function (data) {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), '2FA disabled. Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Unable to disable 2FA.', true);
                }
            }).catch(function () {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var otpInput = document.getElementById('mfa-verify-code');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
})();