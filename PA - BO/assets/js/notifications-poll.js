(function() {
    'use strict';

    var badge = document.getElementById('notifications-count');

    if (!badge) {
        return;
    }


    var headerEl = document.querySelector('header[data-notif-poll]');
    var endpoint = headerEl ? headerEl.getAttribute('data-notif-poll') : '../customers/notifications-poll';

    var lastUnreadCount = null;
    var audioContext = null;

    function initAudio() {
        if (audioContext) {
            return;
        }
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return;
        }
        audioContext = new AudioCtx();
    }

    function playNotificationSound() {
        initAudio();
        if (!audioContext) {
            return;
        }

        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(function() {});
        }

        var gainNode = audioContext.createGain();
        gainNode.gain.value = 0.18;
        gainNode.connect(audioContext.destination);

        var now = audioContext.currentTime;
        var toneA = audioContext.createOscillator();
        toneA.type = 'triangle';
        toneA.frequency.setValueAtTime(880, now);
        toneA.connect(gainNode);

        var toneB = audioContext.createOscillator();
        toneB.type = 'triangle';
        toneB.frequency.setValueAtTime(1174.66, now + 0.12);
        toneB.connect(gainNode);

        toneA.start(now);
        toneA.stop(now + 0.14);
        toneB.start(now + 0.12);
        toneB.stop(now + 0.28);
    }

    function  updateBadge(count) {
        if (lastUnreadCount !== null && count > lastUnreadCount) {
            playNotificationSound();
        }
        lastUnreadCount = count;
        if (count > 0) {
            badge.textContent = String(count);
            badge.hidden = false;
        } else {
            badge.textContent = '0';
            badge.hidden = true;
        }
    }

    function fetchNotifications() {
        fetch(endpoint, {
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function(response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            })
            .then(function(data) {
                if (!Array.isArray(data)) {
                    return;
                }
                var unreadCount = data.filter(function(item) { return !item.read; }).length;
                updateBadge(unreadCount);
            })
            .catch(function() {
                
            });
    }

    document.addEventListener('click', initAudio, { once: true });
    fetchNotifications();
    setInterval(fetchNotifications, 5000);
})();
