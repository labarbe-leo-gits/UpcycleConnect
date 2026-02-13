(function() {
    'use strict';

    var modal = document.querySelector('.add-modal');
    var openButton = document.getElementById('add-offer');
    var closeButton = document.getElementById('close-add-modal');
    var fileInput = document.getElementById('offer-pictures');
    var preview = document.getElementById('pictures-preview');
    var dropZone = document.getElementById('pictures-drop-zone');
    var focusableSelector = 'button, [href], input, textarea, select, [tabindex]:not([tabindex="-1"])';
    var lastFocused = null;
    var selectedFiles = [];

    if (!modal || !openButton || !closeButton) {
        return;
    }

    modal.setAttribute('aria-hidden', 'true');

    function openModal() {
        lastFocused = document.activeElement;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
        modal.setAttribute('aria-hidden', 'false');

        var focusTarget = modal.querySelector('#offer-title') || modal.querySelector(focusableSelector);
        if (focusTarget) {
            focusTarget.focus();
        }
    }

    function closeModal() {
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
        modal.setAttribute('aria-hidden', 'true');

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function trapFocus(event) {
        if (!modal.classList.contains('is-open')) {
            return;
        }

        var focusable = Array.prototype.slice.call(modal.querySelectorAll(focusableSelector))
            .filter(function(element) {
                return !element.hasAttribute('disabled');
            });

        if (focusable.length === 0) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    openButton.addEventListener('click', openModal);
    closeButton.addEventListener('click', closeModal);

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (!modal.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }

        if (event.key === 'Tab') {
            trapFocus(event);
        }
    });

    function syncFileInput() {
        if (!fileInput) {
            return;
        }

        if (!window.DataTransfer) {
            fileInput.value = '';
            return;
        }

        var dataTransfer = new DataTransfer();
        selectedFiles.forEach(function(file) {
            dataTransfer.items.add(file);
        });
        fileInput.files = dataTransfer.files;
    }

    function setFiles(files) {
        selectedFiles = files.filter(function(file) {
            return file && file.type && file.type.indexOf('image/') === 0;
        });
        syncFileInput();
        renderPreview();
    }

    function renderPreview() {
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        if (!selectedFiles.length) {
            return;
        }

        selectedFiles.forEach(function(file, index) {
            var url = URL.createObjectURL(file);
            var wrapper = document.createElement('div');
            wrapper.className = 'picture-thumb';

            var img = document.createElement('img');
            img.src = url;
            img.alt = file.name || 'Selected image';
            img.onload = function() {
                URL.revokeObjectURL(url);
            };

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'picture-remove';
            removeButton.setAttribute('aria-label', 'Remove image');
            removeButton.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeButton.addEventListener('click', function() {
                selectedFiles.splice(index, 1);
                syncFileInput();
                renderPreview();
            });

            wrapper.appendChild(img);
            wrapper.appendChild(removeButton);
            preview.appendChild(wrapper);
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            var files = Array.prototype.slice.call(fileInput.files || []);
            setFiles(files);
        });
    }

    if (dropZone && fileInput) {
        var browseButton = dropZone.querySelector('.drop-zone-button');
        if (browseButton) {
            browseButton.addEventListener('click', function(event) {
                event.preventDefault();
                fileInput.click();
            });
        }

        dropZone.addEventListener('click', function(event) {
            if (event.target === fileInput || event.target.closest('.picture-remove') || event.target.closest('.drop-zone-button')) {
                return;
            }
            fileInput.click();
        });

        dropZone.addEventListener('dragenter', function(event) {
            event.preventDefault();
            dropZone.classList.add('is-dragover');
        });

        dropZone.addEventListener('dragover', function(event) {
            event.preventDefault();
            dropZone.classList.add('is-dragover');
        });

        dropZone.addEventListener('dragleave', function(event) {
            if (event.relatedTarget && dropZone.contains(event.relatedTarget)) {
                return;
            }
            dropZone.classList.remove('is-dragover');
        });

        dropZone.addEventListener('drop', function(event) {
            event.preventDefault();
            dropZone.classList.remove('is-dragover');

            if (!event.dataTransfer || !event.dataTransfer.files) {
                return;
            }

            var droppedFiles = Array.prototype.slice.call(event.dataTransfer.files);
            if (!droppedFiles.length) {
                return;
            }

            setFiles(droppedFiles);
        });
    }
})();
