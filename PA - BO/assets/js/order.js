(function() {
    var page = document.getElementById('order-page');
    if (!page) return;

    var orderToken = page.getAttribute('data-order-token') || '';
    var productUuid = page.getAttribute('data-product-uuid') || '';
    var stripeKey = page.getAttribute('data-stripe-key') || '';

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
    if (!paymentForm || !window.Stripe || !stripeKey) return;

    var stripe = window.Stripe(stripeKey);
    var elements = stripe.elements();

    var cardElement = elements.create('card', {
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

    paymentForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        var submitButton = document.getElementById('submit-payment');
        var buttonText = document.getElementById('button-text');
        var spinner = document.getElementById('spinner');

        if (submitButton) submitButton.disabled = true;
        if (buttonText) buttonText.style.display = 'none';
        if (spinner) spinner.style.display = 'inline-block';

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

            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                data = null;
            }

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

                try {
                    verifyData = JSON.parse(verifyText);
                } catch (verifyParseError) {
                    verifyData = null;
                }

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
