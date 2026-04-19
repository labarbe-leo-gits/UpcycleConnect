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
    loadMaterials();
    loadCategories();

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

    function loadMaterials() {
        var select = document.getElementById('offer-material');
        if (!select) return;
        fetch('facteurs-api', {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(function(res){ return res.json(); })
            .then(function(list){
                if (!Array.isArray(list)) return;
                list.forEach(function(f){
                    var opt = document.createElement('option');
                    opt.value = f.id || '';
                    opt.textContent = f.nom || '';
                    opt.dataset.name = f.nom || '';
                    select.insertBefore(opt, select.querySelector('option[value="other"]'));
                });
            });
    }

    function loadCategories() {
        var select = document.getElementById('offer-category');
        if (!select) return;
        fetch('categories-list-api.php?limit=100', {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(function(res){ return res.json(); })
            .then(function(resp){
                var list = Array.isArray(resp.items) ? resp.items : [];
                list.forEach(function(cat){
                    var opt = document.createElement('option');
                    opt.value = cat.id || '';
                    opt.textContent = cat.name || '';
                    select.appendChild(opt);
                });
            });
    }

    var form = document.getElementById('add-offer-form');
    if (form) {

        var UPCYCLE_RATE  = 0.08;
        var STRIPE_RATE   = 0.029;
        var STRIPE_FIXED  = 0.30;

        function calcTTC(ht) {
            if (ht <= 0) return 0;
            return Math.round(((ht * (1 + UPCYCLE_RATE)) + STRIPE_FIXED) / (1 - STRIPE_RATE) * 100) / 100;
        }

        function updateTTCPreview() {
            var priceInput = document.getElementById('offer-price');
            var previewBox = document.getElementById('offer-ttc-preview');
            if (!priceInput || !previewBox) return;

            var ht = parseFloat(priceInput.value) || 0;
            if (ht > 0) {
                var commission = Math.round(ht * UPCYCLE_RATE * 100) / 100;
                var ttc        = calcTTC(ht);
                var stripeFee  = Math.round((ttc - ht * (1 + UPCYCLE_RATE)) * 100) / 100;
                var refund     = Math.round(ht * (1 + UPCYCLE_RATE) * 100) / 100;

                document.getElementById('ttc-ht').textContent         = '€ ' + ht.toFixed(2);
                document.getElementById('ttc-commission').textContent  = '€ ' + commission.toFixed(2);
                document.getElementById('ttc-stripe').textContent      = '€ ' + stripeFee.toFixed(2);
                document.getElementById('ttc-total').innerHTML         = '<strong>€ ' + ttc.toFixed(2) + '</strong>';
                document.getElementById('ttc-refund').textContent      = refund.toFixed(2);
                previewBox.style.display = 'block';
            } else {
                previewBox.style.display = 'none';
            }
        }

        var priceInputEl = document.getElementById('offer-price');
        if (priceInputEl) {
            priceInputEl.addEventListener('input', updateTTCPreview);
            priceInputEl.addEventListener('change', updateTTCPreview);
            priceInputEl.addEventListener('input', validateMaximumPrice);
            priceInputEl.addEventListener('change', validateMaximumPrice);
        }
        var materialSelect = document.getElementById('offer-material');
        var customInput = document.getElementById('offer-material-custom');
        var estimationGroup = document.getElementById('offer-estimation-group');
            var materialFactors    = [];
            var geminiCO2Factor    = null;
            var _geminiTimer       = null;
            var _geminiPending     = false;

            function setSubmitBlocked(blocked) {
                _geminiPending = blocked;
                var btn = form.querySelector('button[type="submit"]');
                if (!btn) return;
                btn.disabled = blocked;
                btn.style.opacity = blocked ? '0.5' : '';
                btn.title = blocked ? 'Waiting for AI to estimate CO\u2082 factor\u2026' : '';
            }

            if (materialSelect) {
                materialSelect.addEventListener('change', function() {
                    if (materialSelect.value === 'other') {
                        if (customInput) customInput.style.display = 'block';
                    } else {
                        if (customInput) customInput.style.display = 'none';
                        clearGeminiSuggestion();
                    }
                    updateEstimation();
                });
            }

            function updateEstimation() {
                var weightInput = document.getElementById('offer-weight');
                var weight = weightInput ? parseFloat(weightInput.value) : 0;
                var mat = materialSelect ? materialSelect.value : '';
                var nameForScore = '';
                if (mat === 'other' && customInput) {
                    nameForScore = customInput.value.trim();
                } else if (materialSelect && materialSelect.value && materialSelect.value !== 'other') {
                    nameForScore = materialSelect.options[materialSelect.selectedIndex].text;
                }
                var est = 0;
                if (weight > 0 && (mat || nameForScore)) {
                    var url = 'upcycling-score?poids=' + encodeURIComponent(weight);
                    if (mat && mat !== 'other') {
                        url += '&facteurId=' + encodeURIComponent(mat);
                    }
                    if (nameForScore) {
                        url += '&matType=' + encodeURIComponent(nameForScore);
                    }
                    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
                        .then(function(res){ return res.json(); })
                        .then(function(obj){
                            est = parseFloat(obj.score) || 0;
                            displayEstimation(est);
                        });
                } else {
                    displayEstimation(0);
                }
            }

            function displayEstimation(value) {
                if (!estimationGroup) return;
                var input = document.getElementById('offer-estimation');
                var mat = materialSelect ? materialSelect.value : '';
                if (value > 0) {
                    estimationGroup.style.display = 'block';
                    if (input) input.value = value;
                } else if (mat === 'other') {

                    estimationGroup.style.display = 'block';
                } else {
                    estimationGroup.style.display = 'none';
                    if (input) input.value = '';
                }
            }

            function fetchGeminiEstimation(materialName) {
                console.log('Fetching Gemini estimation for:', materialName);
                fetch('gemini-material-api?material=' + encodeURIComponent(materialName), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) {
                    console.log('Gemini API Response Status:', r.status);
                    return r.json();
                })
                .then(function(data) {
                    console.log('Gemini API Response Data:', data);
                    setSubmitBlocked(false);
                    if (data.error || !(data.facteur_co2 > 0)) {
                        console.error('Gemini error or invalid CO2 factor:', data.error || 'Invalid CO2');
                        clearGeminiSuggestion();
                        return;
                    }
                    geminiCO2Factor = data.facteur_co2;
                    showGeminiSuggestion(data.facteur_co2);
                    var wEl = document.getElementById('offer-weight');
                    var w   = wEl ? (parseFloat(wEl.value) || 0) : 0;
                    if (w > 0) {
                        displayEstimation(Math.round(w * geminiCO2Factor * 100) / 100);
                    }
                })
                .catch(function(err) {
                    console.error('Gemini API Error:', err);
                    setSubmitBlocked(false);
                    clearGeminiSuggestion();
                });
            }

            function showGeminiLoading() {
                var badge   = document.getElementById('gemini-ai-badge');
                var spinner = document.getElementById('gemini-spinner');
                var text    = document.getElementById('gemini-badge-text');
                if (!badge) return;
                badge.style.display = 'block';
                if (spinner) { spinner.className = 'fa-solid fa-spinner fa-spin'; spinner.style.display = ''; }
                if (text)    text.textContent = '\u00a0Estimating CO\u2082 factor with AI\u2026';
                var inp = document.getElementById('offer-estimation');
                if (inp) inp.value = '';
                setSubmitBlocked(true);
            }

            function showGeminiSuggestion(factor) {
                setSubmitBlocked(false);
                var badge   = document.getElementById('gemini-ai-badge');
                var spinner = document.getElementById('gemini-spinner');
                var text    = document.getElementById('gemini-badge-text');
                if (!badge) return;
                badge.style.display = 'block';
                if (spinner) spinner.style.display = 'none';
                if (text) text.innerHTML =
                    '\u00a0<span style="color:#4285f4;font-weight:600;"><i class="fa-brands fa-google"></i> Gemini</span>'
                    + ' estimated <strong>' + parseFloat(factor).toFixed(4) + ' kg CO\u2082 eq/kg</strong>'
                    + ' \u2014 score\u00a0=\u00a0weight\u00a0\u00d7\u00a0factor';
            }

            function clearGeminiSuggestion() {
                geminiCO2Factor = null;
                clearTimeout(_geminiTimer);
                setSubmitBlocked(false);
                var badge = document.getElementById('gemini-ai-badge');
                if (badge) badge.style.display = 'none';
            }

            var weightInputEl = document.getElementById('offer-weight');
            if (weightInputEl) {
                weightInputEl.addEventListener('input', function() {
                    updateEstimation();
                    if (geminiCO2Factor && materialSelect && materialSelect.value === 'other') {
                        var w = parseFloat(this.value) || 0;
                        if (w > 0) {
                            var inp = document.getElementById('offer-estimation');
                            if (inp) inp.value = Math.round(w * geminiCO2Factor * 100) / 100;
                        }
                    }
                });
            }
            if (customInput) {
                customInput.addEventListener('input', function() {
                    updateEstimation();
                    var name = customInput.value.trim();
                    clearTimeout(_geminiTimer);
                    if (name.length >= 3) {
                        showGeminiLoading();
                        _geminiTimer = setTimeout(function() { fetchGeminiEstimation(name); }, 2000);
                    } else {
                        clearGeminiSuggestion();
                    }
                });
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            var titleInput = document.getElementById('offer-title');
            var descInput = document.getElementById('offer-description');
            var priceInput = document.getElementById('offer-price');
            var weightInput = document.getElementById('offer-weight');
            var materialSelect = document.getElementById('offer-material');
            var customInput = document.getElementById('offer-material-custom');
            var estimateInput = document.getElementById('offer-estimation');
            var submitButton = form.querySelector('button[type="submit"]');

            var title = titleInput.value.trim();
            var description = descInput.value.trim();
            var price = priceInput.value;
            var weight = weightInput ? weightInput.value : '';
            var material = materialSelect ? materialSelect.value.trim() : '';
            var customMat = customInput ? customInput.value.trim() : '';
            var estimate = estimateInput ? estimateInput.value : '';

            var errors = [];
            if (!title) {
                errors.push('Title is required');
            } else if (title.length > 60) {
                errors.push('Title must be 60 characters or less');
            }

            if (!description) {
                errors.push('Description is required');
            } else if (description.length > 1000) {
                errors.push('Description must be 1000 characters or less');
            }

            if (!price || isNaN(price) || parseFloat(price) < 0) {
                errors.push('Price must be a valid positive number');
            }
            if (weight && (isNaN(weight) || parseFloat(weight) < 0)) {
                errors.push('Weight must be a positive number');
            }
            if (weight && !material) {
                errors.push('Material type is required when weight is provided');
            }
            if (material === 'other') {
                if (!customMat) {
                    errors.push('Please specify material when "Other" is selected');
                }
                if (!estimate || isNaN(estimate) || parseFloat(estimate) <= 0) {
                    errors.push('Please provide a valid estimated upcycling score');
                }
            }

            if (!selectedFiles.length) {
                errors.push('Please add at least one image');
            } else if (selectedFiles.length > 10) {
                errors.push('Maximum 10 images allowed');
            }

            submitButton.disabled = true;
            var originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fa-solid fa-spinner"></i> Checking content...';

            if (typeof Moderator !== 'undefined') {
                Moderator.checkFields({
                    'title': title,
                    'description': description
                }).then(function(validation) {
                    if (!validation.valid) {
                        errors.push('Content moderation: ' + Object.values(validation.errors).join('; '));
                    }
                    
                    if (errors.length > 0) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalText;
                        var errorMessage = errors.join('<br>');
                        var errorBox = document.querySelector('.add-modal .form-error') || createErrorBox();
                        errorBox.innerHTML = errorMessage;
                        errorBox.style.display = 'block';
                        return;
                    }
                    
                    submitForm();
                }).catch(function(err) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    console.error('Moderation check error:', err);
                });
                return;
            }
            
            submitButton.innerHTML = '<i class="fa-solid fa-spinner"></i> Creating...';
            submitForm();

            function submitForm() {
            var categorySelect = document.getElementById('offer-category');
            var category = categorySelect ? categorySelect.value : '';

            var payload = {
                title: title,
                description: description,
                price: parseFloat(price),
                poids_materiaux: weight ? parseFloat(weight) : 0,
                user_id: window.currentUserId
            };
            if (category) {
                payload.category_id = category;
            }
            if (material === 'other') {
                payload.type_materiaux = customMat;
                payload.estimation_score = parseFloat(estimate);
            } else if (material) {
                payload.facteur_id = material;
                payload.type_materiaux = materialSelect.options[materialSelect.selectedIndex].text;
            }

            fetch('create-annonce', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(response) {
                return response.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.log('Raw response:', text);
                        throw new Error('Invalid response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(function(result) {
                if (result.error) {
                    throw new Error(result.error);
                }
                return result;
            })
            .then(function(annonce) {
                var imagePromises = selectedFiles.map(function(file) {
                    return uploadImage(file, annonce.id);
                });
                return Promise.allSettled(imagePromises).then(function(results) {
                    results.forEach(function(result, index) {
                        if (result.status === 'rejected') {
                        }
                    });
                    return annonce;
                });
            })
            .then(function() {
                form.reset();
                selectedFiles = [];
                syncFileInput();
                renderPreview();
                closeModal();
                if (window.loadOffers) {
                    window.loadOffers();
                }
            })
            .catch(function(error) {
                if (window.loadOffers) {
                    window.loadOffers();
                }
            })
            .finally(function() {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
            } // End of submitForm()
        });
    }

    function validateMaximumPrice(){
        if (this.max) this.value = Math.min(parseInt(this.max), parseInt(this.value) || 0);
    }

    function uploadImage(file, annonceId) {
        return new Promise(function(resolve, reject) {
            var reader = new FileReader();

            reader.onload = function(event) {
                var base64String = event.target.result.split(',')[1];

                fetch('create-image?annonce_id=' + encodeURIComponent(annonceId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        annonce_id: annonceId,
                        file_name: file.name,
                        file_data: base64String
                    })
                })
                .then(function(response) {
                    return response.text().then(function(text) {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid response: ' + text.substring(0, 200));
                        }
                    });
                })
                .then(function(result) {
                    if (result.error) {
                        throw new Error(result.error);
                    }
                    resolve();
                })
                .catch(function(error) {
                    reject(error);
                });
            };

            reader.onerror = function() {
                reject(new Error('Failed to read file: ' + file.name));
            };

            reader.readAsDataURL(file);
        });
    }
    }}
)();
