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

    const profilePictureInput = document.getElementById('profile-picture-input');
    const uploadProfilePictureBtn = document.getElementById('upload-profile-picture-btn');
    const profilePictureFeedback = document.getElementById('profile-picture-feedback');
    const profilePictureHistory = document.getElementById('profile-picture-history');
    const profilePicPreview = document.getElementById('profile-pic-preview');

    console.log('profile.js loaded');
    console.log('profile picture controls:', {
        uploadProfilePictureBtn,
        profilePictureInput,
        profilePictureFeedback,
        profilePictureHistory,
        profilePicPreview
    });

    function getProfileImageUrl(picture) {
        if (!picture) return '';
        if (/^(https?:)?\/\//.test(picture) || picture.startsWith('/')) {
            return picture;
        }
        if (profilePicPreview && profilePicPreview.dataset && profilePicPreview.dataset.blobSrc) {
            var base = profilePicPreview.dataset.blobSrc;
            if (base.indexOf('/') !== -1) {
                base = base.replace(/[^/]*$/, '');
                return base + encodeURIComponent(picture);
            }
        }
        return new URL('../../files/uploads/user/' + encodeURIComponent(picture), window.location.href).href;
    }

    function renderProfilePictureHistory(history) {
        if (!profilePictureHistory) return;
        if (!Array.isArray(history) || history.length === 0) {
            var currentAvatarHtml = '';
            if (profilePicPreview && profilePicPreview.src && !profilePicPreview.src.includes('defaultUser.png')) {
                currentAvatarHtml = '<div style="margin-bottom:.75rem;color:#444;font-size:.95rem;">Current avatar</div>' +
                    '<div class="history-grid" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;">' +
                    '<div class="history-thumb" style="text-align:center;max-width:80px;position:relative;overflow:hidden;">' +
                    '<img src="' + profilePicPreview.src + '" alt="Current avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">' +
                    '<div style="font-size:.75rem;color:#444;margin-top:.35rem;">Current</div>' +
                    '</div>' +
                    '</div>';
            }
            profilePictureHistory.innerHTML = currentAvatarHtml + '<p class="history-empty" style="color:#666;font-size:.95rem;">No previous profile pictures yet.</p>';
            return;
        }

        profilePictureHistory.innerHTML = '<div class="history-grid" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;">' + history.map(function(item) {
            var url = item.picture_url ? item.picture_url : getProfileImageUrl(item.picture);
            var label = item.created_at ? item.created_at.split(' ')[0] : '';
            return '<div class="history-thumb" data-history-id="' + (item.id || '') + '" style="text-align:center;max-width:80px;position:relative;overflow:hidden;cursor:pointer;">' +
                '<img src="' + url + '" alt="Previous avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">' +
                '<button type="button" class="history-delete-btn" title="Delete this avatar" style="position:absolute;top:6px;right:6px;opacity:0;transition:opacity .18s ease;display:flex;align-items:center;justify-content:center;width:26px;height:26px;border:none;border-radius:50%;background:rgba(255,255,255,0.95);color:#c00;box-shadow:0 1px 4px rgba(0,0,0,0.18);">' +
                '<i class="fa-solid fa-trash" aria-hidden="true"></i>' +
                '</button>' +
                (label ? '<div style="font-size:.75rem;color:#444;margin-top:.35rem;">' + label + '</div>' : '') +
                '</div>';
        }).join('') + '</div>';
    }

    function setupProfilePictureHistoryActions() {
        if (!profilePictureHistory) return;
        profilePictureHistory.addEventListener('click', async function(event) {
            var deleteBtn = event.target.closest('.history-delete-btn');
            var thumb = event.target.closest('.history-thumb');
            if (!thumb || !thumb.dataset || !thumb.dataset.historyId) return;
            var historyId = thumb.dataset.historyId;
            if (deleteBtn) {
                event.stopPropagation();
                var confirmed = await showConfirmModal('Delete this previous avatar from history?', 'Delete avatar');
                if (!confirmed) return;
                await deleteProfilePictureHistoryItem(historyId);
                return;
            }
            await restoreProfilePictureFromHistory(historyId);
        });
        profilePictureHistory.addEventListener('mouseover', function(event) {
            var thumb = event.target.closest('.history-thumb');
            if (!thumb) return;
            var btn = thumb.querySelector('.history-delete-btn');
            if (btn) btn.style.opacity = '1';
        });
        profilePictureHistory.addEventListener('mouseout', function(event) {
            var thumb = event.target.closest('.history-thumb');
            if (!thumb) return;
            var btn = thumb.querySelector('.history-delete-btn');
            if (btn) btn.style.opacity = '0';
        });
    }

    function showConfirmModal(message, title = 'Confirm action') {
        return new Promise(function(resolve) {
            var modalId = 'profile-history-confirm-modal';
            var modal = document.getElementById(modalId);
            if (!modal) {
                modal = document.createElement('div');
                modal.id = modalId;
                modal.className = 'modal-overlay';
                modal.innerHTML = '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="' + modalId + '-title">' +
                    '<div class="modal-header">' +
                    '<h2 id="' + modalId + '-title" style="font-size:1.1rem;margin:0;">' + title + '</h2>' +
                    '<button type="button" class="modal-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    '<div class="modal-body" style="padding:0 0 10px;">' +
                    '<p style="margin:0;color:#333;">' + message + '</p>' +
                    '</div>' +
                    '<div class="modal-actions" style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1rem;">' +
                    '<button type="button" class="btn-secondary" id="' + modalId + '-cancel">Cancel</button>' +
                    '<button type="button" class="btn-primary" id="' + modalId + '-confirm">Confirm</button>' +
                    '</div>' +
                    '</div>';
                document.body.appendChild(modal);
            }
            var closeBtn = modal.querySelector('.modal-close');
            var cancelBtn = modal.querySelector('#' + modalId + '-cancel');
            var confirmBtn = modal.querySelector('#' + modalId + '-confirm');
            var titleEl = modal.querySelector('h2');
            var bodyEl = modal.querySelector('.modal-body p');
            if (titleEl) titleEl.textContent = title;
            if (bodyEl) bodyEl.textContent = message;

            function close(result) {
                modal.classList.remove('is-visible');
                document.body.classList.remove('modal-open');
                cleanup();
                resolve(result);
            }
            function cleanup() {
                closeBtn?.removeEventListener('click', onClose);
                cancelBtn?.removeEventListener('click', onClose);
                confirmBtn?.removeEventListener('click', onConfirm);
                modal.removeEventListener('click', onBackdrop);
            }
            function onClose(event) {
                event.stopPropagation();
                close(false);
            }
            function onConfirm(event) {
                event.stopPropagation();
                close(true);
            }
            function onBackdrop(event) {
                if (event.target === modal) {
                    close(false);
                }
            }
            closeBtn?.addEventListener('click', onClose);
            cancelBtn?.addEventListener('click', onClose);
            confirmBtn?.addEventListener('click', onConfirm);
            modal.addEventListener('click', onBackdrop);
            modal.classList.add('is-visible');
            document.body.classList.add('modal-open');
        });
    }

    async function restoreProfilePictureFromHistory(historyId) {
        if (!window.currentUserId || !profilePicPreview) return;
        try {
            const response = await authedFetch('/users/' + encodeURIComponent(window.currentUserId) + '/profile-picture/history/' + encodeURIComponent(historyId) + '/restore', {
                method: 'PATCH'
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || !data.success) {
                throw new Error((data && (data.error || data.message)) || 'Unable to restore profile picture.');
            }
            if (profilePicPreview) {
                profilePicPreview.src = getProfileImageUrl(data.profile_picture_url || data.profile_picture);
            }
            if (Array.isArray(data.history)) {
                renderProfilePictureHistory(data.history);
            } else {
                await loadProfilePictureHistory();
            }
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = 'Profile picture restored successfully.';
                profilePictureFeedback.className = 'success-message';
            }
        } catch (err) {
            console.warn(err);
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = err.message || 'Unable to restore profile picture.';
                profilePictureFeedback.className = 'error-message';
            }
        }
    }

    async function deleteProfilePictureHistoryItem(historyId) {
        if (!window.currentUserId) return;
        try {
            const response = await authedFetch('/users/' + encodeURIComponent(window.currentUserId) + '/profile-picture/history/' + encodeURIComponent(historyId), {
                method: 'DELETE'
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || !data.success) {
                throw new Error((data && (data.error || data.message)) || 'Unable to delete profile picture history item.');
            }
            if (Array.isArray(data.history)) {
                renderProfilePictureHistory(data.history);
            } else {
                await loadProfilePictureHistory();
            }
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = 'Previous avatar deleted.';
                profilePictureFeedback.className = 'success-message';
            }
        } catch (err) {
            console.warn(err);
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = err.message || 'Unable to delete profile picture history item.';
                profilePictureFeedback.className = 'error-message';
            }
        }
    }

    async function loadProfilePictureHistory() {
        if (!window.currentUserId || !profilePictureHistory) return;
        try {
            const response = await authedFetch('/users/' + encodeURIComponent(window.currentUserId) + '/profile-picture/history');
            const data = await response.json().catch(() => null);
            if (response.ok && data && Array.isArray(data.history)) {
                renderProfilePictureHistory(data.history);
            }
        } catch (err) {
            console.warn('Unable to load profile picture history', err);
        }
    }

    async function restoreProfilePictureFromApi() {
        if (!window.currentUserId || !profilePicPreview) return;
        try {
            console.log('restoreProfilePictureFromApi: calling API for user', window.currentUserId);
            const response = await authedFetch('/users/' + encodeURIComponent(window.currentUserId) + '/profile-picture');
            console.log('restoreProfilePictureFromApi: response status', response.status);
            const data = await response.json().catch(() => null);
            console.log('restoreProfilePictureFromApi: response data', data);
            if (response.ok && data && typeof data.profile_picture_url === 'string' && data.profile_picture_url.trim() !== '') {
                const profileUrl = getProfileImageUrl(data.profile_picture_url);
                console.log('restoreProfilePictureFromApi: resolved profileUrl', profileUrl);
                profilePicPreview.src = profileUrl;
            }
        } catch (err) {
            console.warn('Could not restore profile picture from API', err);
        }
    }

    async function handleProfilePictureUpload(file) {
        if (!file) return;
        if (!/^image\//.test(file.type)) {
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = 'Please upload a valid image file.';
                profilePictureFeedback.className = 'error-message';
            }
            return;
        }
        if (file.size > 6 * 1024 * 1024) {
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = 'Please choose an image smaller than 6 MB.';
                profilePictureFeedback.className = 'error-message';
            }
            return;
        }

        if (profilePictureFeedback) {
            profilePictureFeedback.textContent = 'Uploading...';
            profilePictureFeedback.className = '';
        }

        try {
            const formData = new FormData();
            formData.append('profile_picture', file);
            const response = await authedFetch('/users/' + encodeURIComponent(window.currentUserId) + '/profile-picture', {
                method: 'POST',
                body: formData
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || !data.success) {
                throw new Error((data && (data.error || data.message)) || 'Upload failed');
            }

            if (profilePicPreview) {
                profilePicPreview.src = getProfileImageUrl(data.profile_picture_url || data.profile_picture);
            }
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = 'Profile picture uploaded successfully.';
                profilePictureFeedback.className = 'success-message';
            }
            if (Array.isArray(data.history)) {
                renderProfilePictureHistory(data.history);
            } else {
                await loadProfilePictureHistory();
            }
        } catch (err) {
            if (profilePictureFeedback) {
                profilePictureFeedback.textContent = err.message || 'Unable to upload profile picture.';
                profilePictureFeedback.className = 'error-message';
            }
            console.warn(err);
        }
    }

    if (uploadProfilePictureBtn && profilePictureInput) {
        console.log('profile picture input and button found');
        uploadProfilePictureBtn.addEventListener('click', function () {
            console.log('profile picture change button clicked');
            profilePictureInput.click();
        });
        profilePictureInput.addEventListener('change', function () {
            console.log('profile picture input changed', profilePictureInput.files);
            if (profilePictureInput.files && profilePictureInput.files[0]) {
                const file = profilePictureInput.files[0];
                console.log('Selected profile picture file:', file);
                handleProfilePictureUpload(file);
            }
        });
    } else {
        console.warn('profile picture upload controls not found', { uploadProfilePictureBtn, profilePictureInput });
    }

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
        loadProfilePictureHistory();
        setupProfilePictureHistoryActions();
        if (profilePicPreview && profilePicPreview.dataset && profilePicPreview.dataset.blobSrc) {
            console.log('Restoring profile picture from data-blob-src on page load', profilePicPreview.dataset.blobSrc);
            profilePicPreview.src = profilePicPreview.dataset.blobSrc;
        }
        restoreProfilePictureFromApi();
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


