(function() {
    var container = document.querySelector('.register-forms');
    if (!container) return;

    var siteKey = container.getAttribute('data-site-key') || '';
    var initialForm = container.getAttribute('data-active-form') || 'customer';
    var forms = document.querySelectorAll('form.register-form');
    var switcherButtons = document.querySelectorAll('.switcher-btn');
    var stage = document.querySelector('.register-forms-stage');
    var apiBase = (window.API_BASE || (window.location.protocol + '//' + window.location.hostname + ':9999')).replace(/\/$/, '');

    function normalizeDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function setFieldStatus(element, message, statusClass) {
        if (!element) return;
        element.textContent = message;
        element.classList.remove('status-available', 'status-unavailable', 'error-message');
        if (statusClass) {
            element.classList.add(statusClass);
        }
    }

    function debounce(callback, delay) {
        var timeout;
        return function() {
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                callback.apply(null, args);
            }, delay);
        };
    }

    function checkUsernameAvailability(input) {
        var rawValue = input.value || '';
        var username = rawValue.trim();
        var field = input.closest('.field');
        var status = field ? field.querySelector('.field-status') : null;
        if (!status) return;
        if (username === '') {
            setFieldStatus(status, '', '');
            return;
        }
        if (/\s/.test(username)) {
            setFieldStatus(status, 'Username cannot contain spaces', 'error-message');
            return;
        }
        if (username.length < 3) {
            setFieldStatus(status, 'Username must be at least 3 characters', 'error-message');
            return;
        }
        setFieldStatus(status, 'Checking availability...', '');
        fetch(apiBase + '/profile/' + encodeURIComponent(username), {
            method: 'GET'
        }).then(function(response) {
            if (response.ok) {
                setFieldStatus(status, 'This username is already taken', 'error-message');
            } else if (response.status === 404) {
                setFieldStatus(status, 'Username is available', 'status-available');
            } else {
                setFieldStatus(status, 'Unable to verify username availability', 'error-message');
            }
        }).catch(function() {
            setFieldStatus(status, 'Unable to verify username availability', 'error-message');
        });
    }

    function extractCompanyName(item) {
        if (!item || typeof item !== 'object') return '';
        return item.nom_raison_sociale || item.nom_complet || item.nom_commercial || item.nom_entreprise || item.nom || (item.unite_legale && item.unite_legale.denomination) || (item.unite_legale && item.unite_legale.denomination_usuelle) || '';
    }

    function checkSiretValue(input) {
        var rawValue = input.value || '';
        var cleaned = normalizeDigits(rawValue);
        var field = input.closest('.field');
        var status = field ? field.querySelector('.field-status') : null;
        var companyField = document.getElementById('company_name_artisan');
        if (!status) return;
        if (cleaned === '') {
            setFieldStatus(status, '', '');
            return;
        }
        if (cleaned.length !== 9 && cleaned.length !== 14) {
            setFieldStatus(status, 'Enter a 9-digit SIREN or 14-digit SIRET', 'error-message');
            return;
        }
        setFieldStatus(status, 'Checking SIRET / SIREN...', '');
        fetch('https://recherche-entreprises.api.gouv.fr/search?q=' + encodeURIComponent(cleaned) + '&per_page=1').then(function(response) {
            if (!response.ok) {
                throw new Error('invalid response');
            }
            return response.json();
        }).then(function(data) {
            var item = Array.isArray(data.results) ? data.results[0] : null;
            if (!item) {
                throw new Error('not found');
            }
            var itemSiret = normalizeDigits((item.siege && item.siege.siret) || item.siret || '');
            var itemSiren = normalizeDigits(item.siren || '');
            if (cleaned.length === 14) {
                if (itemSiret !== cleaned) {
                    throw new Error('not found');
                }
            } else if (cleaned.length === 9) {
                if (itemSiren !== cleaned && itemSiret.indexOf(cleaned) !== 0) {
                    throw new Error('not found');
                }
            }
            var companyName = extractCompanyName(item);
            setFieldStatus(status, 'Valid SIRET / SIREN', 'status-available');
            if (companyField && companyName) {
                companyField.value = companyName;
            }
        }).catch(function() {
            setFieldStatus(status, 'SIRET/SIREN not found or invalid', 'error-message');
        });
    }

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

    document.querySelectorAll('input[name="username"]').forEach(function(input) {
        input.addEventListener('input', debounce(function() {
            checkUsernameAvailability(input);
        }, 500));
    });

    var siretInput = document.getElementById('siret_artisan');
    if (siretInput) {
        siretInput.addEventListener('input', debounce(function() {
            checkSiretValue(siretInput);
        }, 500));
    }

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
                            
                            var firstName = form.querySelector('[name="first_name"]')?.value || '';
                            var lastName = form.querySelector('[name="last_name"]')?.value || '';
                            var username = form.querySelector('[name="username"]')?.value || '';
                            var companyName = form.querySelector('[name="company_name"]')?.value || '';
                            
                            var fieldsToCheck = {
                                'first_name': firstName,
                                'last_name': lastName,
                                'username': username
                            };
                            
                            if (companyName.trim()) {
                                fieldsToCheck['company_name'] = companyName;
                            }
                            
                            if (typeof Moderator !== 'undefined' && Moderator.checkFields) {
                                Moderator.checkFields(fieldsToCheck)
                                    .then(function(checkResult) {
                                        if (checkResult.valid) {
                                            form.submit();
                                        } else {
                                            var errorsList = Object.values(checkResult.errors).join('\n');
                                            alert('Content moderation:\n\n' + errorsList);
                                        }
                                    })
                                    .catch(function(err) {
                                        console.error('Moderation check error:', err);
                                        form.submit();
                                    });
                            } else {
                                form.submit();
                            }
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
