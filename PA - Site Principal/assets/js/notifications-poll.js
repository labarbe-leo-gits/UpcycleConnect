(function() {
    'use strict';

    var header = document.querySelector('header[data-api-base][data-user-id]');
    if (!header) {
        return;
    }

    var apiBase = header.getAttribute('data-api-base') || '';
    var userId = header.getAttribute('data-user-id') || '';
    var badge = document.getElementById('notifications-count');

    if (!apiBase || !userId || !badge) {
        return;
    }

    var endpoint = apiBase.replace(/\/$/, '') + '/users/' + encodeURIComponent(userId) + '/notifications';

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = String(count);
            badge.hidden = false;
        } else {
            badge.textContent = '0';
            badge.hidden = true;
        }
    }

    function fetchNotifications() {
        fetch(endpoint, { cache: 'no-store' })
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

    fetchNotifications();
    setInterval(fetchNotifications, 5000);
})();
