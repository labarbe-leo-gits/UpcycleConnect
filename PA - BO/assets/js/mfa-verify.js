document.addEventListener('DOMContentLoaded', function () {
    var otpInput = document.getElementById('otp_code');
    var form = document.getElementById('mfa-form');

    if (!otpInput || !form) {
        return;
    }

    otpInput.addEventListener('input', function () {
        var val = this.value.replace(/\D/g, '').slice(0, 6);
        this.value = val;

        if (val.length === 6) {
            form.submit();
        }
    });
});
