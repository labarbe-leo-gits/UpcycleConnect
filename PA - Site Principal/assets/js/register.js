(function() {
    var container = document.querySelector('.register-forms');
    if (!container) return;

    var siteKey = container.getAttribute('data-site-key') || '';
    var initialForm = container.getAttribute('data-active-form') || 'customer';
    var forms = document.querySelectorAll('form.register-form');
    var switcherButtons = document.querySelectorAll('.switcher-btn');
    var stage = document.querySelector('.register-forms-stage');

    function updateStageHeight() {
        var activeForm = document.querySelector('form.register-form.is-active');
        if (stage && activeForm) {
            stage.style.minHeight = activeForm.scrollHeight + 'px';
        }
    }

    function setActive(target) {
        forms.forEach(function(form) {
            var isActive = form.getAttribute('data-form') === target;
            form.classList.toggle('is-active', isActive);
            form.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });

        switcherButtons.forEach(function(button) {
            var isPressed = button.getAttribute('data-target') === target;
            button.classList.toggle('is-active', isPressed);
            button.setAttribute('aria-pressed', isPressed ? 'true' : 'false');
        });

        requestAnimationFrame(updateStageHeight);
    }

    switcherButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            setActive(button.getAttribute('data-target'));
        });
    });

    setActive(initialForm);
    window.addEventListener('resize', updateStageHeight);
    window.addEventListener('load', updateStageHeight);
    forms.forEach(function(form) {
        form.addEventListener('transitionend', updateStageHeight);
    });

    if (window.grecaptcha && siteKey) {
        window.grecaptcha.ready(function() {
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    window.grecaptcha.execute(siteKey, { action: 'register' })
                        .then(function(token) {
                            var tokenField = form.querySelector('.recaptcha-token');
                            if (tokenField) {
                                tokenField.value = token;
                            }
                            form.submit();
                        });
                });
            });
        });
    }

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

    document.querySelectorAll('.password-input[data-strength="true"]').forEach(function(input) {
        var meter = input.closest('.field').querySelector('.password-meter');
        var text = meter ? meter.querySelector('.password-meter-text') : null;
        if (!meter || !text) return;

        function meetsCriteria(value) {
            return {
                length: value.length >= 8,
                lower: /[a-z]/.test(value),
                upper: /[A-Z]/.test(value),
                number: /\d/.test(value),
                special: /[^a-zA-Z0-9]/.test(value)
            };
        }

        function getStrength(value) {
            var criteria = meetsCriteria(value);
            var allRequired = criteria.length && criteria.lower && criteria.upper && criteria.number && criteria.special;
            if (!value.length) {
                return { label: '', className: '' };
            }
            if (!allRequired) {
                return { label: 'Weak', className: 'is-weak' };
            }
            if (value.length >= 12) {
                return { label: 'Strong', className: 'is-strong' };
            }
            return { label: 'Medium', className: 'is-medium' };
        }

        function updateMeter() {
            var value = input.value || '';
            var strength = getStrength(value);
            meter.classList.remove('is-weak', 'is-medium', 'is-strong');
            if (!strength.label) {
                text.textContent = 'Strength';
                return;
            }
            meter.classList.add(strength.className);
            text.textContent = 'Strength: ' + strength.label;
        }

        input.addEventListener('input', updateMeter);
        updateMeter();
    });
})();
