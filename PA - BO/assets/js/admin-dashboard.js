(function(){
    var materialLabels = [];
    var materialData   = [];
    var dates          = [];
    var loginsSeries   = [];

    var allLoginDates    = [];
    var allLoginSeries   = [];
    var allRegisterDates = [];
    var allRegisterSeries= [];
    var currentRange     = 'weekly';
    var currentLogFile   = 'login';

    var pieChart = null;
    var lineChart = null;
    var barChart = null;
    var dbTableLabels = [];
    var dbTableCounts = [];
    var serverInfo = {};
    var graphsLive = false;
    var chartsRenderedOnce = false;

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
        if (barChart) {
            barChart.destroy();
            barChart = null;
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

        var barCtx = document.getElementById('db-table-chart');
        if (barCtx) {
            barCtx = barCtx.getContext('2d');
            barChart = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: dbTableLabels,
                    datasets: [{
                        label: 'Rows',
                        data: dbTableCounts.map(function(value){ return Number(value) || 0; }),
                        backgroundColor: dbTableLabels.map((_,i)=>['#60a5fa','#10b981','#f59e0b','#a78bfa','#f87171','#34d399','#fb923c','#38bdf8','#fbbf24','#818cf8','#f472b6'][i%11]),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    scales:{
                        x:{title:{display:true,text:'Table'}},
                        y:{beginAtZero:true,title:{display:true,text:'Count'}}
                    },
                    plugins: {
                        legend:{display:false},
                        datalabels: {
                            anchor: 'end',
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

    function getWeekKey(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var day = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - day);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        var week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        return d.getUTCFullYear() + '-W' + String(week).padStart(2, '0');
    }

    function getChartDataForRange(range, sourceDates, sourceSeries) {
        if (!sourceDates || !sourceSeries || sourceDates.length !== sourceSeries.length) {
            return { dates: [], series: [] };
        }

        if (range === 'daily' || range === 'weekly' || range === 'monthly' || range === 'annually') {
            var grouped = {};

            for (var i = 0; i < sourceDates.length; i++) {
                var rawDate = sourceDates[i];
                var value = Number(sourceSeries[i]) || 0;
                var d = new Date(rawDate + 'T00:00:00');
                if (isNaN(d)) continue;

                var key;
                if (range === 'daily') {
                    key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                } else if (range === 'weekly') {
                    key = getWeekKey(d);
                } else if (range === 'monthly') {
                    key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                } else {
                    key = String(d.getFullYear());
                }

                grouped[key] = (grouped[key] || 0) + value;
            }

            var sortedKeys = Object.keys(grouped).sort();
            var outDates = sortedKeys;
            var outSeries = sortedKeys.map(function(k){ return grouped[k]; });
            return { dates: outDates, series: outSeries };
        }

        return { dates: sourceDates.slice(), series: sourceSeries.slice() };
    }

    function getCurrentLogData() {
        if (currentLogFile === 'register') {
            return { dates: allRegisterDates, series: allRegisterSeries };
        }
        return { dates: allLoginDates, series: allLoginSeries };
    }

    function updateActivityChart() {
        var mode = currentRange;
        var source = getCurrentLogData();
        var data = getChartDataForRange(mode, source.dates, source.series);
        dates = data.dates;
        loginsSeries = data.series;
        var titleEl = document.getElementById('line-chart-title');
        if (titleEl) {
            titleEl.textContent = 'Users activity over time (' + currentLogFile + '.log, ' + mode + ')';
        }
        instantiateCharts();
    }

    function formatBytes(bytes) {
        if (bytes === undefined || bytes === null) return 'N/A';
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var value = Number(bytes) || 0;
        var unitIndex = 0;
        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }
        return value.toFixed(1) + ' ' + units[unitIndex];
    }

    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '0s';
        var minutes = Math.floor(seconds / 60);
        var hours = Math.floor(minutes / 60);
        var days = Math.floor(hours / 24);
        seconds = Math.floor(seconds % 60);
        minutes = minutes % 60;
        var parts = [];
        if (days) parts.push(days + 'd');
        if (hours % 24) parts.push((hours % 24) + 'h');
        if (minutes) parts.push(minutes + 'm');
        if (seconds) parts.push(seconds + 's');
        return parts.join(' ');
    }

    function formatPercent(value) {
        if (value === undefined || value === null || Number.isNaN(Number(value))) return 'N/A';
        return Number(value).toFixed(1) + '%';
    }

    function renderServerSummary(info) {
        var summary = document.getElementById('server-summary');
        if (!summary) return;
        if (!info || Object.keys(info).length === 0) {
            summary.textContent = 'Server metrics unavailable.';
            return;
        }
        summary.innerHTML = '';
        var cards = [
            { icon: 'fa-desktop', title: 'OS', value: info.os || 'N/A', subtitle: info.arch || '' },
            { icon: 'fa-microchip', title: 'CPU cores', value: info.numCpu || 'N/A', subtitle: 'Goroutines: ' + (info.numGoroutine || 'N/A') },
            { icon: 'fa-clock', title: 'Uptime', value: formatDuration(info.uptimeSeconds), subtitle: '' },
            { icon: 'fa-memory', title: 'RAM used', value: formatBytes(info.ramUsed), subtitle: 'of ' + formatBytes(info.ramTotal) + ' (' + formatPercent(info.ramUsedPct) + ')' },
            { icon: 'fa-hdd', title: 'Disk used', value: formatBytes(info.diskUsed), subtitle: 'of ' + formatBytes(info.diskTotal) + ' (' + formatPercent(info.diskUsedPct) + ')' },
            { icon: 'fa-code', title: 'Go version', value: info.goVersion || 'N/A', subtitle: '' },
            { icon: 'fa-memory', title: 'Memory alloc', value: formatBytes(info.memoryAlloc), subtitle: 'Total alloc: ' + formatBytes(info.memoryTotalAlloc) },
            { icon: 'fa-recycle', title: 'GC cycles', value: info.numGC || 'N/A', subtitle: '' }
        ];
        cards.forEach(function(card) {
            var cardEl = document.createElement('div');
            cardEl.className = 'server-stat-card';
            cardEl.innerHTML =
                '<span class="stat-icon"><i class="fa-solid ' + card.icon + '"></i></span>' +
                '<div>' +
                '<h4>' + card.title + '</h4>' +
                '<p>' + card.value + '</p>' +
                (card.subtitle ? '<small>' + card.subtitle + '</small>' : '') +
                '</div>';
            summary.appendChild(cardEl);
        });
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
                contEl.textContent = d.containerDelta + ' of new container since yesterday';
            }
            var contCard = document.querySelector('.portal-card.green');
            if (contCard) {
                var existing = contCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.containerPct, 'of new container since yesterday');
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

            document.getElementById('annonce-count').textContent = d.annonceCount || 0;
            var annonceNewEl = document.querySelector('#annonce-count + .dashboard-small');
            if (annonceNewEl) {
                annonceNewEl.classList.add('detail');
                annonceNewEl.innerHTML = '<i class="fa-solid fa-list"></i> Total listings in catalog: <strong>' + (d.annonceCount || 0) + '</strong>';
            }
            var annonceDelta = document.querySelector('#annonce-count + .dashboard-small + .dashboard-small');
            if (annonceDelta) {
                annonceDelta.classList.add('detail');
                annonceDelta.innerHTML = '<i class="fa-solid fa-check"></i> Listings are live';
            }
            var annonceCard = document.querySelector('.portal-card.purple');
            if (annonceCard) {
                var existing = annonceCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.annoncePct, 'since yesterday');
                annonceCard.appendChild(wrapper);
            }

            document.getElementById('deposit-count').textContent = d.pendingDeposits || 0;
            var depositEl = document.querySelector('#deposit-count + .dashboard-small');
            if (depositEl) {
                depositEl.classList.add('detail');
                depositEl.innerHTML = '<i class="fa-solid fa-hourglass-half"></i> Pending requests: <strong>' + (d.pendingDeposits || 0) + '</strong>';
            }
            var depositDelta = document.querySelector('#deposit-count + .dashboard-small + .dashboard-small');
            if (depositDelta) {
                depositDelta.classList.add('detail');
                depositDelta.innerHTML = '<i class="fa-solid fa-user-clock"></i> Awaiting review';
            }
            var depositCard = document.querySelector('.portal-card.teal');
            if (depositCard) {
                var existing = depositCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.pendingDepositsPct, 'since yesterday');
                depositCard.appendChild(wrapper);
            }

            document.getElementById('event-count').textContent = d.eventCount || 0;
            var eventEl = document.querySelector('#event-count + .dashboard-small');
            if (eventEl) {
                eventEl.classList.add('detail');
                eventEl.innerHTML = '<i class="fa-solid fa-calendar-plus"></i> Upcoming events: <strong>' + (d.upcomingEvents || 0) + '</strong>';
            }
            var eventDelta = document.querySelector('#event-count + .dashboard-small + .dashboard-small');
            if (eventDelta) {
                eventDelta.classList.add('detail');
                eventDelta.innerHTML = '<i class="fa-solid fa-calendar-check"></i> Total events';
            }
            var eventCard = document.querySelector('.portal-card.orange');
            if (eventCard) {
                var existing = eventCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.eventPct, 'since yesterday');
                eventCard.appendChild(wrapper);
            }

            document.getElementById('pending-count').textContent = d.pendingRegistrations || 0;
            var pendingEl = document.querySelector('#pending-count + .dashboard-small');
            if (pendingEl) {
                pendingEl.classList.add('detail');
                pendingEl.innerHTML = '<i class="fa-solid fa-user-clock"></i> Awaiting validation';
            }
            var pendingDelta = document.querySelector('#pending-count + .dashboard-small + .dashboard-small');
            if (pendingDelta) {
                pendingDelta.classList.add('detail');
                pendingDelta.innerHTML = '<i class="fa-solid fa-circle-info"></i> Review queue';
            }
            var pendingCard = document.querySelector('.portal-card.pink');
            if (pendingCard) {
                var existing = pendingCard.querySelector('.pct-chip-wrapper');
                if (existing) existing.remove();
                var wrapper = document.createElement('span');
                wrapper.className = 'pct-chip-wrapper';
                wrapper.innerHTML = pctChip(d.pendingRegistrationsPct, 'since yesterday');
                pendingCard.appendChild(wrapper);
            }

            dbTableLabels = d.dbTableLabels || [];
            dbTableCounts = d.dbTableCounts || [];
            serverInfo = d.serverInfo || {};
            renderServerSummary(serverInfo);

            materialLabels    = d.materialLabels || [];
            materialData      = d.materialData || [];
            dbTableLabels     = d.dbTableLabels || [];
            dbTableCounts     = d.dbTableCounts || [];
            allLoginDates     = d.loginDates || [];
            allLoginSeries    = d.loginSeries || [];
            allRegisterDates  = d.registerDates || [];
            allRegisterSeries = d.registerSeries || [];
            if (!chartsRenderedOnce || graphsLive) {
                updateActivityChart();
                chartsRenderedOnce = true;
            } else {
                var titleEl = document.getElementById('line-chart-title');
                if (titleEl) {
                    titleEl.textContent = 'Users activity over time (' + currentLogFile + '.log, ' + currentRange + ') - paused';
                }
            }
        } catch(e) {
            console.error(e);
        } finally {
            _metricsReady = true;
            if (_pageReady) hideInitialLoader();
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        var rangeSelector = document.getElementById('activity-range-select');
        if (rangeSelector) {
            rangeSelector.addEventListener('change', function(){
                currentRange = this.value || 'weekly';
                if (graphsLive) updateActivityChart();
            });
        }
        var fileSelector = document.getElementById('activity-file-select');
        if (fileSelector) {
            fileSelector.addEventListener('change', function(){
                currentLogFile = this.value || 'login';
                if (graphsLive) updateActivityChart();
            });
        }
        var liveToggle = document.getElementById('graph-live-toggle');
        if (liveToggle) {
            liveToggle.addEventListener('change', function(){
                graphsLive = this.checked;
                if (graphsLive) {
                    updateActivityChart();
                } else {
                    var titleEl = document.getElementById('line-chart-title');
                    if (titleEl) {
                        titleEl.textContent = 'Users activity over time (' + currentLogFile + '.log, ' + currentRange + ') - paused';
                    }
                }
            });
        }
        instantiateCharts();
        refreshKpis();
        setInterval(refreshKpis, 5000);
    });
})();
