
(function() {
    var page = document.getElementById('order-page');
    if (!page) return;

    var orderToken = page.getAttribute('data-order-token') || '';
    var productUuid = page.getAttribute('data-product-uuid') || '';
    var stripeKey = page.getAttribute('data-stripe-key') || '';
    var userId = page.getAttribute('data-user-id') || '';

    function getSelectedScheduleId() {
        var scheduleId = '';
        var scheduleSelect = document.getElementById('schedule-select');
        if (scheduleSelect && scheduleSelect.value) {
            scheduleId = scheduleSelect.value;
        } else {
            var selectedCard = document.querySelector('#schedule-cards .schedule-card.selected');
            scheduleId = selectedCard ? selectedCard.dataset.scheduleId || '' : '';
        }
        if (!scheduleId) {
            scheduleId = (document.getElementById('event_availability_id_paid') || {}).value || (document.getElementById('event_availability_id_free') || {}).value || '';
        }
        return scheduleId;
    }

    function getSelectedScheduleHour() {
        var scheduleSelect = document.getElementById('schedule-select');
        if (scheduleSelect && scheduleSelect.selectedIndex >= 0) {
            var option = scheduleSelect.options[scheduleSelect.selectedIndex];
            if (option && option.dataset && option.dataset.scheduleHour) {
                return option.dataset.scheduleHour;
            }
        }

        var selectedCard = document.querySelector('#schedule-cards .schedule-card.selected');
        if (selectedCard && selectedCard.dataset && selectedCard.dataset.scheduleHour) {
            return selectedCard.dataset.scheduleHour;
        }
        return '';
    }

    var isScheduleConflict = false;
    var scheduleConflictChecked = false;
    var scheduleConflictConfirmed = false;

    function closeConflictModal() {
        var modal = document.getElementById('conflict-modal');
        if (modal) {
            modal.classList.remove('open');
            setTimeout(function() {
                if (modal && !modal.classList.contains('open')) {
                    modal.style.display = 'none';
                }
            }, 250);
        }
    }

    function openConflictModal() {
        var modal = document.getElementById('conflict-modal');
        if (modal) {
            modal.classList.remove('open');
            modal.style.display = 'block';
            window.requestAnimationFrame(function() {
                modal.classList.add('open');
            });
        }
    }

    function formatDateTime(value) {
        if (!value) return '';
        var parts = value.split(' ');
        var datePart = (parts[0] || '').trim();
        var timePart = (parts[1] || '').slice(0,5);
        var dateChunks = datePart.split('-');
        if (dateChunks.length !== 3) {
            return value;
        }
        return dateChunks[2] + '/' + dateChunks[1] + '/' + dateChunks[0] + (timePart ? ' ' + timePart : '');
    }

    async function checkScheduleConflict() {
        var conflictEl = document.getElementById('schedule-conflict-message');
        var conflictCard = document.getElementById('schedule-conflict-card');
        if (!conflictEl || !conflictCard) return;

        var serviceDate = document.getElementById('order-page').getAttribute('data-service-date') || '';
        var selectedHour = getSelectedScheduleHour();
        if (!serviceDate || !selectedHour) {
            conflictEl.style.display = 'none';
            conflictCard.style.display = 'none';
            isScheduleConflict = false;
            scheduleConflictChecked = true;
            scheduleConflictConfirmed = false;
            return;
        }

        conflictEl.style.display = 'none';
        conflictCard.style.display = 'block';
        conflictCard.innerHTML = '<div class="schedule-conflict-skeleton"></div>';

        try {
            var startParam = serviceDate + ' 00:00:00';
            var endParam = serviceDate + ' 23:59:59';
            var url = 'planning-api?start=' + encodeURIComponent(startParam) + '&end=' + encodeURIComponent(endParam);
            var response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Unable to fetch planning');
            var data = await response.json();
            var entries = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
            var conflict = false;
            var conflictEntry = null;
            var selectedHourInt = parseInt(selectedHour.split(':')[0], 10);

            entries.forEach(function(entry) {
                if (!entry || entry.date !== serviceDate) return;

                var plannedStart = entry.start_time || '';
                var plannedEnd = entry.end_time || '';
                if (!plannedStart) return;

                var plannedStartTime = plannedStart.split(' ')[1] || plannedStart;
                var plannedEndTime = plannedEnd.split(' ')[1] || plannedEnd;

                var plannedStartHour = parseInt(plannedStartTime.split(':')[0], 10);
                var plannedEndHour = plannedEndTime ? parseInt(plannedEndTime.split(':')[0], 10) : (isNaN(plannedStartHour) ? NaN : plannedStartHour + 1);

                if (isNaN(plannedStartHour) || isNaN(plannedEndHour)) return;

                if (selectedHourInt >= plannedStartHour && selectedHourInt < plannedEndHour) {
                    conflict = true;
                    if (!conflictEntry) {
                        conflictEntry = entry;
                    }
                }
            });

            if (conflict) {
                isScheduleConflict = true;
                scheduleConflictChecked = true;
                scheduleConflictConfirmed = false;

                conflictEl.style.display = 'block';
                conflictEl.innerHTML = '<div style="border:1px solid #f87171;border-radius:8px;padding:10px;background:#fff7f7;color:#b91c1c;">Conflict: you already have a planning entry at this time on ' + serviceDate + '. The booking will still be added to your agenda.</div>';

                if (conflictEntry) {
                    conflictCard.style.display = 'block';
                    var startFormatted = formatDateTime((conflictEntry.start_time && conflictEntry.start_time.indexOf(' ') > -1) ? conflictEntry.start_time : (conflictEntry.date + ' ' + (conflictEntry.start_time || '')) );
                    var endFormatted = formatDateTime((conflictEntry.end_time && conflictEntry.end_time.indexOf(' ') > -1) ? conflictEntry.end_time : (conflictEntry.date + ' ' + (conflictEntry.end_time || '')) );
                    conflictCard.innerHTML = '<div style="border:1px solid #d1d5db;border-radius:8px;padding:10px;background:#ffffff;box-shadow:0 2px 8px rgba(0,0,0,.1);">'
                        + '<strong>' + (conflictEntry.title || 'Untitled') + '</strong><br>'
                        + '<span>' + (startFormatted || '') + ' - ' + (endFormatted || '') + '</span><br>'
                        + '<span>' + (conflictEntry.description || 'No description available.') + '</span>'
                        + '</div>';
                } else {
                    conflictCard.style.display = 'none';
                }
            } else {
                isScheduleConflict = false;
                scheduleConflictChecked = true;
                scheduleConflictConfirmed = true;

                conflictEl.style.display = 'none';
                conflictCard.style.display = 'block';
                conflictCard.innerHTML = '<div style="border:1px solid #10b981;border-radius:8px;padding:10px;background:#ecfdf5;color:#065f46;">No conflict detected. This booking will be added to your agenda.</div>';
            }
        } catch (e) {
            console.warn('checkScheduleConflict error', e);
            isScheduleConflict = false;
            scheduleConflictChecked = true;
            scheduleConflictConfirmed = false;

            conflictEl.style.display = 'block';
            conflictEl.innerHTML = '<div style="border:1px solid #f87171;border-radius:8px;padding:10px;background:#fff7f7;color:#b91c1c;">Unable to load planning data. Please try again.</div>';
            conflictCard.style.display = 'none';
            conflictCard.innerHTML = '';
        }
    }


    window.addEventListener('load', function() {
        setTimeout(function() {
            var skeleton = document.querySelector('.skeleton-checkout-container');
            var content = document.querySelector('.actual-content');
            if (skeleton) {
                skeleton.style.display = 'none';
            }
            if (content) {
                content.style.display = 'block';
            }
        }, 500);
    });

    function fetchUserBalance() {
        var balanceSpan = document.getElementById('user-balance');
        if (!balanceSpan) return;
        fetch('users-api?id=' + encodeURIComponent(userId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && typeof data.balance !== 'undefined') {
                balanceSpan.textContent = '€ ' + Number(data.balance).toFixed(2);
            } else {
                balanceSpan.textContent = 'N/A';
            }
        })
        .catch(function() {
            balanceSpan.textContent = 'N/A';
        });
    }
    fetchUserBalance();

    var freeForm = document.getElementById('order-form');
    if (freeForm) {
        freeForm.addEventListener('submit', function() {
            var submitButton = document.getElementById('submit-free-order');
            var buttonText = document.getElementById('free-button-text');
            var spinner = document.getElementById('free-spinner');
            if (submitButton) submitButton.disabled = true;
            if (buttonText) buttonText.style.display = 'none';
            if (spinner) spinner.style.display = 'inline-block';
        });
    }

    var scheduleCardsContainer = document.getElementById('schedule-cards');
    var hiddenInputFree = document.getElementById('event_availability_id_free');
    var hiddenInputPaid = document.getElementById('event_availability_id_paid');

    if (scheduleCardsContainer) {
        var cards = Array.from(scheduleCardsContainer.querySelectorAll('.schedule-card'));

        var setSelectedSchedule = function(scheduleId) {
            scheduleConflictChecked = false;
            scheduleConflictConfirmed = false;
            closeConflictModal();
            cards.forEach(function(card) {
                var isSelected = card.dataset.scheduleId === scheduleId;
                card.classList.toggle('selected', isSelected);
                if (isSelected) {
                    card.style.backgroundColor = '#10b981';
                    card.style.color = '#ffffff';
                    card.style.borderColor = '#10b981';
                } else {
                    card.style.backgroundColor = '#ffffff';
                    card.style.color = '#111827';
                    card.style.borderColor = '#d1d5db';
                }
            });
            if (hiddenInputFree) hiddenInputFree.value = scheduleId;
            if (hiddenInputPaid) hiddenInputPaid.value = scheduleId;
            checkScheduleConflict();
        };

        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                setSelectedSchedule(card.dataset.scheduleId);
            });
        });

        if (cards.length > 0) {
            setSelectedSchedule(cards[0].dataset.scheduleId);
        }

        var scheduleSelect = document.getElementById('schedule-select');
        if (scheduleSelect) {
            scheduleSelect.addEventListener('change', function() {
                setSelectedSchedule(scheduleSelect.value);
            });
        }
    }

    var conflictOkButton = document.getElementById('conflict-ok');
    var conflictCloseButton = document.getElementById('conflict-close');
    var conflictModal = document.getElementById('conflict-modal');
    if (conflictOkButton) {
        conflictOkButton.addEventListener('click', function() {
            scheduleConflictConfirmed = true;
            closeConflictModal();
            paymentForm.dispatchEvent(new Event('submit', { cancelable: true }));
        });
    }
    if (conflictCloseButton) {
        conflictCloseButton.addEventListener('click', function() {
            closeConflictModal();
        });
    }
    if (conflictModal) {
        window.addEventListener('click', function(event) {
            if (event.target === conflictModal) {
                closeConflictModal();
            }
        });
    }

    var paymentForm = document.getElementById('payment-form');
    if (!paymentForm) return;

    var tabStripe = document.getElementById('tab-stripe');
    var tabBalance = document.getElementById('tab-balance');
    var paymentMethodInput = document.getElementById('payment_method_input');
    var stripeFields = document.getElementById('stripe-fields');
    var stripeNotice = document.getElementById('stripe-notice');
    var balanceNotice = document.getElementById('balance-notice');

    function setActiveTab(method) {
        var cardholderName = document.getElementById('cardholder-name');
        var billingEmail = document.getElementById('billing-email');
        if (method === 'stripe') {
            tabStripe.classList.add('active');
            tabBalance.classList.remove('active');
            paymentMethodInput.value = 'stripe';
            if (stripeFields) stripeFields.style.display = '';
            if (stripeNotice) stripeNotice.style.display = '';
            if (balanceNotice) balanceNotice.style.display = 'none';
            if (cardholderName) cardholderName.required = true;
            if (billingEmail) billingEmail.required = true;
        } else {
            tabStripe.classList.remove('active');
            tabBalance.classList.add('active');
            paymentMethodInput.value = 'balance';
            if (stripeFields) stripeFields.style.display = 'none';
            if (stripeNotice) stripeNotice.style.display = 'none';
            if (balanceNotice) balanceNotice.style.display = '';
            if (cardholderName) cardholderName.required = false;
            if (billingEmail) billingEmail.required = false;
        }
    }

    function showConflictModal(){
        var modal = document.getElementById('schedule-conflict-modal');
        if (modal) {
            modal.style.display = 'block';
            var closeButton = document.getElementById('conflict-close');
            if (closeButton) {
                closeButton.onclick = function() {
                    modal.style.display = 'none';
                };
            }

            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }

            };
        }
    }

    function hideConflictModal() {
        var modal = document.getElementById('schedule-conflict-modal');

        if (modal) {
            modal.style.display = 'none';
        }

    }

    if (tabStripe && tabBalance) {
        tabStripe.addEventListener('click', function() { setActiveTab('stripe'); });
        tabBalance.addEventListener('click', function() { setActiveTab('balance'); });

        tabStripe.addEventListener('keydown', function(e) { if (e.key === 'ArrowRight') { tabBalance.focus(); setActiveTab('balance'); } });
        tabBalance.addEventListener('keydown', function(e) { if (e.key === 'ArrowLeft') { tabStripe.focus(); setActiveTab('stripe'); } });
        setActiveTab('stripe');
    }

    var stripe = null, elements = null, cardElement = null;
    if (window.Stripe && stripeKey) {
        stripe = window.Stripe(stripeKey);
        elements = stripe.elements();
        cardElement = elements.create('card', {
            hidePostalCode: true,
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#dc2626',
                    iconColor: '#dc2626'
                }
            }
        });
        cardElement.mount('#card-element');
        cardElement.on('change', function(event) {
            var displayError = document.getElementById('card-errors');
            if (!displayError) return;
            if (event.error) {
                displayError.textContent = event.error.message;
                displayError.style.display = 'block';
            } else {
                displayError.textContent = '';
                displayError.style.display = 'none';
            }
        });
    }

    paymentForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (!scheduleConflictChecked) {
            await checkScheduleConflict();
        }

        if (isScheduleConflict && !scheduleConflictConfirmed) {
            openConflictModal();
            return;
        }

        var selected = document.getElementById('payment_method_input').value;
        var submitButton = document.getElementById('submit-payment');
        var buttonText = document.getElementById('button-text');
        var spinner = document.getElementById('spinner');
        if (submitButton) submitButton.disabled = true;
        if (buttonText) buttonText.style.display = 'none';
        if (spinner) spinner.style.display = 'inline-block';

        if (selected === 'balance') {

            var scheduleId = getSelectedScheduleId();

            try {
                var resp = await fetch('pay-with-balance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        product_uuid: productUuid,
                        order_token: orderToken,
                        event_availability_id: scheduleId
                    })
                });
                var respText = await resp.text();
                var respData = null;
                try { respData = JSON.parse(respText); } catch { respData = null; }
                if (resp.ok && respData && respData.status === 'succeeded') {
                    window.location.href = 'order-success?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&payment_method=balance' + (scheduleId ? '&event_availability_id=' + encodeURIComponent(scheduleId) : '');
                    return;
                }
                var reason = (respData && respData.error) ? respData.error : (respText || 'balance_payment_failed');
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(reason);
            } catch (err) {
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(err.message || 'balance_payment_failed');
            }
            return;
        }

        if (!stripe || !cardElement) {
            window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent('stripe_not_available');
            return;
        }
        try {
            var response = await fetch('create-payment-intent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    product_uuid: productUuid
                })
            });
            var responseText = await response.text();
            var data = null;
            try { data = JSON.parse(responseText); } catch { data = null; }
            if (!data) {
                var reason = responseText ? responseText.slice(0, 200) : 'invalid_response';
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(reason);
                return;
            }
            if (!response.ok) {
                var errorReason = data && data.error ? data.error : (responseText || 'payment_intent_failed');
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(errorReason);
                return;
            }
            if (data && data.error) {
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(data.error);
                return;
            }
            if (!data || !data.clientSecret) {
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent('missing_client_secret');
                return;
            }
            var result = await stripe.confirmCardPayment(data.clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: document.getElementById('cardholder-name').value,
                        email: document.getElementById('billing-email').value
                    }
                }
            });
            if (result.error) {
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(result.error.message || 'payment_failed');
                return;
            }
            if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                var scheduleId = getSelectedScheduleId();
                alert('Card payment complete; selected scheduleId=' + (scheduleId || '<none>')); // debug
                if (!scheduleId) {
                    alert('Please select a schedule before completing your card payment.');
                    if (submitButton) submitButton.disabled = false;
                    if (buttonText) buttonText.style.display = '';
                    if (spinner) spinner.style.display = 'none';
                    return;
                }

                var verifyResponse = await fetch('verify-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        payment_intent: result.paymentIntent.id,
                        product_uuid: productUuid
                    })
                });
                var verifyText = await verifyResponse.text();
                var verifyData = null;
                try { verifyData = JSON.parse(verifyText); } catch { verifyData = null; }
                if (verifyResponse.ok && verifyData && verifyData.status === 'succeeded') {
                    var scheduleId = getSelectedScheduleId();
                    console.log('stripe redirect scheduling, selected scheduleId:', scheduleId);
                    if (!scheduleId) {
                        console.warn('Stripe flow: scheduleId missing at redirect');
                    }
                    window.location.href = 'order-success?payment_intent=' + encodeURIComponent(result.paymentIntent.id) + '&product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + (scheduleId ? '&event_availability_id=' + encodeURIComponent(scheduleId) : '');
                    return;
                }
                var verifyReason = verifyData && verifyData.error ? verifyData.error : (verifyText || 'payment_verification_failed');
                window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(verifyReason);
                return;
            }
            window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(result.paymentIntent ? result.paymentIntent.status : 'payment_failed');
        } catch (error) {
            window.location.href = 'order-cancel?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&reason=' + encodeURIComponent(error.message || 'payment_failed');
        }
    });
})();
