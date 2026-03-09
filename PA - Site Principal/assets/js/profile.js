const paymentModal = document.getElementById('payment-modal');
    const openPaymentModal = document.getElementById('open-payment-modal');
    const closePaymentModal = document.getElementById('close-payment-modal');
    const cancelPaymentModal = document.getElementById('cancel-payment-modal');
    const savedRadio = document.querySelector('input[name="banking_option"][value="saved"]');
    const newRadio = document.querySelector('input[name="banking_option"][value="new"]');
    const savedSection = document.getElementById('saved-details-section');
    const newSection = document.getElementById('new-details-section');
    const savedIdInput = document.getElementById('banking_details_id');
    const ribInput = document.getElementById('rib');
    const ibanInput = document.getElementById('iban');
    const bicInput = document.getElementById('bic');
    const holderInput = document.getElementById('account_holder_name');
    const paymentForm = document.getElementById('payment-request-form');
    const feedback = document.getElementById('payment-feedback');
    const balanceTotal = document.getElementById('balance-total');
    const balanceAvailable = document.getElementById('balance-available');
    const amountInput = document.getElementById('amount');

    function toggleBankingSections() {
        const useSaved = !!(savedRadio && savedRadio.checked);
        if (savedSection) savedSection.style.display = useSaved ? 'block' : 'none';
        if (newSection) newSection.style.display = useSaved ? 'none' : 'block';
        if (savedIdInput) savedIdInput.required = useSaved;
        if (ribInput) ribInput.required = !useSaved;
        if (ibanInput) ibanInput.required = !useSaved;
        if (bicInput) bicInput.required = !useSaved;
        if (holderInput) holderInput.required = !useSaved;
    }

    function openModal() {
        paymentModal.classList.add('is-visible');
        document.body.classList.add('modal-open');
        paymentModal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        paymentModal.classList.remove('is-visible');
                         document.body.classList.remove('modal-open');
        paymentModal.setAttribute('aria-hidden', 'true');
    }

    if (paymentModal && openPaymentModal) {
        openPaymentModal.addEventListener('click', openModal);
        closePaymentModal.addEventListener('click', closeModal);
        cancelPaymentModal.addEventListener('click', closeModal);
        paymentModal.addEventListener('click', (event) => {
            if (event.target === paymentModal) {
                closeModal();
            }
        });
    }

    if (savedRadio) savedRadio.addEventListener('change', toggleBankingSections);
    if (newRadio) newRadio.addEventListener('change', toggleBankingSections);
    toggleBankingSections();

    if (paymentForm) {
        paymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (feedback) {
                feedback.textContent = '';
                feedback.className = '';
            }

            const submitButton = paymentForm.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            try {
                const formData = new FormData(paymentForm);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json().catch(() => null);
                if (!data) {
                    throw new Error('Invalid response');
                }

                if (!data.success) {
                    if (feedback) {
                        feedback.textContent = data.message || 'Unable to create payment request.';
                        feedback.className = 'error-message';
                    }
                        hideLoader(true);
                    return;
                }

                if (feedback) {
                    feedback.textContent = data.message || 'Payment request created successfully.';
                    feedback.className = 'success-message';
                }

                if (Array.isArray(data.banking_details) && data.banking_details.length > 0) {
                    try {
                        if (savedRadio) {
                            savedRadio.disabled = false;
                            savedRadio.checked = true;
                        }
                        if (savedIdInput) {
                            savedIdInput.disabled = false;
                            savedIdInput.required = true;
                            savedIdInput.innerHTML = '';
                            data.banking_details.forEach(function(d) {
                                var opt = document.createElement('option');
                                opt.value = d.id || '';
                                var label = ((d.iban || '') + ' ' + (d.account_holder_name || '')).trim();
                                opt.textContent = label || 'Saved banking details';
                                savedIdInput.appendChild(opt);
                            });
                        }
                        toggleBankingSections();
                    } catch (e) {
                        console.warn('Could not refresh saved banking details UI', e);
                    }
                }

                if (typeof data.balance === 'number') {
                    const formatted = data.balance.toFixed(2);
                    if (balanceTotal) balanceTotal.textContent = formatted;
                    if (balanceAvailable) balanceAvailable.textContent = formatted;
                    if (amountInput) {
                        amountInput.max = formatted;
                        amountInput.value = formatted;
                    }
                }

                closeModal();
                    hideLoader(true);
            } catch (error) {
                if (feedback) {
                    feedback.textContent = 'Unable to create payment request.';
                    feedback.className = 'error-message';
                }
                    hideLoader(true);
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        hideLoader(true);
        var initial = document.getElementById('upcycling-score-value');
        if (initial) {
            var m = initial.textContent.match(/^(\d+)/);
            if (m) updateGauge(parseFloat(m[1]));
        }
    });

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const tab = this.getAttribute('data-tab');
        document.querySelectorAll('.tab-content').forEach(tc => tc.style.display = 'none');

        if (tab === 'general') {
            document.getElementById('general-tab').style.display = '';
        } else if (tab === 'business') {
            document.getElementById('business-tab').style.display = '';
        } else if (tab === 'security') {
            document.getElementById('security-tab').style.display = '';
        } else if (tab === 'upcyclingScore') {
            document.getElementById('upcyclingScore-tab').style.display = '';
            var initial = document.getElementById('upcycling-score-value');
            if (initial) {
                var text = initial.textContent || '';
                var m = text.match(/^(\d+(?:\.\d+)?)/);
                if (m) updateGauge(parseFloat(m[1]));
            }
        } else if (tab === 'myupdoc') {
            document.getElementById('myupdoc-tab').style.display = '';
        } else if (tab === 'badges') {
            document.getElementById('badges-tab').style.display = '';
        } else if (tab === 'mfa') {
            document.getElementById('mfa-tab').style.display = '';
        }
    });
});

