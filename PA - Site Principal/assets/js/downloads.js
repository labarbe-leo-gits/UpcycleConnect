(function () {
    const skeleton = document.getElementById('dl-skeleton');
    const content  = document.getElementById('dl-content');

    setTimeout(function () {
        skeleton.style.transition = 'opacity .4s ease, transform .4s ease';
        skeleton.style.opacity    = '0';
        skeleton.style.transform  = 'translateY(-8px)';

        setTimeout(function () {
            skeleton.style.display = 'none';
            content.classList.remove('hidden');
            content.style.opacity    = '0';
            content.style.transform  = 'translateY(10px)';
            content.style.transition = 'opacity .45s ease, transform .45s ease';

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    content.style.opacity   = '1';
                    content.style.transform = 'translateY(0)';
                });
            });
        }, 420);
    }, 1500);

    const verifyBtn   = document.getElementById('captcha-verify-btn');
    const answerInput = document.getElementById('captcha-answer');
    const captchaMsg  = document.getElementById('captcha-msg');
    const dlBtn       = document.getElementById('download-btn');
    const captchaCard = document.getElementById('dl-captcha-card');

    function unlockDownload() {
        captchaCard.classList.add('solved');
        dlBtn.href = '../../assets/app/upcycleConnect.apk';
        dlBtn.setAttribute('download', 'UpcycleConnect.apk');
        dlBtn.classList.remove('locked');
        dlBtn.removeAttribute('aria-disabled');
        dlBtn.removeAttribute('tabindex');
    }

    function playSadTrombone() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();

            const notes = [261.63, 233.08, 207.65, 174.61];
            const noteDuration = 0.38;
            const lastDuration = 0.9;

            notes.forEach(function (freq, i) {
                const isLast  = i === notes.length - 1;
                const start   = i * noteDuration;
                const dur     = isLast ? lastDuration : noteDuration;
                const gainAmt = isLast ? 0.55 : 0.45;

                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(freq * 1.06, ctx.currentTime + start);
                osc.frequency.linearRampToValueAtTime(freq, ctx.currentTime + start + 0.06);

                gain.gain.setValueAtTime(0, ctx.currentTime + start);
                gain.gain.linearRampToValueAtTime(gainAmt, ctx.currentTime + start + 0.04);
                gain.gain.setValueAtTime(gainAmt, ctx.currentTime + start + dur - 0.12);
                gain.gain.linearRampToValueAtTime(0, ctx.currentTime + start + dur);

                const vibrato = ctx.createOscillator();
                const vibratoGain = ctx.createGain();
                vibrato.frequency.value = 5.5;
                vibratoGain.gain.value  = isLast ? 7 : 3;
                vibrato.connect(vibratoGain);
                vibratoGain.connect(osc.frequency);
                vibrato.start(ctx.currentTime + start);
                vibrato.stop(ctx.currentTime + start + dur);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + start);
                osc.stop(ctx.currentTime + start + dur);
            });
        } catch (e) { /* audio not supported, silent fail */ }
    }

    if (window._dlTroll) {
        const skipBtn = document.getElementById('captcha-skip-btn');

        skipBtn.addEventListener('click', function () {
            captchaMsg.textContent = '✓ Confirmed human. Only a robot could solve that. Welcome!';
            captchaMsg.className   = 'dl-captcha-msg success';
            unlockDownload();
        });

        verifyBtn.addEventListener('click', function () {
            const val = answerInput.value.trim();
            if (val === '') {
                captchaMsg.textContent = 'Please enter an answer, or admit defeat below.';
                captchaMsg.className   = 'dl-captcha-msg error';
                return;
            }

            verifyBtn.disabled  = true;
            verifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analysing…';

            setTimeout(function () {
                const num = parseFloat(val);
                if (!isNaN(num) && num === window._dlTrollAnswer) {

                    captchaCard.classList.add('troll-banned');
                    captchaMsg.innerHTML = 'Impressive… too impressive.<br><strong>Only a robot could solve that. Download denied.</strong>';
                    captchaMsg.className = 'dl-captcha-msg error';
                    verifyBtn.disabled   = true;
                    verifyBtn.innerHTML  = '<i class="fa-solid fa-ban"></i> Access denied';
                    answerInput.disabled = true;
                    skipBtn.disabled     = true;
                    playSadTrombone();
                } else {
                    captchaMsg.textContent = '✓ Wrong answer. Perfectly imperfect. You\'re human!';
                    captchaMsg.className   = 'dl-captcha-msg success';
                    unlockDownload();
                }
            }, 900);
        });

        answerInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') verifyBtn.click();
        });

    } else {
        verifyBtn.addEventListener('click', async function () {
            const val = answerInput.value.trim();

            if (val === '') {
                captchaMsg.textContent = 'Please enter your answer.';
                captchaMsg.className   = 'dl-captcha-msg error';
                return;
            }

            verifyBtn.disabled  = true;
            verifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking…';

            try {
                const fd = new FormData();
                fd.append('action', 'dl_verify');
                fd.append('answer', val);

                const res  = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    captchaMsg.textContent = '✓ Verification successful!';
                    captchaMsg.className   = 'dl-captcha-msg success';
                    unlockDownload();
                } else {
                    captchaMsg.textContent = '✗ Wrong answer - please try again.';
                    captchaMsg.className   = 'dl-captcha-msg error';
                    answerInput.value      = '';
                    answerInput.focus();
                    verifyBtn.disabled     = false;
                    verifyBtn.innerHTML    = '<i class="fa-solid fa-check"></i> Verify';
                }
            } catch (e) {
                captchaMsg.textContent = 'Network error - please refresh and try again.';
                captchaMsg.className   = 'dl-captcha-msg error';
                verifyBtn.disabled     = false;
                verifyBtn.innerHTML    = '<i class="fa-solid fa-check"></i> Verify';
            }
        });

        answerInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') verifyBtn.click();
        });
    }
}());