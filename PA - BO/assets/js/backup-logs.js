(function(){
    var files = window.backupFiles || [];
    var selectedDownloadFile = null;

    function formatDate(ts){
        var d = new Date(ts*1000);
        return d.toLocaleString();
    }

    function escapeHtml(str){return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

    var currentLimit = 10;
    var loadedItems = 0;

    function showSkeleton(count) {
        var list = document.getElementById('backup-list');
        list.innerHTML = '';
        for (var i = 0; i < count; i++) {
            var skeleton = document.createElement('div');
            skeleton.className = 'skeleton-card';
            skeleton.innerHTML = '<div class="skeleton-line skeleton-title"></div>'
                + '<div class="skeleton-line skeleton-meta" style="width:45%;"></div>'
                + '<div class="skeleton-line skeleton-meta" style="width:30%;"></div>'
                + '<div class="skeleton-line skeleton-actions" style="margin-top:10px;"></div>';
            list.appendChild(skeleton);
        }
    }

    function renderTable(){
        var list = document.getElementById('backup-list');
        list.innerHTML = '';

        var s = (document.getElementById('backup-file-search').value||'').toLowerCase();
        var min = parseFloat(document.getElementById('backup-min-size').value||'0');
        var max = parseFloat(document.getElementById('backup-max-size').value||'0');

        var filtered = files.filter(function(f){
            var name = f.file.toLowerCase();
            if (s && name.indexOf(s)===-1) return false;
            var sizeKb = f.size/1024;
            if (!isNaN(min) && min>0 && sizeKb<min) return false;
            if (!isNaN(max) && max>0 && sizeKb>max) return false;
            return true;
        });

        var perPage = parseInt(document.getElementById('backup-per-page').value, 10) || 10;
        currentLimit = perPage;

        showSkeleton(perPage);

        setTimeout(function() {
            list.innerHTML = '';
            var toShow = Math.min(filtered.length, loadedItems + perPage);

            for (var i=0; i<toShow; i++) {
                var f = filtered[i];
                var sizeKb = f.size/1024;
                var card = document.createElement('div');
                card.className = 'backup-card';
                card.innerHTML = '<div class="backup-card-title">'+escapeHtml(f.file)+'</div>'
                    +'<div class="backup-card-meta"><span><strong>'+sizeKb.toFixed(2)+'</strong> KB</span><span>'+formatDate(f.mtime)+'</span></div>'
                    +'<div class="backup-card-actions">'
                    +'<button class="btn-secondary preview-btn" data-file="'+escapeHtml(f.file)+'">Preview</button>'
                    +'<button class="btn-primary download-btn" data-file="'+escapeHtml(f.file)+'">Download</button>'
                    +'</div>';
                list.appendChild(card);
            }

            loadedItems = toShow;
            document.getElementById('backup-load-more').style.display = (loadedItems < filtered.length ? 'inline-block' : 'none');
            attachActions();
        }, 250);
    }

    function attachActions(){
        document.querySelectorAll('.preview-btn').forEach(function(btn){
            btn.onclick = function(){
                var file = this.dataset.file;
                fetch('backup.php?ajax=1&action=preview&file='+encodeURIComponent(file))
                    .then(r => r.json())
                    .then(d => {
                        if (d.error) { showInfoMessage(d.error); return; }
                        var content = d.content || '';
                        document.getElementById('preview-content').innerHTML = renderLogPreviewTable(content);
                        showModal('preview-modal');
                    })
                    .catch(e => {
                        showInfoMessage('Unable to parse log preview.');
                        console.error(e);
                    });
            };
        });
        document.querySelectorAll('.download-btn').forEach(function(btn){
            btn.onclick = function(){
                selectedDownloadFile = this.dataset.file;
                document.getElementById('download-confirm-message').textContent = 'Download "'+selectedDownloadFile+'" ?';
                showModal('download-confirm-modal');
            };
        });
    }

    function isPrivateIp(ip) {
        if (!ip || typeof ip !== 'string') return false;
        if (ip === 'localhost' || ip === '127.0.0.1' || ip === '::1') return true;
        var parts = ip.split('.').map(Number);
        if (parts.length === 4 && parts.every(function(p){return Number.isFinite(p) && p >= 0 && p < 256;})) {
            if (parts[0] === 10) return true;
            if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return true;
            if (parts[0] === 192 && parts[1] === 168) return true;
            if (parts[0] === 169 && parts[1] === 254) return true;
        }
        return false;
    }

    function formatLogTimestamp(ts) {
        if (!ts || typeof ts !== 'string') return '';
        var norm = ts.trim().replace(' ', 'T');
        var date = new Date(norm);
        if (isNaN(date.getTime())) {
            // fallback for older browsers or non-standard format
            var match = ts.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})$/);
            if (match) {
                date = new Date(match[1] + 'T' + match[2]);
                if (!isNaN(date.getTime())) {
                    return date.toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
            }
            return ts;
        }
        return date.toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function renderLogPreviewTable(content) {
        var rows = (content || '').split(/\r?\n/).filter(function(line){ return line.trim() !== ''; });
        if (rows.length === 0) {
            return '<p style="color:#6b7280;">No entries in file.</p>';
        }

        var html = '<table class="preview-log-table"><thead><tr>' +
            '<th>Timestamp</th><th>Level</th><th>IP</th><th>Message</th>' +
            '</tr></thead><tbody>';
        var re = /^\[(.+?)\]\s+\[(.+?)\]\s+\[(.+?)\]\s+(.*)$/;

        for (var i = 0; i < rows.length; i++) {
            var line = rows[i];
            var m = re.exec(line);
            if (m) {
                var ip = m[3];
                var ipCell = escapeHtml(ip);
                if (!isPrivateIp(ip)) {
                    ipCell = '<a class="preview-log-ip-chip" target="_blank" rel="noreferrer noopener" href="https://ipinfo.io/' + encodeURIComponent(ip) + '">' + ipCell + '</a>';
                } else {
                    ipCell = '<span class="preview-log-ip-chip">' + ipCell + '</span>';
                }
                html += '<tr><td>' + escapeHtml(formatLogTimestamp(m[1])) + '</td><td>' + escapeHtml(m[2]) + '</td><td>' + ipCell + '</td><td>' + escapeHtml(m[4]) + '</td></tr>';
            } else {
                html += '<tr><td colspan="4">' + escapeHtml(line) + '</td></tr>';
            }
        }

        html += '</tbody></table>';
        return html;
    }

    function showModal(id){var m=document.getElementById(id);if(m){m.classList.add('is-open');m.setAttribute('aria-hidden','false');}}
    function hideModal(id){var m=document.getElementById(id);if(m){m.classList.remove('is-open');m.setAttribute('aria-hidden','true');}}

    document.getElementById('backup-file-search').addEventListener('input', function(){loadedItems=0; renderTable();});
    document.getElementById('backup-min-size').addEventListener('input', function(){loadedItems=0; renderTable();});
    document.getElementById('backup-max-size').addEventListener('input', function(){loadedItems=0; renderTable();});
    document.getElementById('backup-per-page').addEventListener('change', function(){loadedItems=0; renderTable();});

    document.getElementById('backup-reset-filters').addEventListener('click', function(){
        document.getElementById('backup-file-search').value='';
        document.getElementById('backup-min-size').value='';
        document.getElementById('backup-max-size').value='';
        document.getElementById('backup-per-page').value='10';
        loadedItems = 0;
        renderTable();
    });

    document.getElementById('backup-load-more').addEventListener('click', function(){renderTable();});

    document.getElementById('preview-close').addEventListener('click',()=>hideModal('preview-modal'));
    document.getElementById('download-confirm-close').addEventListener('click',()=>hideModal('download-confirm-modal'));
    document.getElementById('download-cancel-btn').addEventListener('click',()=>hideModal('download-confirm-modal'));
    document.getElementById('download-confirm-btn').addEventListener('click', function(){
        if(!selectedDownloadFile){showInfoMessage('No file selected');return;}
        window.location.href = 'backup.php?action=download&file='+encodeURIComponent(selectedDownloadFile);
        hideModal('download-confirm-modal');
    });

    renderTable();
})();