const editAddressBtn = document.getElementById('edit-address-btn');
const addressDisplayFields = document.getElementById('address-display-fields');
const addressEditModal = document.getElementById('edit-address-modal');
const addressEditForm = document.getElementById('edit-address-form');
const cancelEditAddressBtn = document.getElementById('cancel-edit-address');
const closeEditAddressModalBtn = document.getElementById('close-edit-address-modal');
const addressEditFeedback = document.getElementById('address-edit-feedback');

function openEditAddressModal() {
    if (addressEditModal) {
        addressEditModal.classList.add('is-visible');
        addressEditModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        document.getElementById('edit-user_road_number').value = document.getElementById('user_road_number-value').textContent.trim();
        document.getElementById('edit-user_road').value = document.getElementById('user_road-value').textContent.trim();
        document.getElementById('edit-user_zip_code').value = document.getElementById('user_zip_code-value').textContent.trim();
        document.getElementById('edit-user_city').value = document.getElementById('user_city-value').textContent.trim();
        addressEditFeedback.style.display = 'none';
    }
}
function closeEditAddressModal() {
    if (addressEditModal) {
        addressEditModal.classList.remove('is-visible');
        addressEditModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        addressEditFeedback.style.display = 'none';
    }
}
if (editAddressBtn) {
    editAddressBtn.addEventListener('click', openEditAddressModal);
}
if (cancelEditAddressBtn) {
    cancelEditAddressBtn.addEventListener('click', closeEditAddressModal);
}
if (closeEditAddressModalBtn) {
    closeEditAddressModalBtn.addEventListener('click', closeEditAddressModal);
}
if (addressEditModal) {
    addressEditModal.addEventListener('click', function(event) {
        if (event.target === addressEditModal) closeEditAddressModal();
    });
}
if (addressEditForm) {
    addressEditForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        addressEditFeedback.style.display = 'none';
        const user_road_number = document.getElementById('edit-user_road_number').value.trim();
        const user_road = document.getElementById('edit-user_road').value.trim();
        const user_zip_code = document.getElementById('edit-user_zip_code').value.trim();
        const user_city = document.getElementById('edit-user_city').value.trim();

        const payload = {
            user_road_number,
            user_road,
            user_zip_code,
            user_city
        };
        try {
            const res = await fetch('update-profile-api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error || 'Update failed');

            document.getElementById('user_road_number-value').textContent = user_road_number;
            document.getElementById('user_road-value').textContent = user_road;
            document.getElementById('user_zip_code-value').textContent = user_zip_code;
            document.getElementById('user_city-value').textContent = user_city;
            closeEditAddressModal();
            updateCombinedAddress();
        } catch (err) {
            addressEditFeedback.textContent = err.message;
            addressEditFeedback.style.display = '';
        }
    });
}

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

