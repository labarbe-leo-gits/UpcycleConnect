
(function() {
    var page = document.getElementById('order-page');
    if (!page) return;

    var orderToken = page.getAttribute('data-order-token') || '';
    var productUuid = page.getAttribute('data-product-uuid') || '';
    var stripeKey = page.getAttribute('data-stripe-key') || '';
    var userId = page.getAttribute('data-user-id') || '';

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
        var selected = document.getElementById('payment_method_input').value;
        var submitButton = document.getElementById('submit-payment');
        var buttonText = document.getElementById('button-text');
        var spinner = document.getElementById('spinner');
        if (submitButton) submitButton.disabled = true;
        if (buttonText) buttonText.style.display = 'none';
        if (spinner) spinner.style.display = 'inline-block';

        if (selected === 'balance') {

            try {
                var resp = await fetch('pay-with-balance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        product_uuid: productUuid,
                        order_token: orderToken
                    })
                });
                var respText = await resp.text();
                var respData = null;
                try { respData = JSON.parse(respText); } catch { respData = null; }
                if (resp.ok && respData && respData.status === 'succeeded') {
                    window.location.href = 'order-success?product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken) + '&payment_method=balance';
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
                    window.location.href = 'order-success?payment_intent=' + encodeURIComponent(result.paymentIntent.id) + '&product_uuid=' + encodeURIComponent(productUuid) + '&order_token=' + encodeURIComponent(orderToken);
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
