(function() {
    var page = document.querySelector('.promotion-checkout-page');
    if (!page) return;

    var promotion = window.PROMOTION_DATA || {};
    var stripeKey = promotion.stripeKey || '';

    var stripe = null;
    var elements = null;
    var cardElement = null;

    function initStripe() {
        if (!window.Stripe || !stripeKey) return;
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

    function createPaymentIntent() {
        return fetch('create-promotion-payment-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                offer_id: promotion.offerId,
                budget: promotion.budget,
                duration_days: promotion.duration,
                name: promotion.name,
                description: promotion.description
            })
        }).then(function(res) { return res.text(); })
        .then(function(text) {
            try { return JSON.parse(text); } catch (e) { return null; }
        });
    }

    function verifyPayment(paymentIntentId) {
        return fetch('verify-promotion-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                payment_intent: paymentIntentId,
                offer_id: promotion.offerId
            })
        }).then(function(res) { return res.text(); })
        .then(function(text) {
            try { return JSON.parse(text); } catch (e) { return null; }
        });
    }

    function showResult(success, message) {
        var result = document.getElementById('promotion-result');
        var form = document.getElementById('promotion-payment-form');
        if (!result || !form) return;
        form.style.display = 'none';
        result.classList.remove('hidden');
        if (success) {
            result.innerHTML = `
                <div class="success-card">
                    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                    <h1>Promotion activated!</h1>
                    <p>${message || 'Your offer is now being promoted.'}</p>
                    <a href="offers" class="btn btn-primary"><i class="fas fa-list"></i> Back to offers</a>
                </div>`;
        } else {
            result.innerHTML = `
                <div class="error-card">
                    <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <h1>Payment failed</h1>
                    <p>${message || 'Unable to process the payment.'}</p>
                    <a href="offers" class="btn btn-outline">Back to offers</a>
                </div>`;
        }
    }

    function initForm() {
        var form = document.getElementById('promotion-payment-form');
        if (!form) return;

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            var submitButton = document.getElementById('submit-promotion-payment');
            var buttonText = document.getElementById('button-text');
            var spinner = document.getElementById('spinner');
            var didRedirect = false;

            if (submitButton) submitButton.disabled = true;
            if (buttonText) buttonText.style.display = 'none';
            if (spinner) spinner.style.display = 'inline-block';

            createPaymentIntent().then(function(data) {
                if (!data || !data.clientSecret) {
                    showResult(false, (data && data.error) ? data.error : 'Unable to create payment intent');
                    return;
                }

                return stripe.confirmCardPayment(data.clientSecret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: document.getElementById('cardholder-name').value,
                            email: document.getElementById('billing-email').value
                        }
                    }
                });
            }).then(function(result) {
                if (!result) return;
                if (result.error) {
                    showResult(false, result.error.message);
                    return;
                }
                if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    return verifyPayment(result.paymentIntent.id).then(function(verify) {
                        if (verify && verify.status === 'succeeded') {
                            didRedirect = true;
                            window.location.href = 'promote-success?status=success';
                        } else {
                            showResult(false, (verify && verify.error) ? verify.error : 'Payment confirmed, but verification failed.');
                        }
                    });
                }
                showResult(false, 'Payment not completed.');
            }).catch(function(err) {
                showResult(false, err && err.message ? err.message : 'Unable to create payment intent.');
            }).finally(function() {
                if (!didRedirect) {
                    if (submitButton) submitButton.disabled = false;
                    if (buttonText) buttonText.style.display = '';
                    if (spinner) spinner.style.display = 'none';
                }
            });
        });
    }

    initStripe();
    initForm();
})();
