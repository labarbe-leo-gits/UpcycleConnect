// Leaflet integration for the map on the about page
(function () {
    var tries = 0;
    var maxTries = 20;

    function init() {
        var el = document.getElementById('upcycle-map');
        if (!el) return schedule();
        if (typeof L === 'undefined') return schedule();

        var lat = 48.8740;
        var lng = 2.3565;

        try {
            var map = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
            }).addTo(map);

            var marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup('<strong>UpcycleConnect - Headquarter</strong><br>174 Rue La Fayette, Paris').openPopup();

            if (map.zoomControl) map.zoomControl.setPosition('topright');
        } catch (err) {
            return schedule();
        }
    }

    function schedule() {
        tries++;
        if (tries > maxTries) return;
        setTimeout(init, 100);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
