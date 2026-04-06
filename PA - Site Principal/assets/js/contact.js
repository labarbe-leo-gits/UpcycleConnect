document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    const button = document.getElementById('contact-submit-button');
    const feedback = document.getElementById('contact-feedback');
    if (!form || !button || !feedback) {
        return;
    }

    const originalButtonText = button.innerHTML;

    function setFeedback(message, type) {
        feedback.innerHTML = '<div class="form-feedback ' + type + '">' + message + '</div>';
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        const name = form.name.value.trim();
        const email = form.email.value.trim();
        const message = form.message.value.trim();

        if (name === '' || email === '' || message === '') {
            setFeedback('Please complete all fields before sending your message.', 'error');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setFeedback('Please enter a valid email address.', 'error');
            return;
        }

        button.disabled = true;
        button.classList.add('loading');
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>Sending…';

        try {
            const response = await fetch('../common/contacts-api', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ name, email, message })
            });

            const data = await response.json();

            if (!response.ok) {
                throw data;
            }

            setFeedback(data.message || 'Your message has been sent successfully. Thank you for contacting us!', 'success');
            form.reset();
        } catch (error) {
            const errorMessage = (error && error.error) ? error.error : 'Unable to send your message right now. Please try again later.';
            setFeedback(errorMessage, 'error');
        } finally {
            button.disabled = false;
            button.classList.remove('loading');
            button.removeAttribute('aria-busy');
            button.innerHTML = originalButtonText;
        }
    });
});