(function () {
    'use strict';

    window.OneSignalDeferred = window.OneSignalDeferred || [];

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

    function registerPlayerId(OneSignal) {
        const playerId = OneSignal?.User?.PushSubscription?.id || '';
        if (!playerId) {
            return;
        }

        const stored = localStorage.getItem('one_signal_player_id');
        if (stored === playerId) {
            log('OneSignal player ID already registered');
            return;
        }

        sendPlayerIdToServer(playerId);
    }

    async function showPermissionPrompt(OneSignal) {
        if (!OneSignal?.Notifications?.isPushSupported || !OneSignal.Notifications.isPushSupported()) {
            log('OneSignal push is not supported in this browser');
            return;
        }

        if (OneSignal.Notifications.permission) {
            return;
        }

        if (localStorage.getItem('one_signal_prompt_shown')) {
            return;
        }

        localStorage.setItem('one_signal_prompt_shown', '1');

        try {
            if (OneSignal?.Slidedown?.promptPush) {
                await OneSignal.Slidedown.promptPush({ force: true });
                return;
            }

            if (OneSignal?.Notifications?.requestPermission) {
                await OneSignal.Notifications.requestPermission();
            }
        } catch (err) {
            log('Error showing OneSignal prompt:', err);
        }
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

        window.OneSignalDeferred.push(async function (OneSignal) {
            try {
                await OneSignal.init({
                    appId: window.ONE_SIGNAL_APP_ID,
                    allowLocalhostAsSecureOrigin: isLocalhost(),
                    autoResubscribe: true,
                    notifyButton: { enable: false }
                });

                try {
                    await OneSignal.login(String(window.currentUserId));
                } catch (loginError) {
                    log('OneSignal login failed:', loginError);
                }

                if (OneSignal?.User?.PushSubscription?.addEventListener) {
                    OneSignal.User.PushSubscription.addEventListener('change', function (event) {
                        const currentId = event?.current?.id || '';
                        log('OneSignal subscription changed:', event?.current?.optedIn, currentId);
                        if (currentId) {
                            sendPlayerIdToServer(currentId);
                        }
                    });
                }

                if (OneSignal?.Notifications?.addEventListener) {
                    OneSignal.Notifications.addEventListener('permissionChange', function (permission) {
                        log('OneSignal permission changed:', permission);
                        if (permission) {
                            registerPlayerId(OneSignal);
                        }
                    });
                }

                registerPlayerId(OneSignal);

                if (!OneSignal.Notifications.permission) {
                    await showPermissionPrompt(OneSignal);
                }
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
