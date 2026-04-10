(function() {
    function togglePasswordVisibility(toggle) {
        var wrapper = toggle.closest('.password-wrapper');
        var input = wrapper ? wrapper.querySelector('input') : null;
        if (!input) return;
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        toggle.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
    }

    function openForgotModal(forgotModal) {
        if (!forgotModal) return;
        forgotModal.classList.add('is-visible');
        document.body.classList.add('modal-open');
        forgotModal.setAttribute('aria-hidden', 'false');
    }

    function closeForgotModal(forgotModal) {
        if (!forgotModal) return;
        forgotModal.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
        forgotModal.setAttribute('aria-hidden', 'true');
    }

    function resetForgotStep2State() {
        const codeField = document.getElementById('forgot-code-field');
        const passwordFields = document.getElementById('forgot-password-fields');
        const verifyButton = document.getElementById('forgot-verify-code-button');
        const resetButton = document.getElementById('forgot-reset-password-button');
        const codeInput = document.getElementById('forgot-code');
        const newPasswordInput = document.getElementById('forgot-new-password');
        const confirmPasswordInput = document.getElementById('forgot-confirm-password');
        if (codeField) codeField.style.display = 'block';
        if (passwordFields) passwordFields.style.display = 'none';
        if (verifyButton) verifyButton.style.display = 'inline-flex';
        if (resetButton) resetButton.style.display = 'none';
        if (codeInput) codeInput.value = '';
        if (confirmPasswordInput) confirmPasswordInput.value = '';
    }

    function showForgotStep(step, forgotStep1, forgotStep2, forgotMessage) {
        if (!forgotStep1 || !forgotStep2 || !forgotMessage) return;
        if (step === 1) {
            forgotStep1.style.display = 'block';
            forgotStep2.style.display = 'none';
            forgotMessage.innerHTML = '';
            resetForgotStep2State();
        } else {
            forgotStep1.style.display = 'none';
            forgotStep2.style.display = 'block';
            forgotMessage.innerHTML = '';
            resetForgotStep2State();
        }
    }

    function setForgotMessage(forgotMessage, message, isError) {
        if (!forgotMessage) return;
        forgotMessage.innerHTML = '<div class="' + (isError ? 'error-message' : 'success-message') + '">' + message + '</div>';
    }

    function setButtonLoading(button, isLoading, text) {
        if (!button) return;
        if (isLoading) {
            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml = button.innerHTML;
            }
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + text;
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
        }
    }

    async function postForgotPassword(payload) {
        const response = await fetch('forgot-password-api', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        return response.json();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.password-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                togglePasswordVisibility(toggle);
            });
        });

        const forgotLink = document.querySelector('.forgot-password');
        const forgotModal = document.getElementById('forgot-password-modal');
        const closeForgot = document.getElementById('close-forgot-modal');
        const cancelForgot = document.getElementById('cancel-forgot-modal');
        const forgotOk = document.getElementById('forgot-ok');
        const forgotMessage = document.getElementById('forgot-password-message');
        const forgotStep1 = document.getElementById('forgot-password-step1');
        const forgotStep2 = document.getElementById('forgot-password-step2');
        const forgotEmailDisplay = document.getElementById('forgot-email-display');
        const forgotEmailHidden = document.getElementById('forgot-email-hidden');
        const forgotSendCodeButton = document.getElementById('forgot-send-code-button');
        const forgotVerifyCodeButton = document.getElementById('forgot-verify-code-button');
        const forgotResetButton = document.getElementById('forgot-reset-password-button');
        const forgotRequestForm = document.getElementById('forgot-password-request-form');
        const forgotResetForm = document.getElementById('forgot-password-step2-form');
        const forgotBackButton = document.getElementById('forgot-back-button');

        if (forgotLink) {
            forgotLink.addEventListener('click', function(e) {
                e.preventDefault();
                openForgotModal(forgotModal);
            });
        }
        if (closeForgot) closeForgot.addEventListener('click', function() { closeForgotModal(forgotModal); });
        if (cancelForgot) cancelForgot.addEventListener('click', function() { closeForgotModal(forgotModal); });
        if (forgotOk) forgotOk.addEventListener('click', function() { closeForgotModal(forgotModal); });
        if (forgotModal) {
            forgotModal.addEventListener('click', function(event) {
                if (event.target === forgotModal) {
                    closeForgotModal(forgotModal);
                }
            });
        }

        async function submitForgotRequest() {
            const emailInput = document.getElementById('forgot-email');
            const email = emailInput ? emailInput.value.trim() : '';
            if (!email) {
                setForgotMessage(forgotMessage, 'Please enter your email address.', true);
                return;
            }
            setForgotMessage(forgotMessage, 'Sending code...', false);
            setButtonLoading(forgotSendCodeButton, true, 'Sending...');
            try {
                const result = await postForgotPassword({ action: 'send_code', email });
                if (result.success) {
                    if (forgotEmailDisplay) forgotEmailDisplay.textContent = email;
                    if (forgotEmailHidden) forgotEmailHidden.value = email;
                    showForgotStep(2, forgotStep1, forgotStep2, forgotMessage);
                    setForgotMessage(forgotMessage, result.message || 'A verification code was sent to your email.', false);
                } else {
                    setForgotMessage(forgotMessage, result.error || 'Unable to send the reset code.', true);
                }
            } catch (error) {
                setForgotMessage(forgotMessage, 'Unable to send the reset code. Please try again later.', true);
            } finally {
                setButtonLoading(forgotSendCodeButton, false);
            }
        }

        if (forgotRequestForm) {
            forgotRequestForm.addEventListener('submit', function(event) {
                event.preventDefault();
                submitForgotRequest();
            });
        }
        if (forgotSendCodeButton) {
            forgotSendCodeButton.addEventListener('click', function() {
                submitForgotRequest();
            });
        }

        async function submitForgotCodeVerification() {
            const email = forgotEmailHidden ? forgotEmailHidden.value.trim() : '';
            const codeInput = document.getElementById('forgot-code');
            const code = codeInput ? codeInput.value.trim() : '';

            if (!email || !code) {
                setForgotMessage(forgotMessage, 'Please enter the verification code.', true);
                return;
            }
            setForgotMessage(forgotMessage, 'Verifying code...', false);
            setButtonLoading(forgotVerifyCodeButton, true, 'Verifying...');
            try {
                const result = await postForgotPassword({
                    action: 'verify_code',
                    email,
                    code
                });
                if (result.success) {
                    setForgotMessage(forgotMessage, result.message || 'Code verified. Enter your new password.', false);
                    const passwordFields = document.getElementById('forgot-password-fields');
                    const codeField = document.getElementById('forgot-code-field');
                    const verifyButton = document.getElementById('forgot-verify-code-button');
                    const resetButton = document.getElementById('forgot-reset-password-button');
                    if (codeField) codeField.style.display = 'none';
                    if (passwordFields) passwordFields.style.display = 'block';
                    if (verifyButton) verifyButton.style.display = 'none';
                    if (resetButton) resetButton.style.display = 'inline-flex';
                } else {
                    setForgotMessage(forgotMessage, result.error || 'The code could not be verified.', true);
                }
            } catch (error) {
                setForgotMessage(forgotMessage, 'Unable to verify code. Please try again later.', true);
            } finally {
                setButtonLoading(forgotVerifyCodeButton, false);
            }
        }

        async function submitForgotReset() {
            const email = forgotEmailHidden ? forgotEmailHidden.value.trim() : '';
            const codeInput = document.getElementById('forgot-code');
            const newPasswordInput = document.getElementById('forgot-new-password');
            const confirmPasswordInput = document.getElementById('forgot-confirm-password');
            const code = codeInput ? codeInput.value.trim() : '';
            const newPassword = newPasswordInput ? newPasswordInput.value.trim() : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value.trim() : '';

            if (!email || !code || !newPassword || !confirmPassword) {
                setForgotMessage(forgotMessage, 'Please fill in all fields.', true);
                return;
            }
            if (newPassword !== confirmPassword) {
                setForgotMessage(forgotMessage, 'Passwords do not match.', true);
                return;
            }
            setForgotMessage(forgotMessage, 'Resetting password...', false);
            setButtonLoading(forgotResetButton, true, 'Resetting...');
            try {
                const result = await postForgotPassword({
                    action: 'reset_password',
                    email,
                    code,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                });
                if (result.success) {
                    setForgotMessage(forgotMessage, result.message || 'Your password has been reset successfully.', false);
                    if (forgotResetForm) forgotResetForm.reset();
                    if (forgotRequestForm) forgotRequestForm.reset();
                    if (forgotEmailDisplay) forgotEmailDisplay.textContent = '';
                    if (forgotEmailHidden) forgotEmailHidden.value = '';
                    showForgotStep(1, forgotStep1, forgotStep2, forgotMessage);
                } else {
                    setForgotMessage(forgotMessage, result.error || 'Unable to reset password.', true);
                }
            } catch (error) {
                setForgotMessage(forgotMessage, 'Unable to reset password. Please try again later.', true);
            } finally {
                setButtonLoading(forgotResetButton, false);
            }
        }

        if (forgotResetForm) {
            forgotResetForm.addEventListener('submit', function(event) {
                event.preventDefault();
                if (document.getElementById('forgot-password-fields').style.display === 'block') {
                    submitForgotReset();
                } else {
                    submitForgotCodeVerification();
                }
            });
        }
        if (forgotVerifyCodeButton) {
            forgotVerifyCodeButton.addEventListener('click', function() {
                submitForgotCodeVerification();
            });
        }
        if (forgotResetButton) {
            forgotResetButton.addEventListener('click', function() {
                submitForgotReset();
            });
        }

        if (forgotBackButton) {
            forgotBackButton.addEventListener('click', function() {
                showForgotStep(1, forgotStep1, forgotStep2, forgotMessage);
            });
        }

        const loginForm = document.querySelector('.container.form form');
        const loginSubmitButton = document.getElementById('login-submit-btn') || (loginForm ? loginForm.querySelector('button[type="submit"]') : null);
        if (loginForm && loginSubmitButton) {
            loginForm.addEventListener('submit', function() {
                setButtonLoading(loginSubmitButton, true, 'Logging in...');
            });
        }

        showForgotStep(1, forgotStep1, forgotStep2, forgotMessage);
    });
})();