var gaugeMax = 100;

var lastPct = 0;

function drawGaugeCanvas(score) {
    var canvas = document.getElementById('upcycling-gauge-chart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var w = canvas.width;
    var h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    var radius = Math.min(w/2, h) - 10;
    ctx.lineWidth = 10;
    ctx.strokeStyle = '#ddd';
    ctx.beginPath();
    ctx.arc(w/2, h, radius, Math.PI, 0, false);
    ctx.stroke();
    var pct = Math.min(Math.max(score / gaugeMax, 0), 1);
    var color;
    if (score >= 100) {
        pct = 1;
        color = '#e11d48';
    } else if (score < 10) {
        color = '#10b981';
    } else if (score < 50) {
        color = '#facc15';
    } else if (score < 70) {
        color = '#f97316';
    } else {
        color = '#e11d48';
    }
    var endAngle = Math.PI + pct * Math.PI;
    ctx.strokeStyle = color;
    ctx.beginPath();
    ctx.arc(w/2, h, radius, Math.PI, endAngle, false);
    ctx.stroke();
    ctx.strokeStyle = '#444';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w/2, h);
    var nx = w/2 + Math.cos(endAngle) * (radius - 5);
    var ny = h + Math.sin(endAngle) * (radius - 5);
    ctx.lineTo(nx, ny);
    ctx.stroke();
    ctx.fillStyle = '#444';
    ctx.beginPath();
    ctx.arc(w/2, h, 4, 0, 2*Math.PI);
    ctx.fill();
}

function updateGauge(score) {
    var targetPct = Math.min(Math.max(score / gaugeMax, 0), 1);
    var startPct = lastPct;
    var duration = 600;
    var startTime = null;
    function animate(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var currentPct = startPct + (targetPct - startPct) * progress;
        drawGaugeCanvas(currentPct * gaugeMax);
        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            lastPct = targetPct;
        }
    }
    requestAnimationFrame(animate);
}

