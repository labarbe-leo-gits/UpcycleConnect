(function () {
    'use strict';

    window.OneSignal = window.OneSignal || [];

    function isLocalhost() {
        return window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    }

    function log() {
        if (window.console && window.console.log) {
            window.console.log('[OneSignal]', ...arguments);
        }
    }

    function getAuthToken() {
        return window.API_TOKEN || localStorage.getItem('jwt_token') || localStorage.getItem('token') || '';
    }

    async function sendPlayerIdToServer(playerId) {
        if (!playerId || !window.currentUserId || !window.API_BASE) {
            return;
        }

        const token = getAuthToken();
        if (!token) {
            log('No auth token available for OneSignal registration');
            return;
        }

        const url = window.API_BASE.replace(/\/$/, '') + '/users/' + encodeURIComponent(window.currentUserId);
        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ one_signal_player_id: playerId })
            });
            if (!response.ok) {
                const body = await response.text();
                log('OneSignal player ID save failed:', response.status, body);
                return;
            }
            localStorage.setItem('one_signal_player_id', playerId);
            log('OneSignal player ID saved to server:', playerId);
        } catch (error) {
            log('OneSignal player ID save exception:', error);
        }
    }

    function registerPlayerId() {
        window.OneSignal.getUserId(function (playerId) {
            if (!playerId) {
                return;
            }
            const stored = localStorage.getItem('one_signal_player_id');
            if (stored === playerId) {
                log('OneSignal player ID already registered');
                return;
            }
            sendPlayerIdToServer(playerId);
        });
    }

    function initOneSignal() {
        if (!window.ONE_SIGNAL_APP_ID || !window.currentUserId || !window.API_BASE) {
            log('OneSignal init skipped: missing config', {
                appId: window.ONE_SIGNAL_APP_ID,
                currentUserId: window.currentUserId,
                apiBase: window.API_BASE
            });
            return;
        }

        window.OneSignal.push(function () {
            try {
                window.OneSignal.init({
                    appId: window.ONE_SIGNAL_APP_ID,
                    allowLocalhostAsSecureOrigin: isLocalhost(),
                    notifyButton: { enable: false }
                });

                window.OneSignal.isPushNotificationsEnabled(function (isEnabled) {
                    log('OneSignal push enabled:', isEnabled);
                    if (isEnabled) {
                        registerPlayerId();
                        return;
                    }
                    if (!localStorage.getItem('one_signal_prompt_shown')) {
                        localStorage.setItem('one_signal_prompt_shown', '1');
                        try {
                            window.OneSignal.showNativePrompt();
                        } catch (err) {
                            log('Error showing OneSignal prompt:', err);
                        }
                    }
                });

                window.OneSignal.on('subscriptionChange', function (isSubscribed) {
                    log('OneSignal subscription changed:', isSubscribed);
                    if (isSubscribed) {
                        registerPlayerId();
                    }
                });
            } catch (err) {
                log('OneSignal init failed:', err);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOneSignal);
    } else {
        initOneSignal();
    }
})();
