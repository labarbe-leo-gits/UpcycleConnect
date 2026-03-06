(function() {
    document.querySelectorAll('.password-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var wrapper = toggle.closest('.password-wrapper');
            var input = wrapper ? wrapper.querySelector('input') : null;
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            toggle.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        });
    });
    const forgotLink = document.querySelector('.forgot-password');
    const forgotModal = document.getElementById('forgot-password-modal');
    const closeForgot = document.getElementById('close-forgot-modal');
    const cancelForgot = document.getElementById('cancel-forgot-modal');
    const forgotOk = document.getElementById('forgot-ok');

    function openForgotModal() {
        if (!forgotModal) return;
        forgotModal.classList.add('is-visible');
        document.body.classList.add('modal-open');
        forgotModal.setAttribute('aria-hidden', 'false');
    }
    function closeForgotModal() {
        if (!forgotModal) return;
        forgotModal.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
        forgotModal.setAttribute('aria-hidden', 'true');
    }

    window.openForgotModal = openForgotModal;
    window.closeForgotModal = closeForgotModal;

    if (forgotLink) {
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            openForgotModal();
        });
    }
    if (closeForgot) closeForgot.addEventListener('click', closeForgotModal);
    if (cancelForgot) cancelForgot.addEventListener('click', closeForgotModal);
    if (forgotOk) forgotOk.addEventListener('click', closeForgotModal);
    if (forgotModal) {
        forgotModal.addEventListener('click', function(event) {
            if (event.target === forgotModal) {
                closeForgotModal();
            }
        });
    }

    function checkAutoOpen() {
        if (window.shouldOpenForgotModal) {
            openForgotModal();
        }
    }

    document.addEventListener('DOMContentLoaded', checkAutoOpen);
})();