document.querySelectorAll('.btn-edit-inline').forEach(btn => {
    btn.addEventListener('click', function () {
        const field = this.getAttribute('data-edit');
        const row = this.closest('.profile-field-row');
        const valueSpan = document.getElementById(field + '-value');
        if (!valueSpan || row.querySelector('.profile-edit-input')) return;

        const originalValue = valueSpan.textContent.trim();
        const editBtn = this;

        const input = document.createElement('input');
        input.type = field === 'email' ? 'email' : 'text';
        input.value = originalValue;
        input.className = 'profile-edit-input';

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn-edit-save';
        saveBtn.title = 'Save';
        saveBtn.innerHTML = '<i class="fa-solid fa-check"></i>';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn-edit-cancel';
        cancelBtn.title = 'Cancel';
        cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';

        valueSpan.replaceWith(input);
        editBtn.style.display = 'none';
        editBtn.insertAdjacentElement('afterend', cancelBtn);
        editBtn.insertAdjacentElement('afterend', saveBtn);
        input.focus();
        input.select();

        function cancelEdit() {
            const span = document.createElement('span');
            span.id = field + '-value';
            span.textContent = originalValue;
            input.replaceWith(span);
            saveBtn.remove();
            cancelBtn.remove();
            const errMsg = row.querySelector('.edit-error-msg');
            if (errMsg) errMsg.remove();
            editBtn.style.display = '';
        }

        async function saveEdit() {
            const newValue = input.value.trim();
            if (newValue === originalValue) { cancelEdit(); return; }
            
            var addressFields = ['user_road_number','user_road','user_zip_code','user_city'];
            if (addressFields.includes(field)) {
                var anyOther = addressFields.some(f => {
                    if (f === field) return false;
                    var el = document.getElementById(f + '-value');
                    return el && el.textContent.trim() !== '';
                });
                if (anyOther && newValue === '') {
                    input.classList.add('input-error');
                    let errMsg = row.querySelector('.edit-error-msg');
                    if (!errMsg) {
                        errMsg = document.createElement('span');
                        errMsg.className = 'edit-error-msg';
                        cancelBtn.insertAdjacentElement('afterend', errMsg);
                    }
                    errMsg.textContent = 'Field required when any other address part is set.';
                    setTimeout(() => {
                        input.classList.remove('input-error');
                        if (errMsg) errMsg.remove();
                    }, 3000);
                    return;
                }
            }
            if (!newValue) return;

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('update-profile-api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ field, value: newValue })
                });
                const data = await res.json();
                if (!res.ok || data.error) throw new Error(data.error || 'Update failed');

                const span = document.createElement('span');
                span.id = field + '-value';
                span.textContent = newValue;
                input.replaceWith(span);
                saveBtn.remove();
                cancelBtn.remove();
                editBtn.style.display = '';

                // if this was an address component, refresh combined display
                if ([
                    'user_road_number',
                    'user_road',
                    'user_zip_code',
                    'user_city'
                ].includes(field)) {
                    updateCombinedAddress();
                }

                span.style.transition = 'color .4s';
                span.style.color = '#10b981';
                setTimeout(() => { span.style.color = ''; }, 1800);
            } catch (err) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
                input.classList.add('input-error');
                let errMsg = row.querySelector('.edit-error-msg');
                if (!errMsg) {
                    errMsg = document.createElement('span');
                    errMsg.className = 'edit-error-msg';
                    cancelBtn.insertAdjacentElement('afterend', errMsg);
                }
                errMsg.textContent = err.message;
                setTimeout(() => {
                    input.classList.remove('input-error');
                    if (errMsg) errMsg.remove();
                }, 3500);
            }
        }

        saveBtn.addEventListener('click', saveEdit);
        cancelBtn.addEventListener('click', cancelEdit);
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); saveEdit(); }
            if (e.key === 'Escape') { cancelEdit(); }
        });
    });
});

