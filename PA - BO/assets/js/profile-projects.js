(function () {
    var currentSecret = '';

    function postMFA(formType, extra) {
        var fd = new FormData();
        fd.append('form_type', formType);
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        return fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) {
            return r.text().then(function (text) {
                if (!r.ok) {
                    throw new Error('Server error: ' + r.status + ' ' + r.statusText + '\n' + text.slice(0, 300));
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response:\n' + text.slice(0, 300));
                }
            });
        });
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
                if (!data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Error.', true);
                    return;
                }
                currentSecret = data.secret || '';
                document.getElementById('mfa-secret-display').textContent = data.secret || '';
                var qrEl = document.getElementById('mfa-qr-code');
                if (qrEl) {
                    qrEl.innerHTML = '';
                    function renderQR() {
                        if (typeof QRCode === 'undefined') { setTimeout(renderQR, 100); return; }
                        new QRCode(qrEl, { text: data.otp_url || '', width: 200, height: 200 });
                    }
                    renderQR();
                }
                var panel = document.getElementById('mfa-setup-panel');
                if (panel) panel.style.display = 'block';
                setupBtn.style.display = 'none';
            }).catch(function (err) {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                setFeedback(document.getElementById('mfa-feedback'), err && err.message ? err.message : 'Network error. Please try again.', true);
            });
        });
    }

    if (window.forceMFASetup) {
        var mfaForceModal = document.getElementById('mfa-force-modal');
        var mfaForceClose = document.getElementById('mfa-force-modal-close');
        var mfaForceSkip = document.getElementById('mfa-force-modal-skip');
        var mfaForceStart = document.getElementById('mfa-force-modal-start');
        if (mfaForceModal) {
            mfaForceModal.style.display = 'flex';
            requestAnimationFrame(function() {
                mfaForceModal.classList.add('is-visible');
                mfaForceModal.setAttribute('aria-hidden', 'false');
            });
        }

        function closeForceModal() {
            if (!mfaForceModal) return;
            mfaForceModal.classList.remove('is-visible');
            mfaForceModal.setAttribute('aria-hidden', 'true');

            var hideModal = function(event) {
                if (event && event.target !== mfaForceModal) return;
                mfaForceModal.style.display = 'none';
                mfaForceModal.removeEventListener('transitionend', hideModal);
            };

            mfaForceModal.addEventListener('transitionend', hideModal);
            setTimeout(function() {
                hideModal();
            }, 250);
        }

        if (mfaForceClose) {
            mfaForceClose.addEventListener('click', closeForceModal);
        }
        if (mfaForceSkip) {
            mfaForceSkip.addEventListener('click', closeForceModal);
        }
        if (mfaForceStart) {
            mfaForceStart.addEventListener('click', function () {
                closeForceModal();
                var tabBtn = document.querySelector('.tab-btn[data-tab="mfa"]');
                if (tabBtn) {
                    tabBtn.click();
                }
                setTimeout(function () {
                    if (setupBtn) {
                        setupBtn.click();
                    }
                }, 100);
            });
        }
    }

    var enableBtn = document.getElementById('mfa-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            var codeInput = document.getElementById('mfa-verify-code');
            var code = codeInput ? (codeInput.value || '').trim() : '';
            if (!code || code.length !== 6 || !/^[0-9]{6}$/.test(code)) {
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
            }).catch(function (err) {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                setFeedback(document.getElementById('mfa-setup-feedback'), err && err.message ? err.message : 'Network error. Please try again.', true);
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
            }).catch(function (err) {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                setFeedback(document.getElementById('mfa-feedback'), err && err.message ? err.message : 'Network error. Please try again.', true);
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
