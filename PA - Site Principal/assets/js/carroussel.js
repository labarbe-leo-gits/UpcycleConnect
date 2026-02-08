// Simple accessible carousel for team section
(function () {
    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

    document.addEventListener('DOMContentLoaded', function () {
        var carousel = qs('.team-carousel');
        if (!carousel) return;

        var track = qs('.carousel-track', carousel);
        var items = qsa('.carousel-item', carousel);
        var prevBtn = qs('.carousel-btn.prev', carousel);
        var nextBtn = qs('.carousel-btn.next', carousel);
        var dotsContainer = qs('.carousel-dots', carousel);

        if (!track || items.length === 0) return;

        var current = 0;
        var autoplay = false;
        var interval = 4000;
        var timer = null;

        var dots = [];
        if (dotsContainer) {
            items.forEach(function (item, i) {
                var dot = document.createElement('button');
                dot.className = 'dot';
                dot.setAttribute('aria-label', 'Show ' + (item.dataset.name || ('member ' + (i+1))));
                dot.dataset.index = i;
                dotsContainer.appendChild(dot);
            });
            dots = qsa('.dot', dotsContainer);
        }

        function update() {
            var target = items[current];
            if (!target) return;
            var tx = -Math.round(target.offsetLeft);
            track.style.transform = 'translateX(' + tx + 'px)';
            if (dots.length) dots.forEach(function(d, idx){ d.classList.toggle('active', idx === current); });
        }

        function next() { current = (current + 1) % items.length; update(); }
        function prev() { current = (current - 1 + items.length) % items.length; update(); }

        if (nextBtn) nextBtn.addEventListener('click', function(){ next(); resetTimer(); });
        if (prevBtn) prevBtn.addEventListener('click', function(){ prev(); resetTimer(); });

        dots.forEach(function(d){ d.addEventListener('click', function(){ current = Number(this.dataset.index); update(); resetTimer(); }); });

        function setItemWidths(){
            var w = carousel.clientWidth;
            items.forEach(function(it){ it.style.flex = '0 0 ' + w + 'px'; it.style.width = w + 'px'; });
            track.style.width = (w * items.length) + 'px';
            update();
        }

        window.addEventListener('resize', setItemWidths);
        setItemWidths();

        function startTimer(){ if (!autoplay) return; timer = setInterval(next, interval); }
        function stopTimer(){ if (timer) { clearInterval(timer); timer = null; } }
        function resetTimer(){ stopTimer(); startTimer(); }

        carousel.addEventListener('mouseenter', stopTimer);
        carousel.addEventListener('focusin', stopTimer);
        carousel.addEventListener('mouseleave', startTimer);
        carousel.addEventListener('focusout', startTimer);

        startTimer();

        carousel.addEventListener('keydown', function(e){
            if (e.key === 'ArrowLeft') { prev(); resetTimer(); }
            if (e.key === 'ArrowRight') { next(); resetTimer(); }
        });
    });
})();