// helper: update combined address display whenever a component changes
function updateCombinedAddress() {
    var parts = [];
    ['user_road_number','user_road','user_zip_code','user_city'].forEach(function(f){
        var el = document.getElementById(f + '-value');
        if (el) {
            var txt = el.textContent.trim();
            if (txt) parts.push(txt);
        }
    });
    var addrEl = document.getElementById('address-value');
    if (addrEl) {
        if (parts.length > 0) {
            addrEl.textContent = parts.join(' ');
            addrEl.parentElement.parentElement.style.display = '';
        } else {
            addrEl.textContent = '—';
            // hide full address row
            var row = addrEl.closest('.full-address-row');
            if (row) row.style.display = 'none';
        }
    }
}

// open/close address modal and geocode
var addressMap, addressMarker;
function openAddressModal(addr) {
    if (!addr || addr === '—') return;
    var modal = document.getElementById('address-modal');
    if (!modal) return;
    modal.classList.add('is-visible');
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden', 'false');

    // lazy init map
    setTimeout(function() {
        if (!addressMap) {
            addressMap = L.map('address-map').setView([0,0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(addressMap);
        }

        // geocode via Nominatim
        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr))
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (data && data.length) {
                    var lat = parseFloat(data[0].lat);
                    var lon = parseFloat(data[0].lon);
                    addressMap.setView([lat, lon], 16);
                    if (addressMarker) {
                        addressMarker.setLatLng([lat,lon]);
                    } else {
                        addressMarker = L.marker([lat,lon]).addTo(addressMap);
                    }
                }
            }).catch(function(err){ console.warn('Geocode failed', err); });
    }, 50);
}

function closeAddressModal() {
    var modal = document.getElementById('address-modal');
    if (!modal) return;
    modal.classList.remove('is-visible');
    document.body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden', 'true');
}

// click handler for combined address
document.addEventListener('click', function(e) {
    if (e.target.closest('.address-clickable')) {
        e.preventDefault();
        var addrEl = document.getElementById('address-value');
        if (addrEl) openAddressModal(addrEl.textContent);
    }
});

// close modal when clicking outside or using close button
var addrModal = document.getElementById('address-modal');
if (addrModal) {
    addrModal.addEventListener('click', function(ev) {
        if (ev.target === addrModal) closeAddressModal();
    });
    var closeBtn = document.getElementById('address-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeAddressModal);
}


document.querySelectorAll('.btn-copy').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        const text = this.getAttribute('data-copy') || '';
        if (!text) return;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            this.classList.add('copied');
            const icon = this.querySelector('i');
            if (icon) { icon.className = 'fa-solid fa-check'; }
            const prevTitle = this.getAttribute('title') || '';
            this.setAttribute('title', 'Copied!');

            setTimeout(() => {
                this.classList.remove('copied');
                if (icon) { icon.className = 'fa-solid fa-copy'; }
                this.setAttribute('title', prevTitle);
            }, 1600);
        } catch (err) {
            this.classList.add('copy-failed');
            setTimeout(() => this.classList.remove('copy-failed'), 1400);
            console.warn('Copy failed', err);
        }
    });
});