var addressMap, addressMarker;
function openAddressModal(addr) {
    if (!addr || addr === '—') return;
    var modal = document.getElementById('address-modal');
    if (!modal) return;
    modal.classList.add('is-visible');
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden', 'false');

    setTimeout(function() {
        if (!addressMap) {
            addressMap = L.map('address-map').setView([0,0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(addressMap);
        }

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

document.addEventListener('click', function(e) {
    if (e.target.closest('.address-clickable')) {
        e.preventDefault();
        var addrEl = document.getElementById('address-value');
        if (addrEl) openAddressModal(addrEl.textContent);
    }
});

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

function enableInlineEditing(btn) {
    const key = btn.getAttribute('data-edit');
    if (!key) return;
    const valueEl = document.getElementById(key + '-value');
    if (!valueEl) return;
    const orig = valueEl.textContent.trim();
    const row = btn.closest('.profile-field-row');
    if (row) row.classList.add('editing');

    btn.disabled = true;
    btn.style.visibility = 'hidden';

    valueEl.innerHTML = '';
    const input = document.createElement('input');
    input.type = 'text';
    input.value = orig;
    input.className = 'profile-edit-input';
    valueEl.appendChild(input);

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'btn-edit-save';
    saveBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
    valueEl.appendChild(saveBtn);

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'btn-edit-cancel';
    cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    valueEl.appendChild(cancelBtn);

    const errorEl = document.createElement('div');
    errorEl.className = 'edit-error-msg';
    errorEl.style.display = 'none';
    valueEl.appendChild(errorEl);

    input.focus();
    input.select();

    async function submitEdit() {
        errorEl.style.display = 'none';
        const newVal = input.value.trim();
        if (newVal === orig) {
            finish();
            return;
        }
        saveBtn.disabled = true;
        cancelBtn.disabled = true;
        try {
            const payload = { field: key, value: newVal };
            const resp = await fetch('update-profile-api', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json().catch(() => null);
            if (resp.ok && !(data && data.error)) {
                valueEl.textContent = newVal;
            } else {
                const msg = (data && (data.error || (data.errors && data.errors.join(' ')))) || 'Update failed';
                errorEl.textContent = msg;
                errorEl.style.display = '';
                return;
            }
        } catch (err) {
            errorEl.textContent = 'Network error.';
            errorEl.style.display = '';
            return;
        } finally {
            saveBtn.disabled = false;
            cancelBtn.disabled = false;
        }
        finish();
    }

    function finish() {
        if (row) row.classList.remove('editing');
        btn.disabled = false;
        btn.style.visibility = '';
    }

    saveBtn.addEventListener('click', submitEdit);
    cancelBtn.addEventListener('click', function() {

        valueEl.textContent = orig;
        finish();
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            submitEdit();
        } else if (e.key === 'Escape') {
            cancelBtn.click();
        }
    });
}

const newsletterToggle = document.getElementById('newsletter-toggle');
const newsletterStatusText = document.getElementById('newsletter-status-text');
const newsletterFeedback = document.getElementById('newsletter-feedback');

async function toggleNewsletterSubscription(enabled) {
    if (!newsletterToggle) return;
    if (newsletterFeedback) {
        newsletterFeedback.textContent = '';
        newsletterFeedback.className = '';
    }
    const previousState = !enabled;
    try {
        const response = await fetch('update-profile-api', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ field: 'newsletter_subscribed', value: enabled ? '1' : '0' })
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || (data && data.error)) {
            throw new Error((data && data.error) || 'Unable to update newsletter settings.');
        }
        
    } catch (err) {
        if (newsletterFeedback) {
            newsletterFeedback.textContent = err.message || 'Unable to update newsletter subscription.';
            newsletterFeedback.className = 'error-message';
        }
        if (newsletterToggle) {
            newsletterToggle.checked = previousState;
        }
    }
}

if (newsletterToggle) {
    newsletterToggle.addEventListener('change', function() {
        toggleNewsletterSubscription(newsletterToggle.checked);
    });
}

document.addEventListener('click', function(e) {
    const editBtn = e.target.closest('.btn-edit-inline');
    if (editBtn) {
        e.preventDefault();
        enableInlineEditing(editBtn);
    }
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

    updateCombinedAddress();

    var faRow = document.querySelector('.full-address-row');
    if (faRow) {
        var addr = document.getElementById('address-value');
        if (addr && addr.textContent.trim() !== '' && addr.textContent.trim() !== '—') {
            faRow.style.display = '';
        } else {
            faRow.style.display = 'none';
        }
    }

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
