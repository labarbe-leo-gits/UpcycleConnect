(function(){
    var materialLabels = [];
    var materialData   = [];
    var dates          = [];
    var loginsSeries   = [];

    var pieChart = null;
    var lineChart = null;

    if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);
    }
    function instantiateCharts() {
        if (typeof Chart === 'undefined') return;

        if (pieChart) {
            pieChart.destroy();
            pieChart = null;
        }
        if (lineChart) {
            lineChart.destroy();
            lineChart = null;
        }

        var pieCtx = document.getElementById('pie-chart');
        if (pieCtx) {
            pieCtx = pieCtx.getContext('2d');
            pieChart = new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: materialLabels,
                    datasets: [{
                        data: materialData,
                        backgroundColor: materialLabels.map((_,i)=>['#10b981','#34d399','#fbbf24','#60a5fa','#a78bfa','#f87171','#f59e0b','#6ee7b7','#93c5fd','#fb923c'][i%10]),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    plugins: {
                        legend:{position:'right'},
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: 12 },
                            formatter: function(value, ctx) {
                                var sum = ctx.chart.data.datasets[0].data.reduce(function(a,b){return a+b;},0);
                                var pct = sum ? Math.round(value*100/sum) : 0;
                                return value + '\n(' + pct + '%)';
                            }
                        }
                    }
                }
            });
        }

        var lineCtx = document.getElementById('line-chart');
        if (lineCtx) {
            lineCtx = lineCtx.getContext('2d');
            lineChart = new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        { label:'Logins', data: loginsSeries, borderColor:'#60a5fa', backgroundColor:'#60a5fa', fill:false, tension:0.3, pointRadius:4 }
                    ]
                },
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    elements: { point: { radius: 4 } },
                    scales:{
                        x:{title:{display:true,text:'Date'}},
                        y:{beginAtZero:true,title:{display:true,text:'Count'},ticks:{stepSize:1}}
                    },
                    plugins: {
                        datalabels: {
                            align: 'top',
                            color: '#000',
                            font: { size: 10 },
                            formatter: function(value) { return value; }
                        }
                    }
                }
            });
        }
    }

    var _pageReady = false;
    var _metricsReady = false;
    function hideInitialLoader() {
        var loader = document.getElementById('initial-loader');
        var main   = document.getElementById('main-content');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(function(){
                loader.style.display = 'none';
                loader.setAttribute('aria-hidden','true');
            }, 300);
        }
        if (main) {
            main.style.visibility = '';
        }
    }

    window.addEventListener('load', function(){
        _pageReady = true;
        if (_metricsReady) hideInitialLoader();
    });

    async function refreshKpis() {
        try {
            var res = await fetch('dashboard-api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('Failed to load dashboard data');
            var d = await res.json();
            console.log('dashboard raw response', d);
            console.log('dashboard metrics', d.dates, d.loginsSeries);

            if (d && d.error) {
                console.error('dashboard API returned error:', d.error);
                var errBox = document.getElementById('dashboard-error');
                if (!errBox) {
                    errBox = document.createElement('div');
                    errBox.id = 'dashboard-error';
                    errBox.style.color = '#c00';
                    errBox.style.padding = '10px';
                    errBox.style.background = '#fee';
                    errBox.style.marginBottom = '10px';
                    errBox.textContent = 'Unable to load dashboard data: ' + d.error;
                    var container = document.querySelector('.dashboard-container') || document.body;
                    container.insertBefore(errBox, container.firstChild);
                } else {
                    errBox.textContent = 'Unable to load dashboard data: ' + d.error;
                }
                return;
            }

            document.getElementById('user-count').textContent = d.userCount;
            document.getElementById('container-count').textContent = d.containerCount;
            document.getElementById('income-total').textContent = d.totalIncome.toFixed(2) + ' €';
            document.getElementById('project-count').textContent = d.projectCount;

            var userNewEl = document.querySelector('#user-count + .dashboard-small');
            if (userNewEl) {
                userNewEl.classList.add('detail');
                userNewEl.innerHTML = '<i class="fa-solid fa-calendar-day"></i> New accounts today: <strong>' + d.newUsersToday + '</strong>';
            }
            var userSinceEl = document.querySelector('#user-count + .dashboard-small + .dashboard-small');
            if (userSinceEl) {
                userSinceEl.classList.add('detail');
                userSinceEl.textContent = d.userDelta + ' since yesterday';
            }

            function pctChip(val, tooltip){
                var n = parseFloat(val) || 0;
                var sign = n > 0 ? '+' : '';
                var s = sign + n.toFixed(2) + '%';
                if (tooltip) {
                    return '<span class="help-icon" style="position:relative;display:inline-flex;align-items:center;cursor:pointer;">' +
                           '<span class="pct-chip">' + s + '</span>' +
                           '<span class="help-tooltip" style="width:150px;">' + tooltip + '</span>' +
                           '</span>';
                }
                return '<span class="pct-chip">' + s + '</span>';
            }

            var userDeltaEl = document.querySelector('#user-count + .dashboard-small + .dashboard-small');
            if (userDeltaEl) {
                userDeltaEl.textContent = d.userDelta + ' since yesterday';
            }
            var userCard = document.querySelector('.portal-card.blue');
            if (userCard) {
                var existing = userCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.userPct, 'since yesterday');
                userCard.appendChild(wrapper);
            }

            var contNewEl = document.querySelector('#container-count + .dashboard-small');
            if (contNewEl) {
                contNewEl.classList.add('detail');
                contNewEl.innerHTML = '<i class="fa-solid fa-box"></i> New deposits today: <strong>' + d.newDepositsToday + '</strong>';
            }
            var contEl = document.querySelector('#container-count + .dashboard-small + .dashboard-small');
            if (contEl) {
                contEl.classList.add('detail');
                contEl.textContent = d.containerDelta + ' of new deposits since yesterday';
            }
            var contCard = document.querySelector('.portal-card.green');
            if (contCard) {
                var existing = contCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.containerPct, 'of new deposits since yesterday');
                contCard.appendChild(wrapper);
            }

            var incNewEl = document.querySelector('#income-total + .dashboard-small');
            if (incNewEl) {
                incNewEl.classList.add('detail');
                incNewEl.innerHTML = '<i class="fa-solid fa-dollar-sign"></i> Today: <strong>' + d.todayIncome.toFixed(2) + ' €</strong>';
            }
            var incEl = document.querySelector('#income-total + .dashboard-small + .dashboard-small');
            if (incEl) {
                incEl.classList.add('detail');
                incEl.textContent = (d.incomeDelta>=0?'+':'')+d.incomeDelta.toFixed(2) + ' € vs yesterday';
            }
            var incCard = document.querySelector('.portal-card.yellow');
            if (incCard) {
                var existing = incCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.incomePct, 'vs yesterday');
                incCard.appendChild(wrapper);
            }

            var projNewEl = document.querySelector('#project-count + .dashboard-small');
            if (projNewEl) {
                projNewEl.classList.add('detail');
                projNewEl.innerHTML = '<i class="fa-solid fa-robot"></i> AI generated: <strong>' + d.aiPct.toFixed(2) + '%</strong>';
            }
            var projSince = document.querySelector('#project-count + .dashboard-small + .dashboard-small');
            if (projSince) {
                projSince.classList.add('detail');
                projSince.textContent = d.projectDelta + ' since yesterday';
            }

            materialLabels = d.materialLabels || [];
            materialData   = d.materialData || [];
            dates          = d.loginDates || [];
            loginsSeries   = d.loginSeries || [];
            instantiateCharts();
        } catch(e) {
            console.error(e);
        } finally {
            _metricsReady = true;
            if (_pageReady) hideInitialLoader();
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        instantiateCharts();
        refreshKpis();
    });
})();
