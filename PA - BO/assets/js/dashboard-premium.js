(function () {
    function fmt(n, decimals) {
        return Number(n).toLocaleString('en-GB', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function removeSkeleton(el) {
        el.classList.remove('skeleton', 'skeleton-text');
    }

    function renderMaterials(stats, totalWeight) {
        const wrap = document.getElementById('materials-content');
        if (!stats || stats.length === 0) {
            wrap.innerHTML = '<p class="empty-state">No material data available — publish listings to see your stats.</p>';
            return;
        }
        const rows = stats.map(function (m) {
            const pct = totalWeight > 0 ? Math.round(m.weight / totalWeight * 1000) / 10 : 0;
            return `<tr>
                <td>${m.name}</td>
                <td>${fmt(m.weight, 2)}</td>
                <td>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar" style="width:${pct}%"></div>
                        <span>${pct} %</span>
                    </div>
                </td>
            </tr>`;
        }).join('');
        wrap.innerHTML = `<div class="materials-table-wrap">
            <table class="data-table">
                <thead><tr><th>Material</th><th>Total weight (kg)</th><th>Share (%)</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }

    function renderAlerts(alerts) {
        const wrap = document.getElementById('alerts-content');
        if (!alerts || alerts.length === 0) {
            wrap.innerHTML = '<p class="empty-state">No collection scheduled in the next 72 hours.</p>';
            return;
        }
        const items = alerts.map(function (a) {
            const d   = new Date(a.start_time);
            const day = String(d.getDate()).padStart(2, '0');
            const mon = String(d.getMonth() + 1).padStart(2, '0');
            const h   = String(d.getHours()).padStart(2, '0');
            const min = String(d.getMinutes()).padStart(2, '0');
            const time = `${day}/${mon} ${h}:${min}`;
            return `<li class="alert-item priority">
                <span class="alert-time">${time}</span>
                <span class="alert-title">${a.title}</span>
                <span class="alert-desc">${a.description}</span>
            </li>`;
        }).join('');
        wrap.innerHTML = `<ul class="alerts-list">${items}</ul>`;
    }

    document.addEventListener('DOMContentLoaded', async function () {
        const loader = document.getElementById('initial-loader');
        if (loader) loader.style.display = 'none';

        try {
            const res = await fetch('dashboard-premium-api', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (res.status === 403) {
                window.location.href = 'subscription';
                return;
            }
            if (!res.ok) throw new Error('Request failed');
            const d = await res.json();

            const kpiAnnonces = document.getElementById('kpi-annonces');
            const kpiRevenue  = document.getElementById('kpi-revenue');
            const kpiScore    = document.getElementById('kpi-score');
            const kpiWeight   = document.getElementById('kpi-weight');

            removeSkeleton(kpiAnnonces); kpiAnnonces.textContent = d.annonces_count;
            removeSkeleton(kpiRevenue);  kpiRevenue.textContent  = fmt(d.total_revenue, 2) + ' €';
            removeSkeleton(kpiScore);    kpiScore.textContent    = fmt(d.upcycling_score, 1);
            removeSkeleton(kpiWeight);   kpiWeight.textContent   = fmt(d.total_weight, 1) + ' kg';

            const ecoCo2    = document.getElementById('eco-co2');
            const ecoWeight = document.getElementById('eco-weight');
            const ecoScore  = document.getElementById('eco-score');

            removeSkeleton(ecoCo2);    ecoCo2.textContent    = fmt(d.total_co2, 2) + ' kg';
            removeSkeleton(ecoWeight); ecoWeight.textContent = fmt(d.total_weight, 1) + ' kg';
            removeSkeleton(ecoScore);  ecoScore.textContent  = fmt(d.upcycling_score, 1);

            renderMaterials(d.material_stats, d.total_weight);
            renderAlerts(d.alerts);

        } catch (e) {
            ['kpi-annonces', 'kpi-revenue', 'kpi-score', 'kpi-weight',
             'eco-co2', 'eco-weight', 'eco-score'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) { removeSkeleton(el); el.textContent = '—'; }
            });
            document.getElementById('materials-content').innerHTML =
                '<p class="empty-state">Unable to load data.</p>';
            document.getElementById('alerts-content').innerHTML =
                '<p class="empty-state">Unable to load data.</p>';
        }
    });
})();
