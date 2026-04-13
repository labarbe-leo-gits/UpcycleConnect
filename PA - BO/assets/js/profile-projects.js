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

    var resetRequestBtn = document.getElementById('mfa-reset-request-btn');
    var resetPanel = document.getElementById('mfa-reset-panel');
    var resetSendCodeBtn = document.getElementById('mfa-reset-send-code-btn');
    var resetVerifyBtn = document.getElementById('mfa-reset-verify-btn');
    var resetCodeInput = document.getElementById('mfa-reset-code');

    function showResetPanel() {
        if (resetPanel) {
            resetPanel.style.display = 'block';
        }
    }

    if (resetRequestBtn) {
        resetRequestBtn.addEventListener('click', function () {
            showResetPanel();
            setFeedback(document.getElementById('mfa-reset-feedback'), 'Request a verification code by email to reset your 2FA configuration.', false);
        });
    }

    if (resetSendCodeBtn) {
        resetSendCodeBtn.addEventListener('click', function () {
            resetSendCodeBtn.disabled = true;
            resetSendCodeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
            postMFA('mfa_reset_request').then(function (data) {
                resetSendCodeBtn.disabled = false;
                resetSendCodeBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send code';
                if (!data.success) {
                    setFeedback(document.getElementById('mfa-reset-feedback'), data.message || 'Unable to send the code. Please try again.', true);
                    return;
                }
                showResetPanel();
                setFeedback(document.getElementById('mfa-reset-feedback'), data.message || 'Verification code sent to your email.', false);
            }).catch(function (err) {
                resetSendCodeBtn.disabled = false;
                resetSendCodeBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send code';
                setFeedback(document.getElementById('mfa-reset-feedback'), err && err.message ? err.message : 'Network error. Please try again.', true);
            });
        });
    }

    if (resetVerifyBtn) {
        resetVerifyBtn.addEventListener('click', function () {
            var code = resetCodeInput ? (resetCodeInput.value || '').trim() : '';
            if (!code || code.length !== 6 || !/^[0-9]{6}$/.test(code)) {
                setFeedback(document.getElementById('mfa-reset-feedback'), 'Please enter a valid 6-digit code.', true);
                return;
            }
            resetVerifyBtn.disabled = true;
            resetVerifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            postMFA('mfa_reset_verify', { code: code }).then(function (data) {
                resetVerifyBtn.disabled = false;
                resetVerifyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Confirm code';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-reset-feedback'), data.message || '2FA reset successful. Reloading...', false);
                    if (data.reload) {
                        setTimeout(function () { window.location.reload(); }, 1200);
                    }
                    return;
                }
                setFeedback(document.getElementById('mfa-reset-feedback'), data.message || 'Unable to verify the code.', true);
            }).catch(function (err) {
                resetVerifyBtn.disabled = false;
                resetVerifyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Confirm code';
                setFeedback(document.getElementById('mfa-reset-feedback'), err && err.message ? err.message : 'Network error. Please try again.', true);
            });
        });
    }

    if (resetCodeInput) {
        resetCodeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
})();
