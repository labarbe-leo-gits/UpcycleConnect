(function () {
    var CHART_COLORS = [
        '#10b981','#34d399','#fbbf24','#60a5fa','#a78bfa',
        '#f87171','#f59e0b','#6ee7b7','#93c5fd','#fb923c'
    ];

    var materialsChart = null;

    function fmt(n, decimals) {
        return Number(n).toLocaleString('en-GB', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function renderMaterialsChart(stats, totalWeight) {
        var wrap  = document.getElementById('materials-chart-wrap');
        var empty = document.getElementById('materials-empty');
        if (!wrap) return;
        if (!stats || stats.length === 0) {
            wrap.style.display = 'none';
            if (empty) empty.style.display = '';
            return;
        }
        var labels = stats.map(function (m) { return m.name; });
        var data   = stats.map(function (m) { return m.weight; });
        var colors = stats.map(function (_, i) { return CHART_COLORS[i % CHART_COLORS.length]; });

        if (materialsChart) { materialsChart.destroy(); }
        var ctx = document.getElementById('chart-materials').getContext('2d');
        materialsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            padding: 10,
                            font: { size: 12 },
                            color: '#374151'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var pct = totalWeight > 0 ? Math.round(ctx.parsed / totalWeight * 1000) / 10 : 0;
                                return ' ' + fmt(ctx.parsed, 2) + ' kg (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    function renderAlerts(alerts) {
        var wrap = document.getElementById('alerts-content');
        if (!wrap) return;
        if (!alerts || alerts.length === 0) {
            wrap.innerHTML = '<p class="empty-state">No collection scheduled in the next 72 hours.</p>';
            return;
        }
        var items = alerts.map(function (a) {
            var d   = new Date(a.start_time);
            var day = String(d.getDate()).padStart(2, '0');
            var mon = String(d.getMonth() + 1).padStart(2, '0');
            var h   = String(d.getHours()).padStart(2, '0');
            var min = String(d.getMinutes()).padStart(2, '0');
            return '<li class="alert-item priority">'
                + '<span class="alert-time">' + day + '/' + mon + ' ' + h + ':' + min + '</span>'
                + '<span class="alert-title">' + a.title + '</span>'
                + '<span class="alert-desc">' + a.description + '</span>'
                + '</li>';
        }).join('');
        wrap.innerHTML = '<ul class="alerts-list">' + items + '</ul>';
    }

    function showPremiumError() {
        ['kpi-annonces','kpi-revenue','kpi-score','kpi-weight',
         'eco-co2','eco-weight','eco-score'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '--';
        });
        var ac = document.getElementById('alerts-content');
        if (ac) ac.innerHTML = '<p class="empty-state">Unable to load data.</p>';
        var mw = document.getElementById('materials-chart-wrap');
        if (mw) mw.style.display = 'none';
        var me = document.getElementById('materials-empty');
        if (me) { me.textContent = 'Unable to load data.'; me.style.display = ''; }
    }

    function loadPremiumData() {
        fetch('dashboard-premium-api', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) throw new Error('failed');
            return res.json();
        }).then(function (d) {
            document.getElementById('kpi-annonces').textContent = d.annonces_count;
            document.getElementById('kpi-revenue').textContent  = fmt(d.total_revenue, 2) + ' \u20ac';
            document.getElementById('kpi-score').textContent    = fmt(d.upcycling_score, 1);
            document.getElementById('kpi-weight').textContent   = fmt(d.total_weight, 1) + ' kg';
            document.getElementById('eco-co2').textContent      = fmt(d.total_co2, 2) + ' kg';
            document.getElementById('eco-weight').textContent   = fmt(d.total_weight, 1) + ' kg';
            document.getElementById('eco-score').textContent    = fmt(d.upcycling_score, 1);
            renderMaterialsChart(d.material_stats, d.total_weight);
            renderAlerts(d.alerts);
        }).catch(showPremiumError);
    }

    function loadFreeData() {
        fetch('dashboard-free-api', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) throw new Error('failed');
            return res.json();
        }).then(function (d) {
            var el;
            el = document.getElementById('free-kpi-annonces');
            if (el) el.textContent = d.annonces_count;
            el = document.getElementById('free-kpi-revenue');
            if (el) el.textContent = fmt(d.total_revenue, 2) + ' \u20ac';
            el = document.getElementById('free-kpi-score');
            if (el) el.textContent = fmt(d.upcycling_score, 1);
        }).catch(function () {
            ['free-kpi-annonces', 'free-kpi-revenue', 'free-kpi-score'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.textContent = '--';
            });
        });
    }

    function wireSubscribeButtons(priceDisplay) {
        var priceEl = document.getElementById('free-price-display');
        if (priceEl && priceDisplay) priceEl.textContent = priceDisplay;

        ['btn-subscribe','btn-subscribe-2'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) return;
            btn.addEventListener('click', function () {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting...';
                fetch('create-subscription-checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({})
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                    }
                }).catch(function () {
                    alert('Network error.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', async function () {
        var loader = document.getElementById('initial-loader');
        if (loader) loader.style.display = 'none';

        try {
            var res  = await fetch('subscription-api', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('failed');
            var data = await res.json();

            document.getElementById('dash-skeleton').style.display = 'none';

            var subBtn      = document.getElementById('dash-sub-btn');
            var subBtnLabel = document.getElementById('dash-sub-btn-label');

            if (data.is_premium) {
                if (subBtnLabel) subBtnLabel.textContent = 'My subscription';
                if (subBtn)      subBtn.style.display    = '';
                document.getElementById('dash-premium').style.display = '';
                loadPremiumData();
            } else {
                if (subBtnLabel) subBtnLabel.textContent = 'Go Premium';
                if (subBtn) {
                    subBtn.className    = 'btn btn-primary btn-sm';
                    subBtn.style.display = '';
                }
                document.getElementById('dash-free').style.display = '';
                loadFreeData();
                wireSubscribeButtons(data.price_display);
            }
        } catch (e) {
            document.getElementById('dash-skeleton').style.display = 'none';
            document.getElementById('dash-free').style.display = '';
            loadFreeData();
            wireSubscribeButtons(null);
        }
    });
})();