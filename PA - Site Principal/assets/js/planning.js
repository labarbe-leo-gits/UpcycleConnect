

(function() {
    var modal = document.getElementById('planning-modal');
    var modalButton = document.getElementById('add-planning-btn');
    var closeButton = document.getElementById('close-planning-modal');
    var cancelButton = document.getElementById('cancel-planning');
    var form = document.getElementById('planning-form');
    var planningList = document.getElementById('planning-list');
    var timeSlots = document.querySelectorAll('.time-slot');
    var dateRangeSpan = document.getElementById('planning-date-range');
    var prevWeekBtn = document.getElementById('prev-week-btn');
    var nextWeekBtn = document.getElementById('next-week-btn');
    var todayBtn = document.getElementById('today-btn');
    var dayHeaders = [
        document.getElementById('day-header-0'),
        document.getElementById('day-header-1'),
        document.getElementById('day-header-2'),
        document.getElementById('day-header-3'),
        document.getElementById('day-header-4'),
        document.getElementById('day-header-5'),
        document.getElementById('day-header-6')
    ];

    var plannings = [];
    var currentWeekStart = getMonday(new Date());
    var pendingDeleteIndex = null;
    var pendingEditIndex = null;
    var viewPlanningMapInstance = null;

    function getMonday(date) {
        var d = new Date(date);
        var day = d.getDay();
        var diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setDate(diff);
        d.setHours(0,0,0,0);
        return d;
    }

    function addDays(date, days) {
        var d = new Date(date);
        d.setDate(d.getDate() + days);
        return d;
    }

    function formatDate(date) {
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatLocalISO(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function showPreloader() {
        var p = document.getElementById('planning-preloader');
        if (p) p.style.display = 'flex';
    }

    function hidePreloader() {
        var p = document.getElementById('planning-preloader');
        if (p) p.style.display = 'none';
    }

    function updateDateRangeDisplay() {
        var weekStart = currentWeekStart;
        var weekEnd = addDays(weekStart, 6);
        dateRangeSpan.textContent = formatDate(weekStart) + ' - ' + formatDate(weekEnd);
        var today = new Date();
        today.setHours(0,0,0,0);
        for (var i = 0; i < 7; i++) {
            var d = addDays(weekStart, i);
            var dayName = d.toLocaleDateString(undefined, { weekday: 'long' });
            dayHeaders[i].textContent = dayName + ' ' + d.getDate();
            if (d.getTime() === today.getTime()) {
                dayHeaders[i].classList.add('today');
            } else {
                dayHeaders[i].classList.remove('today');
            }
        }
        var timetableRows = document.querySelectorAll('.timetable tbody tr');
        for (var r = 0; r < timetableRows.length; r++) {
            var cells = timetableRows[r].querySelectorAll('td');
            for (var c = 0; c < cells.length; c++) {
                if (c === 0) continue;
                var d = addDays(weekStart, c-1);
                if (d.getTime() === today.getTime()) {
                    cells[c].classList.add('today');
                } else {
                    cells[c].classList.remove('today');
                }
            }
        }
    }

    function goToPrevWeek() {
        currentWeekStart = addDays(currentWeekStart, -7);
        updateDateRangeDisplay();
        loadPlannings();
    }

    function goToNextWeek() {
        currentWeekStart = addDays(currentWeekStart, 7);
        updateDateRangeDisplay();
        loadPlannings();
    }

    function openModal() {
        modal.classList.add('active');
        form.reset();
        hideModalError();
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    function loadPlannings() {
        showPreloader();
        var weekStart = currentWeekStart;
        var weekEnd = addDays(weekStart, 6);
        var startParam = formatLocalISO(weekStart) + ' 00:00:00';
        var endParam = formatLocalISO(addDays(weekEnd, 1)) + ' 00:00:00';

        var url = './planning-api?start=' + encodeURIComponent(startParam) + '&end=' + encodeURIComponent(endParam);

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load plannings');
            }
            return response.json();
        })
        .then(function(data) {
            if (Array.isArray(data)) {
                plannings = data;
            } else {
                plannings = data.items || data.plannings || [];
            }

            plannings = plannings.map(function(p) {
                var item = Object.assign({}, p);
                if (!item.type) item.type = item.title ? 'slot' : 'slot';
                if (!item.start_time && item.start_time_unix) {
                    var d = new Date(item.start_time_unix * 1000);
                    item.start_time = d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0') + ':00';
                }
                if (!item.date && item.start_time && item.start_time.indexOf(' ') !== -1) {
                    item.date = item.start_time.split(' ')[0];
                }
                return item;
            });

            renderPlannings();
        })
        .catch(function(error) {
            console.error('Error loading plannings:', error);
            renderPlannings();
        });
    }


    function renderPlannings() {
        planningList.innerHTML = '';

        if (plannings.length === 0) {
            planningList.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <p>No planning slots yet</p>
                    <small>Click "Add Time Slot" to create your first availability</small>
                </div>
            `;
            renderTimetable();
            hidePreloader();
            return;
        }

        var weekStart = currentWeekStart;
        var weekEnd = addDays(weekStart, 6);
        var weekPlannings = plannings.filter(function(planning) {
            if (!planning.date) return true;
            var planningDate = new Date(planning.date);
            planningDate.setHours(0,0,0,0);
            return planningDate >= weekStart && planningDate <= weekEnd;
        });

        if (weekPlannings.length === 0) {
            planningList.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <p>No planning slots for this week</p>
                    <small>Click "Add Time Slot" to create your first availability</small>
                </div>
            `;
            renderTimetable();
            hidePreloader();
            return;
        } else {
            weekPlannings.forEach(function(planning, index) {
                var globalIndex = plannings.indexOf(planning);
                var item = document.createElement('div');
                item.className = 'planning-item ' + planning.type;
                var dateStr = planning.date ? formatDisplayDate(planning.date) : '';
                var timePeriod = formatDisplayTime(planning.start_time) + ' - ' + formatDisplayTime(planning.end_time);
                var descHtml = planning.description ? '<div class="planning-item-desc">' + escapeHtml(planning.description) + '</div>' : '';
                var title = planning.title || 'Slot';
                item.innerHTML = `
                    <div class="planning-item-info">
                        <div class="planning-item-time">${escapeHtml(dateStr)} <span class="dot">·</span> ${escapeHtml(timePeriod)}</div>
                        <div class="planning-item-type">${escapeHtml(title)}</div>
                        
                    </div>
                    <div class="planning-item-actions">
                        <button class="icon-button" onclick="viewPlanning(${globalIndex})" title="View">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button class="icon-button" onclick="editPlanning(${globalIndex})" title="Edit">
                            <i class="fa-solid fa-pencil"></i>
                        </button>
                        <button class="icon-button" onclick="deletePlanning(${globalIndex})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `;
                planningList.appendChild(item);
            });
        }

        renderTimetable(weekPlannings);
        hidePreloader();
    }

    function formatDisplayDate(dateStr) {
        if (!dateStr) return '';
        if (/^\d{4}-\d{2}-\d{2}/.test(dateStr)) {
            var parts = dateStr.split(' ')[0].split('-');
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        var dd = String(d.getDate()).padStart(2,'0');
        var mm = String(d.getMonth()+1).padStart(2,'0');
        var yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy;
    }

    function formatDisplayTime(timeStr) {
        if (!timeStr) return '';
        var t = timeStr;
        if (timeStr.indexOf(' ') !== -1) t = timeStr.split(' ')[1];
        var parts = t.split(':');
        if (parts.length < 2) return t;
        var hh = parseInt(parts[0], 10);
        var mm = parts[1];
        var ampm = hh >= 12 ? 'PM' : 'AM';
        var h12 = hh % 12;
        if (h12 === 0) h12 = 12;
        return String(h12) + ':' + String(mm).padStart(2,'0') + ' ' + ampm;
    }

    function renderTimetable(weekPlannings) {
        timeSlots.forEach(function(slot) {
            slot.innerHTML = '<div class="slot-content"></div>';
        });

        var wrapper = document.querySelector('.timetable-wrapper');
        if (!wrapper) return;
        var oldOverlay = wrapper.querySelector('.timetable-overlay');
        if (oldOverlay) oldOverlay.parentNode.removeChild(oldOverlay);

        var overlay = document.createElement('div');
        overlay.className = 'timetable-overlay';
        var table = wrapper.querySelector('.timetable');
        overlay.style.width = '100%';
        overlay.style.height = table ? table.clientHeight + 'px' : '100%';
        wrapper.appendChild(overlay);

        var sampleRow = document.querySelector('.time-row');
        var rowHeight = sampleRow ? sampleRow.clientHeight : 60;
            var eventsByDay = {};

            function parseDateTime(dateStr, timeStr) {
                if (!timeStr && dateStr && dateStr.indexOf(' ') >= 0) {
                    var parts = dateStr.split(' ');
                    return new Date(parts[0] + 'T' + parts[1]);
                }
                if (!timeStr) return new Date(dateStr);
                var dt = new Date(dateStr + 'T' + timeStr);
                if (isNaN(dt)) {
                    var s = (dateStr + ' ' + timeStr).replace(' ', 'T');
                    dt = new Date(s);
                }
                return dt;
            }

            (weekPlannings || []).forEach(function(planning) {
                var startDT = parseDateTime(planning.date || planning.start_time, (planning.start_time || '').split(' ')[1]);
                var endDT = parseDateTime(planning.date || planning.end_time, (planning.end_time || '').split(' ')[1]);
                if (!startDT || !endDT || isNaN(startDT) || isNaN(endDT)) return;

                var dayIndex = (startDT.getDay() + 6) % 7;
                var startMinutes = startDT.getHours() * 60 + startDT.getMinutes();
                var endMinutes = endDT.getHours() * 60 + endDT.getMinutes();
                if (endMinutes <= startMinutes) endMinutes = startMinutes + 15;

                eventsByDay[dayIndex] = eventsByDay[dayIndex] || [];
                eventsByDay[dayIndex].push({ planning: planning, startDT: startDT, endDT: endDT, startMinutes: startMinutes, endMinutes: endMinutes });
            });

            Object.keys(eventsByDay).forEach(function(dayKey) {
                var day = parseInt(dayKey, 10);
                var events = eventsByDay[day].sort(function(a, b) { return a.startMinutes - b.startMinutes; });

                var columns = [];
                events.forEach(function(ev) {
                    var placed = false;
                    for (var c = 0; c < columns.length; c++) {
                        var last = columns[c][columns[c].length - 1];
                        if (ev.startMinutes >= last.endMinutes) {
                            columns[c].push(ev);
                            ev.col = c;
                            placed = true;
                            break;
                        }
                    }
                    if (!placed) {
                        ev.col = columns.length;
                        columns.push([ev]);
                    }
                });

                var sampleCell = document.querySelector(`.time-slot[data-day="${day}"][data-hour="0"]`);
                if (!sampleCell) {
                    sampleCell = document.querySelector(`.time-slot[data-day="${day}"]`);
                }
                if (!sampleCell) return;
                var cellRect = sampleCell.getBoundingClientRect();
                var tableRect = table.getBoundingClientRect();
                var dayLeft = cellRect.left - tableRect.left;

                var colCount = Math.max(1, columns.length);
                var colWidth = (cellRect.width - 12) / colCount;

                events.forEach(function(ev) {
                    var minuteOffset = (ev.startDT.getMinutes() / 60) * rowHeight;
                    var topPx = (ev.startDT.getHours() * rowHeight) + minuteOffset;
                    var heightPx = Math.max(28, ((ev.endMinutes - ev.startMinutes) / 60) * rowHeight);

                    var overlapping = events.filter(function(o) {
                        return !(o.endMinutes <= ev.startMinutes || o.startMinutes >= ev.endMinutes);
                    });
                    var concurrency = Math.max(1, overlapping.length);

                    var overlapCols = overlapping.map(function(o){ return o.col; }).sort(function(a,b){return a-b;});
                    var idx = overlapCols.indexOf(ev.col);
                    if (idx < 0) idx = ev.col;

                    var eventColWidth = (cellRect.width - 12) / concurrency;
                    var leftPx = dayLeft + 6 + (idx * eventColWidth);
                    var widthPx = Math.max(40, eventColWidth - 6);

                    var eventEl = document.createElement('div');
                    eventEl.className = 'slot-event ' + (ev.planning.type || '').toLowerCase();
                    var pillLabel = (ev.planning.title && ev.planning.title.length) ? ev.planning.title : (ev.planning.type || 'Slot');
                    var displayLabel = pillLabel.length > 18 ? pillLabel.substring(0, 18) + '...' : pillLabel;
                    eventEl.textContent = displayLabel;
                    eventEl.title = pillLabel + '\n' + (ev.planning.description || '');

                    eventEl.style.position = 'absolute';
                    eventEl.style.left = leftPx + 'px';
                    eventEl.style.top = topPx + 'px';
                    eventEl.style.width = widthPx + 'px';
                    eventEl.style.height = heightPx + 'px';
                    eventEl.style.cursor = 'pointer';
                    eventEl.style.marginTop = '46px';

                    eventEl.addEventListener('click', function(e) {
                        e.stopPropagation();
                        var globalIndex = plannings.indexOf(ev.planning);
                        if (globalIndex < 0 && ev.planning && ev.planning.id) {
                            globalIndex = plannings.findIndex(function(p){ return p && p.id && p.id === ev.planning.id; });
                        }
                        if (globalIndex >= 0) {
                            try { window.viewPlanning(globalIndex); } catch(err) { console.warn('viewPlanning call failed', err); }
                        } else {
                            console.warn('[planning] could not determine global index for clicked event');
                        }
                    });
                    eventEl.style.boxSizing = 'border-box';
                    eventEl.style.overflow = 'hidden';
                    eventEl.style.whiteSpace = 'nowrap';
                    eventEl.style.textOverflow = 'ellipsis';
                    eventEl.style.padding = '6px 8px';
                    eventEl.style.pointerEvents = 'auto';

                    overlay.appendChild(eventEl);
                });
            });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"]+/g, function(match) {
            switch(match) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
            }
        });
    }

    function makeLinksClickable(text) {
        if (!text) return '';
        var escaped = escapeHtml(text);
        var linked = escaped.replace(/(https?:\/\/[^\s<]+)/gi, function(url){
            var safeUrl = url.replace(/&amp;/g, '&');
            return '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
        });
        return linked.replace(/\n/g, '<br>');
    }

    function extractAddress(text) {
        if (!text) return '';
        var match = text.match(/Presential session at:\s*([^\n]+)/i);
        return match ? match[1].trim() : '';
    }

    function extractMapUrls(text) {
        if (!text) return [];
        var urls = [];
        var regex = /(https?:\/\/[^\s<]+)/gi;
        var match;
        while ((match = regex.exec(text)) !== null) {
            urls.push(match[1]);
        }
        return urls;
    }

    function getMapLinkLabel(url) {
        var host = '';
        try {
            host = new URL(url).hostname.toLowerCase();
        } catch (e) {
            host = url;
        }
        if (host.indexOf('google.com') >= 0) return 'Google Maps';
        if (host.indexOf('openstreetmap.org') >= 0) return 'OpenStreetMap';
        return host.replace(/^www\./, '');
    }

    function getMapLinkIcon(url) {
        try {
            var host = new URL(url).hostname.toLowerCase();
            if (host.indexOf('google.com') >= 0) return 'fa-brands fa-google';
            if (host.indexOf('openstreetmap.org') >= 0) return 'fa-solid fa-map-location-dot';
        } catch (e) {}
        return 'fa-solid fa-globe';
    }

    function makeDefaultMapUrls(address) {
        if (!address) return [];
        var query = encodeURIComponent(address);
        return [
            'https://www.google.com/maps/search/?api=1&query=' + query,
            'https://www.openstreetmap.org/search?query=' + query
        ];
    }

    function renderPlanningMap(address, urls) {
        var container = document.getElementById('view-planning-map-container');
        var mapEl = document.getElementById('view-planning-map');
        var linksEl = document.getElementById('view-planning-map-links');
        if (!container || !mapEl || !linksEl) return;

        if (!address && urls.length === 0) {
            container.style.display = 'none';
            mapEl.style.display = 'none';
            linksEl.style.display = 'none';
            linksEl.innerHTML = '';
            return;
        }

        container.style.display = '';
        linksEl.innerHTML = '';

        var linkTargets = urls.length > 0 ? urls : makeDefaultMapUrls(address);
        if (linkTargets.length > 0) {
            linkTargets.forEach(function(url) {
                var linkBtn = document.createElement('a');
                linkBtn.className = 'btn-secondary planning-map-link';
                linkBtn.href = url;
                linkBtn.target = '_blank';
                linkBtn.rel = 'noopener noreferrer';
                linkBtn.innerHTML = '<i class="' + getMapLinkIcon(url) + '"></i>' + getMapLinkLabel(url);
                linksEl.appendChild(linkBtn);
            });
            linksEl.style.display = 'flex';
            linksEl.style.flexWrap = 'wrap';
        } else {
            linksEl.style.display = 'none';
        }

        if (address && typeof window.L !== 'undefined') {
            mapEl.style.display = 'block';
            mapEl.textContent = 'Loading map…';
            if (viewPlanningMapInstance) {
                try { viewPlanningMapInstance.remove(); } catch (ignore) {}
                viewPlanningMapInstance = null;
            }
            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address))
                .then(function(response){ return response.json(); })
                .then(function(data) {
                    if (!data || !data.length) {
                        mapEl.textContent = 'Map unavailable for this address.';
                        return;
                    }
                    mapEl.textContent = '';
                    var lat = parseFloat(data[0].lat);
                    var lon = parseFloat(data[0].lon);
                    if (isNaN(lat) || isNaN(lon)) {
                        mapEl.textContent = 'Map unavailable for this address.';
                        return;
                    }
                    mapEl.innerHTML = '';
                    viewPlanningMapInstance = window.L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lon], 15);
                    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(viewPlanningMapInstance);
                    window.L.marker([lat, lon]).addTo(viewPlanningMapInstance);
                })
                .catch(function(err) {
                    mapEl.textContent = 'Unable to load map.';
                    console.warn('Planning map geocode failed', err);
                });
        } else {
            if (viewPlanningMapInstance) {
                try { viewPlanningMapInstance.remove(); } catch (ignore) {}
                viewPlanningMapInstance = null;
            }
            mapEl.style.display = 'none';
            mapEl.innerHTML = '';
        }
    }

    window.viewPlanning = function(index) {
        var planning = plannings[index];
        if (!planning) return;
        var viewModal = document.querySelector('.view-planning-modal');
        var viewTitle = document.getElementById('view-planning-title');
        var viewDate = document.getElementById('view-planning-date');
        var viewTime = document.getElementById('view-planning-time');
        var viewDesc = document.getElementById('view-planning-description');
        if (viewTitle) viewTitle.textContent = planning.title || 'Slot Details';
        if (viewDate) viewDate.textContent = formatDisplayDate(planning.date || '') || '';
        if (viewTime) viewTime.textContent = formatDisplayTime(planning.start_time) + ' - ' + formatDisplayTime(planning.end_time);
        if (viewDesc) viewDesc.innerHTML = makeLinksClickable(planning.description || 'No description available.');

        var address = extractAddress(planning.description || '');
        var urls = extractMapUrls(planning.description || '');
        renderPlanningMap(address, urls);

        if (viewModal) viewModal.classList.add('active');
    };

    function setFormReadOnly(readOnly) {
        var els = form.querySelectorAll('input, select, textarea');
        els.forEach(function(i){ i.disabled = !!readOnly; });
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.style.display = readOnly ? 'none' : '';
    }

    function getDayName(dayIndex) {
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        return days[dayIndex] || 'Unknown';
    }

    function savePlanning(data) {
        var isEdit = !!data.id;

        if (document.getElementById('planning-date')) {
            var dateVal = document.getElementById('planning-date').value;
            if (dateVal) {
                var parseTime = function(t) {
                    if (!t) return null;
                    var parts = t.split(':');
                    if (parts.length === 2) return {hh: parseInt(parts[0]), mm: parseInt(parts[1])};
                    if (parts.length === 3) return {hh: parseInt(parts[0]), mm: parseInt(parts[1]), ss: parseInt(parts[2])};
                    return null;
                };

                var sT = parseTime(data.start_time || '');
                var eT = parseTime(data.end_time || '');

                var makeDate = function(dateStr, tObj) {
                    if (!tObj) return null;
                    return new Date(parseInt(dateStr.split('-')[0]), parseInt(dateStr.split('-')[1]) - 1, parseInt(dateStr.split('-')[2]), tObj.hh || 0, tObj.mm || 0, tObj.ss || 0);
                };

                var startDt = makeDate(dateVal, sT) || new Date();
                var endDt = makeDate(dateVal, eT) || new Date(startDt.getTime() + 3600*1000);

                function two(n){return String(n).padStart(2,'0');}
                var dateStr = dateVal;
                    var startStr = dateStr + ' ' + two(startDt.getHours()) + ':' + (sT && sT.mm < 10 ? '0' + sT.mm : sT.mm) + ':00';
                    var endStr = dateStr + ' ' + two(endDt.getHours()) + ':' + (eT && eT.mm < 10 ? '0' + eT.mm : eT.mm) + ':00';

                data.start_time = startStr;
                data.end_time = endStr;
                data.date = dateStr;
            }
        }

        var url = './planning-api' + (isEdit && data.id ? '?id=' + encodeURIComponent(data.id) : '');
        var method = isEdit ? 'PATCH' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) {
            return response.text().then(function(text) {
                if (!response.ok) {
                    console.error('Save planning non-OK response text:', text);
                    var parsed = null;
                    try { parsed = JSON.parse(text); } catch (e) { }
                    if (parsed && parsed.error) {
                            var msg = parsed.error;
                            if (parsed.body) msg += '\n' + parsed.body;
                            showModalError(msg);
                        } else {
                            var snippet = text.replace(/<[^>]*>/g, '').trim();
                            if (snippet.length > 400) snippet = snippet.substring(0,400) + '...';
                            showModalError(snippet || 'Unexpected response');
                        }
                    throw new Error('Failed to save planning: ' + text);
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse success JSON:', e, text);
                    throw e;
                }
            });
        })
        .then(function(result) {
            loadPlannings();
            closeModal();
        })
        .catch(function(error) {
            console.error('Error saving planning:', error);
        });
    }

    function showModalError(msg) {
        var el = document.getElementById('planning-error');
        if (!el) return showToast(msg);
        el.innerText = msg;
        el.style.display = 'block';
    }

    function hideModalError() {
        var el = document.getElementById('planning-error');
        if (!el) return;
        el.innerText = '';
        el.style.display = 'none';
    }


    window.editPlanning = function(index) {
        var planning = plannings[index];
        if (!planning) return;

        var dateEl = document.getElementById('edit-planning-date');
        var startHourEl = document.getElementById('edit-planning-start-hour');
        var startMinEl = document.getElementById('edit-planning-start-minute');
        var startAmpmEl = document.getElementById('edit-planning-start-ampm');
        var endHourEl = document.getElementById('edit-planning-end-hour');
        var endMinEl = document.getElementById('edit-planning-end-minute');
        var endAmpmEl = document.getElementById('edit-planning-end-ampm');
        var titleEl = document.getElementById('edit-planning-title');
        var descEl = document.getElementById('edit-planning-description');

        if (dateEl) {
            if (planning.date) {
                var d = new Date(planning.date + 'T00:00:00');
                if (!isNaN(d.getTime())) dateEl.value = formatLocalISO(d);
                else dateEl.value = planning.date;
            } else {
                dateEl.value = '';
            }
        }

        function setTime(prefixHourEl, prefixMinEl, prefixAmpmEl, timeStr) {
            if (!prefixHourEl) return;
            if (!timeStr) {
                prefixHourEl.value = '12';
                if (prefixMinEl) prefixMinEl.value = '00';
                if (prefixAmpmEl) prefixAmpmEl.value = 'AM';
                return;
            }
            var stFull = (timeStr.split(' ')[1] || timeStr);
            var parts = stFull.split(':');
            var hh = parseInt(parts[0], 10);
            var mm = parts.length >= 2 ? parts[1] : '00';
            var ampm = hh >= 12 ? 'PM' : 'AM';
            var h12 = hh % 12; if (h12 === 0) h12 = 12;
            prefixHourEl.value = String(h12);
            if (prefixMinEl) prefixMinEl.value = String(mm).padStart(2, '0');
            if (prefixAmpmEl) prefixAmpmEl.value = ampm;
        }

        setTime(startHourEl, startMinEl, startAmpmEl, planning.start_time);
        setTime(endHourEl, endMinEl, endAmpmEl, planning.end_time);
        if (titleEl) titleEl.value = planning.title || '';
        if (descEl) descEl.value = planning.description || '';

        var editModal = document.querySelector('.edit-planning-modal');
        if (editModal) {
            editModal.classList.add('active');
            try { editModal.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(e){}
        } else {
            console.warn('[planning] edit modal not found, falling back to add modal');
            var aDate = document.getElementById('planning-date'); if (aDate && dateEl) aDate.value = dateEl.value;
            var aStartH = document.getElementById('planning-start-hour'); if (aStartH && startHourEl) aStartH.value = startHourEl.value;
            var aStartM = document.getElementById('planning-start-minute'); if (aStartM && startMinEl) aStartM.value = startMinEl.value;
            var aStartA = document.getElementById('planning-start-ampm'); if (aStartA && startAmpmEl) aStartA.value = startAmpmEl.value;
            var aEndH = document.getElementById('planning-end-hour'); if (aEndH && endHourEl) aEndH.value = endHourEl.value;
            var aEndM = document.getElementById('planning-end-minute'); if (aEndM && endMinEl) aEndM.value = endMinEl.value;
            var aEndA = document.getElementById('planning-end-ampm'); if (aEndA && endAmpmEl) aEndA.value = endAmpmEl.value;
            var aTitle = document.getElementById('planning-title'); if (aTitle && titleEl) aTitle.value = titleEl.value;
            var aDesc = document.getElementById('planning-description'); if (aDesc && descEl) aDesc.value = descEl.value;
            var addModal = document.getElementById('planning-modal'); if (addModal) addModal.classList.add('active');
        }
        pendingEditIndex = index;
    };

    window.deletePlanning = function(index) {
        pendingDeleteIndex = index;
        var delModal = document.querySelector('.delete-confirmation-modal');
        if (delModal) delModal.classList.add('active');
    };

    (function() {
        var delModal = document.querySelector('.delete-confirmation-modal');
        var closeBtn = document.getElementById('close-delete-confirmation');
        var cancelBtn = document.getElementById('cancel-delete-btn');
        var confirmBtn = document.getElementById('confirm-delete-btn');

        function hideDelModal() {
            if (delModal) delModal.classList.remove('active');
            pendingDeleteIndex = null;
        }

        if (closeBtn) closeBtn.addEventListener('click', hideDelModal);
        if (cancelBtn) cancelBtn.addEventListener('click', hideDelModal);
        if (delModal) {
            delModal.addEventListener('click', function(e) {
                if (e.target === delModal) hideDelModal();
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                var idx = pendingDeleteIndex;
                if (idx === null || idx === undefined) return hideDelModal();
                var planning = plannings[idx];
                hideDelModal();
                if (!planning) return;
                if (planning.id) {
                    var delUrl = './planning-api?id=' + encodeURIComponent(planning.id);
                    fetch(delUrl, {
                        method: 'DELETE',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(function(response) {
                        if (response.ok) {
                            loadPlannings();
                        } else {
                            showToast('Failed to delete planning slot');
                        }
                    })
                    .catch(function(err) {
                        console.error('Error deleting planning:', err);
                        showToast('Failed to delete planning slot');
                    });
                } else {
                    plannings.splice(idx, 1);
                    renderPlannings();
                }
            });
        }

    timeSlots.forEach(function(slot) {
        slot.addEventListener('click', function() {
            var day  = this.getAttribute('data-day');
            var hour = this.getAttribute('data-hour');
            console.log('planning slot clicked', day, hour);

            delete form.dataset.editingIndex;
            openModal();

            var dateEl = document.getElementById('planning-date');
            if (dateEl) {
                var d = addDays(currentWeekStart, parseInt(day, 10));
                dateEl.value = formatLocalISO(d);
            }

            var startHour = document.getElementById('planning-start-hour');
            var startMin  = document.getElementById('planning-start-minute');
            var startAmpm = document.getElementById('planning-start-ampm');
            var endHour   = document.getElementById('planning-end-hour');
            var endMin    = document.getElementById('planning-end-minute');
            var endAmpm   = document.getElementById('planning-end-ampm');

            if (startHour) {
                var h    = parseInt(hour, 10);
                var ampm = h >= 12 ? 'PM' : 'AM';
                var disp = h % 12;
                if (disp === 0) disp = 12;
                startHour.value = String(disp);
                if (startMin)  startMin.value  = '00';
                if (startAmpm) startAmpm.value = ampm;
            }
            if (endHour) {
                var eh    = parseInt(hour, 10) + 1;
                if (eh >= 24) eh -= 24;
                var eampm = eh >= 12 ? 'PM' : 'AM';
                var edisp = eh % 12;
                if (edisp === 0) edisp = 12;
                endHour.value = String(edisp);
                if (endMin)  endMin.value  = '00';
                if (endAmpm) endAmpm.value = eampm;
            }
        });
    });

    })();
    modalButton.addEventListener('click', openModal);
    closeButton.addEventListener('click', closeModal);
    cancelButton.addEventListener('click', closeModal);

    var editModal = document.querySelector('.edit-planning-modal');
    var closeEditBtn = document.getElementById('close-edit-planning-modal');
    var cancelEditBtn = document.getElementById('cancel-edit-planning');
    var editForm = document.getElementById('edit-planning-form');
    if (closeEditBtn) closeEditBtn.addEventListener('click', function(){ if (editModal) editModal.classList.remove('active'); });
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', function(){ if (editModal) editModal.classList.remove('active'); });
    if (editModal) editModal.addEventListener('click', function(e){ if (e.target === editModal) editModal.classList.remove('active'); });

        if (editForm) {
        editForm.addEventListener('submit', function(e){
            e.preventDefault();
            var idx = pendingEditIndex;
            if (idx === undefined || idx === null) idx = null;
            var planning = (idx !== null) ? plannings[idx] : null;
            var dateVal = document.getElementById('edit-planning-date') ? document.getElementById('edit-planning-date').value : '';
            var readTime = function(hrId, minId, ampmId) {
                var hr = document.getElementById(hrId), mn = document.getElementById(minId), ap = document.getElementById(ampmId);
                if (!hr || !mn || !ap) return '';
                var h = parseInt(hr.value,10); var m = String(mn.value).padStart(2,'0'); var am = ap.value||'AM';
                if (am === 'PM' && h < 12) h += 12; if (am === 'AM' && h === 12) h = 0;
                return String(h).padStart(2,'0') + ':' + m;
            };
            var data = {};
            if (planning && planning.id) data.id = planning.id;
            data.date = dateVal;
            data.start_time = readTime('edit-planning-start-hour','edit-planning-start-minute','edit-planning-start-ampm');
            data.end_time = readTime('edit-planning-end-hour','edit-planning-end-minute','edit-planning-end-ampm');
            data.title = document.getElementById('edit-planning-title') ? document.getElementById('edit-planning-title').value : '';
            data.description = document.getElementById('edit-planning-description') ? document.getElementById('edit-planning-description').value : '';
            savePlanning(data);
            if (editModal) editModal.classList.remove('active');
            pendingEditIndex = null;
        });
    }

    if (prevWeekBtn && nextWeekBtn) {
        prevWeekBtn.addEventListener('click', goToPrevWeek);
        nextWeekBtn.addEventListener('click', goToNextWeek);
    }


    if (todayBtn) {
        todayBtn.addEventListener('click', function() {
            currentWeekStart = getMonday(new Date());
            updateDateRangeDisplay();
            loadPlannings();
        });
    }

    var viewModal = document.querySelector('.view-planning-modal');
    var closeViewBtn = document.getElementById('close-view-planning-modal');
    if (closeViewBtn) {
        closeViewBtn.addEventListener('click', function(){ if (viewModal) viewModal.classList.remove('active'); });
    }
    if (viewModal) {
        viewModal.addEventListener('click', function(e){ if (e.target === viewModal) viewModal.classList.remove('active'); });
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var dateEl = document.getElementById('planning-date');
        var startHourEl = document.getElementById('planning-start-hour');
        var startMinEl = document.getElementById('planning-start-minute');
        var startAmpmEl = document.getElementById('planning-start-ampm');
        var endHourEl = document.getElementById('planning-end-hour');
        var endMinEl = document.getElementById('planning-end-minute');
        var endAmpmEl = document.getElementById('planning-end-ampm');
        var titleEl = document.getElementById('planning-title');
        var typeEl = document.getElementById('planning-type');
        var descEl = document.getElementById('planning-description');

        function readTimeFromSelects(hrEl, minEl, ampmEl) {
            if (!hrEl || !minEl || !ampmEl) return '';
            var h = parseInt(hrEl.value, 10);
            var m = String(minEl.value).padStart(2, '0');
            var ampm = ampmEl.value || 'AM';
            if (ampm === 'PM' && h < 12) h += 12;
            if (ampm === 'AM' && h === 12) h = 0;
            return String(h).padStart(2, '0') + ':' + m;
        }

        var formData = {
            date: dateEl ? dateEl.value : '',
            start_time: readTimeFromSelects(startHourEl, startMinEl, startAmpmEl),
            end_time: readTimeFromSelects(endHourEl, endMinEl, endAmpmEl),
            title: titleEl ? titleEl.value : '',
            type: typeEl ? typeEl.value : '',
            description: descEl ? descEl.value : ''
        };

        var editingIndex = form.dataset.editingIndex;
        if (editingIndex !== undefined) {
            formData.id = plannings[editingIndex].id;
        }

        savePlanning(formData);
    });

    var calendarToggleBtn = document.getElementById('calendar-toggle-btn');
    var calendarPickerPopover = document.getElementById('calendar-picker-popover');
    var calendarMonthYear = document.getElementById('calendar-month-year');
    var calendarDays = document.getElementById('calendar-days');
    var calendarPrevMonth = document.getElementById('calendar-prev-month');
    var calendarNextMonth = document.getElementById('calendar-next-month');
    
    var calendarCurrentDate = new Date();

    function renderCalendar() {
        calendarMonthYear.textContent = calendarCurrentDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        
        calendarDays.innerHTML = '';
        
        var year = calendarCurrentDate.getFullYear();
        var month = calendarCurrentDate.getMonth();
        
        var firstDay = new Date(year, month, 1);

        var lastDay = new Date(year, month + 1, 0);
        
        var startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - ((startDate.getDay() + 6) % 7));
        
        var date = new Date(startDate);
        var weekCount = 0;
        
        while (date <= lastDay || weekCount < 6) {
            for (var i = 0; i < 7; i++) {
                var dayEl = document.createElement('div');
                dayEl.className = 'calendar-day';
                dayEl.textContent = date.getDate();
                
                var isOtherMonth = date.getMonth() !== month;
                var isToday = date.toDateString() === new Date().toDateString();
                var isInCurrentWeek = date >= currentWeekStart && date <= addDays(currentWeekStart, 6);
                var isWeekStart = date.getDay() === 1;
                var isWeekEnd = date.getDay() === 0;
                
                if (isOtherMonth) {
                    dayEl.classList.add('other-month');
                }
                if (isToday) {
                    dayEl.classList.add('today');
                }
                if (isInCurrentWeek && !isOtherMonth) {
                    if (isWeekStart) {
                        dayEl.classList.add('week-start');
                    } else if (isWeekEnd) {
                        dayEl.classList.add('week-end');
                    } else {
                        dayEl.classList.add('in-week');
                    }
                }
                
                if (!isOtherMonth) {
                    dayEl.addEventListener('click', (function(d) {
                        return function() {
                            currentWeekStart = getMonday(new Date(d));
                            updateDateRangeDisplay();
                            loadPlannings();
                            
                            calendarPickerPopover.classList.remove('active');
                            renderCalendar();
                        };
                    })(new Date(date)));
                }
                
                calendarDays.appendChild(dayEl);
                date.setDate(date.getDate() + 1);
            }
            weekCount++;
        }
    }

    if (calendarToggleBtn) {
        calendarToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            calendarPickerPopover.classList.toggle('active');
            if (calendarPickerPopover.classList.contains('active')) {
                renderCalendar();
                calendarCurrentDate = new Date(currentWeekStart);
                renderCalendar();
            }
        });
    }

    if (calendarPrevMonth) {
        calendarPrevMonth.addEventListener('click', function() {
            calendarCurrentDate.setMonth(calendarCurrentDate.getMonth() - 1);
            renderCalendar();
        });
    }

    if (calendarNextMonth) {
        calendarNextMonth.addEventListener('click', function() {
            calendarCurrentDate.setMonth(calendarCurrentDate.getMonth() + 1);
            renderCalendar();
        });
    }

    document.addEventListener('click', function(e) {
        if (calendarPickerPopover && calendarToggleBtn) {
            if (!calendarPickerPopover.contains(e.target) && !calendarToggleBtn.contains(e.target)) {
                calendarPickerPopover.classList.remove('active');
            }
        }
    });

    updateDateRangeDisplay();
    loadPlannings();
})();
