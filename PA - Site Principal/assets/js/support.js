(function () {
    const area  = document.getElementById('description');
    const count = document.getElementById('desc-count');
    if (!area || !count) return;
    const update = () => count.textContent = area.value.length;
    area.addEventListener('input', update);
    update();
})();

(function () {
    const input       = document.getElementById('attachment');
    const dropZone    = document.getElementById('file-drop-zone');
    const previewList = document.getElementById('file-preview-list');

    const EXT_ICONS = {
        pdf:  { icon: 'fa-file-pdf',   color: '#ef4444' },
        doc:  { icon: 'fa-file-word',  color: '#3b82f6' },
        docx: { icon: 'fa-file-word',  color: '#3b82f6' },
        txt:  { icon: 'fa-file-lines', color: '#6b7280' },
    };

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function clearAttachment() {
        input.value = '';
        previewList.innerHTML = '';
        if (dropZone) dropZone.style.display = '';
    }

    if (input && dropZone && previewList) {
        input.addEventListener('change', function () {
            previewList.innerHTML = '';
            if (!this.files || this.files.length === 0) {
                dropZone.style.display = '';
                return;
            }

            const file    = this.files[0];
            const ext     = file.name.split('.').pop().toLowerCase();
            const isImage = file.type.startsWith('image/');

            dropZone.style.display = 'none';

            const card = document.createElement('div');
            card.className = 'file-card';

            const thumb = document.createElement('div');
            thumb.className = 'file-card-thumb';

            if (isImage) {
                const img = document.createElement('img');
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = e => { img.src = e.target.result; };
                reader.readAsDataURL(file);
                thumb.appendChild(img);
            } else {
                const extInfo = EXT_ICONS[ext] || { icon: 'fa-file', color: '#9ca3af' };
                const icon = document.createElement('i');
                icon.className = 'fa-solid ' + extInfo.icon;
                icon.style.color = extInfo.color;
                thumb.appendChild(icon);
            }

            const info = document.createElement('div');
            info.className = 'file-card-info';

            const nameEl = document.createElement('span');
            nameEl.className = 'file-card-name';
            nameEl.textContent = file.name;

            const sizeEl = document.createElement('span');
            sizeEl.className = 'file-card-size';
            sizeEl.textContent = formatSize(file.size);

            info.appendChild(nameEl);
            info.appendChild(sizeEl);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'file-card-remove';
            removeBtn.setAttribute('aria-label', 'Remove attachment');
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.addEventListener('click', clearAttachment);

            card.appendChild(thumb);
            card.appendChild(info);
            card.appendChild(removeBtn);
            previewList.appendChild(card);
        });
    }

    document.querySelectorAll('.urgency-card input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.urgency-card').forEach(c => c.classList.remove('is-selected'));
            if (this.checked) {
                this.closest('.urgency-card').classList.add('is-selected');
            }
        });
    });
})();