function hideLoader(immediate = false) {
    var loader = document.getElementById('planning-preloader');
    var initial = document.getElementById('initial-loader');
    var main = document.getElementById('main-content');

    if (loader) {
        if (immediate) {
            loader.style.display = 'none';
        } else {
            setTimeout(function() {
                loader.style.display = 'none';
            }, 5000);
        }
    }

    if (initial) {
        initial.style.display = 'none';
    }
    if (main) {
        main.style.visibility = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    hideLoader(false);

    // refresh formatted address display if present
    updateCombinedAddress();

    // ensure address-accordion row visibility
    var faRow = document.querySelector('.full-address-row');
    if (faRow) {
        var addr = document.getElementById('address-value');
        if (addr && addr.textContent.trim() !== '' && addr.textContent.trim() !== '—') {
            faRow.style.display = '';
        } else {
            faRow.style.display = 'none';
        }
    }

    // if there is any feedback under password form, show security tab
    var pwdFeedback = document.getElementById('password-feedback');
    if (pwdFeedback && pwdFeedback.textContent.trim().length > 0) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        var secBtn = document.querySelector('.tab-btn[data-tab="security"]');
        if (secBtn) secBtn.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(tc => tc.style.display = 'none');
        var secTab = document.getElementById('security-tab');
        if (secTab) secTab.style.display = '';
    }

    (function() {
        var picSection = document.querySelector('.profile-picture-section');
        var profileImg = document.getElementById('profile-pic-preview');
        var placeholder = 'data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==';
        if (!picSection || !profileImg) return;

        function markLoaded() {
            picSection.classList.remove('loading');
            picSection.classList.add('loaded');
        }
        picSection.classList.add('loading');

        if (profileImg.complete && profileImg.naturalWidth > 1 && profileImg.src && profileImg.src !== placeholder) {
            markLoaded();
        } else {
            profileImg.addEventListener('load', function onLoad() {
                markLoaded();
                profileImg.removeEventListener('load', onLoad);
            });
            profileImg.addEventListener('error', function onErr() {
                markLoaded();
            });
            setTimeout(markLoaded, 4500);
        }
    })();
});

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

var newPasswordInput = document.querySelector('.password-input[data-strength="true"]');
if (newPasswordInput) {
    var meter = newPasswordInput.closest('.field').querySelector('.password-meter');
    var text = meter ? meter.querySelector('.password-meter-text') : null;
    if (meter && text) {
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
            var value = newPasswordInput.value || '';
            var strength = getStrength(value);
            meter.classList.remove('is-weak', 'is-medium', 'is-strong');
            if (!strength.label) {
                text.textContent = 'Strength';
                return;
            }
            meter.classList.add(strength.className);
            text.textContent = 'Strength: ' + strength.label;
        }
        newPasswordInput.addEventListener('input', updateMeter);
        updateMeter();
    }
}

var passwordForm = document.querySelector('.change-password-form');
var passwordFeedback = document.getElementById('password-feedback');
var passwordSuccessModal = document.getElementById('password-success-modal');
var closePasswordSuccessBtn = document.getElementById('close-password-success');
var passwordSuccessOk = document.getElementById('password-success-ok');

function openPasswordSuccessModal() {
    if (!passwordSuccessModal) return;
    passwordSuccessModal.classList.add('is-visible');
    document.body.classList.add('modal-open');
    passwordSuccessModal.setAttribute('aria-hidden', 'false');
}

function closePasswordSuccessModal() {
    if (!passwordSuccessModal) return;
    passwordSuccessModal.classList.remove('is-visible');
    document.body.classList.remove('modal-open');
    passwordSuccessModal.setAttribute('aria-hidden', 'true');
}

if (closePasswordSuccessBtn) {
    closePasswordSuccessBtn.addEventListener('click', closePasswordSuccessModal);
}
if (passwordSuccessOk) {
    passwordSuccessOk.addEventListener('click', closePasswordSuccessModal);
}
if (passwordSuccessModal) {
    passwordSuccessModal.addEventListener('click', function(e) {
        if (e.target === passwordSuccessModal) {
            closePasswordSuccessModal();
        }
    });
}

if (passwordForm) {
    passwordForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (passwordFeedback) {
            passwordFeedback.textContent = '';
            passwordFeedback.className = '';
        }
        var submitBtn = passwordForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
            var formData = new FormData(passwordForm);
            formData.append('form_type', 'password_change');
            var response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            var data = await response.json().catch(function() { return null; });
            if (!data) throw new Error('Invalid response');
            if (!data.success) {
                if (passwordFeedback) {
                    passwordFeedback.textContent = data.message || 'Unable to change password.';
                    passwordFeedback.className = 'error-message';
                }
                return;
            }
            openPasswordSuccessModal();
            passwordForm.reset();
        } catch (error) {
            if (passwordFeedback) {
                passwordFeedback.textContent = 'Unable to change password.';
                passwordFeedback.className = 'error-message';
            }
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}
