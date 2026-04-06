(function () {
    'use strict';

    var mde = new EasyMDE({
        element: document.getElementById('proj-description'),
        spellChecker: false,
        autosave: { enabled: false },
        placeholder: 'Describe your project here. Markdown is supported…',
        maxHeight: '320px',
        toolbar: [
            'bold', 'italic', 'heading', '|',
            'quote', 'unordered-list', 'ordered-list', '|',
            'link', 'image', '|', 'preview', 'side-by-side', 'fullscreen'
        ],
    });

    var stepList   = document.getElementById('updoc-step-list');
    var emptyState = document.getElementById('updoc-empty-state');
    var aiUsed     = false;

    Sortable.create(stepList, {
        handle: '.updoc-step-drag-handle',
        animation: 150,
        onEnd: renumberSteps
    });

    function feedback(msg, type) {
        var el = document.getElementById('updoc-feedback');
        el.textContent = msg;
        el.className   = 'updoc-feedback ' + type;
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideFeedback() {
        var el = document.getElementById('updoc-feedback');
        el.className = 'updoc-feedback';
    }

    function apiPost(action, body) {
        var apiPath = typeof UPDOC_API_PATH !== 'undefined' ? UPDOC_API_PATH : 'updoc-api';
        return fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(Object.assign({ action: action }, body))
        }).then(function (r) { return r.json(); });
    }

    function renumberSteps() {
        var cards = stepList.querySelectorAll('.updoc-step-card');
        cards.forEach(function (card, i) {
            var numEl = card.querySelector('.updoc-step-num');
            if (numEl) { numEl.textContent = 'Step ' + (i + 1); }
            card.dataset.stepOrder = i + 1;
        });
        toggleEmptyState();
    }

    function toggleEmptyState() {
        var hasCards = stepList.querySelectorAll('.updoc-step-card').length > 0;
        if (emptyState) {
            emptyState.style.display = hasCards ? 'none' : '';
        }
    }

    function openModal(el) {
        el.classList.add('is-visible');
        document.body.classList.add('modal-open');
    }
    function closeModal(el) {
        el.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
    }

    document.getElementById('add-step-btn').addEventListener('click', function () {
        openStepModal(null);
    });

    function buildStepCard(step) {
        var card = document.createElement('div');
        card.className = 'updoc-step-card';
        card.dataset.stepId    = step.id || '';
        card.dataset.stepOrder = step.step_order || 1;

        var matsHtml = '';
        (step.materials || []).forEach(function (m) {
            matsHtml += buildMatTagHtml(m.facteur_id || m.id, m.nom || m.name || m.facteur_id, m.quantity);
        });

        var matOptions = '';
        if (Array.isArray(AVAIL_MATS)) {
            matOptions = AVAIL_MATS.map(function (m) {
                return '<option value="' + escAttr(m.id) + '">' + escHtml(m.nom || '') + '</option>';
            }).join('');
        }

        card.innerHTML =
            '<div class="updoc-step-head">' +
              '<span class="updoc-step-drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>' +
              '<span class="updoc-step-num">Step ' + (step.step_order || 1) + '</span>' +
              '<input type="text" class="updoc-step-title-input" placeholder="Step title" value="' + escAttr(step.title || '') + '" readonly>' +
              '<button type="button" class="updoc-step-edit" title="Edit step"><i class="fa-solid fa-pen"></i></button>' +
              '<button type="button" class="updoc-step-remove" title="Remove step"><i class="fa-solid fa-xmark"></i></button>' +
            '</div>' +
            '<div class="updoc-step-body">' +
              '<div class="field updoc-step-desc"><label>Description</label>' +
                '<textarea placeholder="Describe this step..." readonly>' + escHtml(step.description || '') + '</textarea>' +
              '</div>' +
              '<div class="field updoc-step-duration"><label>Duration (minutes)</label>' +
                '<input type="number" min="1" placeholder="e.g. 30" value="' + escAttr(step.duration_minutes != null ? String(step.duration_minutes) : '') + '" readonly>' +
              '</div>' +
              '<div class="field updoc-step-materials"><label>Materials</label>' +
                '<div class="updoc-material-list">' + matsHtml + '</div>' +
                '<div class="updoc-material-add">' +
                  '<select class="mat-select"><option value="">Select material</option>' + matOptions + '</select>' +
                  '<input type="number" class="qty-input" style="padding-left:None;" min="0.01" step="1" placeholder="Qty">' +
                  '<button type="button" class="btn-secondary mat-add-btn" style="padding:.35rem .65rem;font-size:.82rem;"><i class="fa-solid fa-plus"></i></button>' +
                '</div>' +
              '</div>' +
            '</div>';

        bindStepCard(card);
        return card;
    }

    function buildMatTagHtml(facteurId, name, qty) {
        var qtyStr = qty != null && qty !== '' ? ' (' + qty + ')' : '';
        return '<span class="updoc-material-tag" data-facteur-id="' + escAttr(facteurId) + '">' +
               escHtml(name) + escHtml(qtyStr) +
               '<button type="button" title="Remove material"><i class="fa-solid fa-xmark"></i></button>' +
               '</span>';
    }

    function bindStepCard(card) {
        card.querySelector('.updoc-step-remove').addEventListener('click', function () {
            removeStep(card);
        });

        card.querySelector('.updoc-step-edit').addEventListener('click', function () {
            openStepModal(card);
        });

        card.querySelector('.mat-add-btn').addEventListener('click', function () {
            addMaterial(card);
        });

        card.querySelector('.updoc-material-list').addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var tag       = btn.closest('.updoc-material-tag');
            var facteurId = tag?.dataset.facteurId;
            var stepId    = card.dataset.stepId;
            var projId    = PROJECT_ID;
            if (projId && stepId && facteurId) {
                apiPost('remove_material', { project_id: projId, step_id: stepId, facteur_id: facteurId })
                    .then(function () { tag.remove(); });
            } else {
                if (tag) tag.remove();
            }
        });

    }

    function removeStep(card) {
        var stepId = card.dataset.stepId;
        var projId = PROJECT_ID;
        if (projId && stepId) {
            apiPost('delete_step', { project_id: projId, step_id: stepId }).then(function () {
                card.remove();
                renumberSteps();
            }).catch(function () {
                card.remove();
                renumberSteps();
            });
        } else {
            card.remove();
            renumberSteps();
        }
    }

    function addMaterial(card) {
        var sel       = card.querySelector('.mat-select');
        var qtyInput  = card.querySelector('.qty-input');
        var facteurId = sel?.value;
        var matName   = sel?.options[sel.selectedIndex]?.text || facteurId;
        var qty       = qtyInput?.value;

        if (!facteurId) return;

        var stepId = card.dataset.stepId;
        var projId = PROJECT_ID;

        var matList = card.querySelector('.updoc-material-list');

        if (projId && stepId) {
            apiPost('add_material', { project_id: projId, step_id: stepId, facteur_id: facteurId, quantity: qty ? parseFloat(qty) : null })
                .then(function (data) {
                    if (data.error) { feedback(data.error, 'error'); return; }
                    matList.insertAdjacentHTML('beforeend', buildMatTagHtml(facteurId, matName, qty || null));
                    sel.value    = '';
                    qtyInput.value = '';
                });
        } else {
            matList.insertAdjacentHTML('beforeend', buildMatTagHtml(facteurId, matName, qty || null));
            sel.value      = '';
            qtyInput.value = '';
        }
    }

    function loadSteps() {
        var skeleton = document.getElementById('updoc-step-skeleton');
        if (skeleton) { skeleton.style.display = ''; }
        stepList.style.display = 'none';
        if (emptyState) { emptyState.style.display = 'none'; }

        apiPost('get_steps', { project_id: PROJECT_ID })
            .then(function (resp) {
                var steps = Array.isArray(resp) ? resp : [];
                stepList.innerHTML = '';
                if (steps.length > 0) {
                    var frag = document.createDocumentFragment();
                    steps.forEach(function (step) {
                        var card = buildStepCard(step);
                        frag.appendChild(card);
                    });
                    stepList.appendChild(frag);
                    renumberSteps();
                }
                if (skeleton) { skeleton.style.display = 'none'; }
                stepList.style.display = '';
                toggleEmptyState();
            })
            .catch(function (err) {
                console.error('loadSteps error:', err);
                feedback('Failed to load steps. Please refresh.', 'error');
                if (skeleton) { skeleton.style.display = 'none'; }
                stepList.style.display = '';
                toggleEmptyState();
            });
    }

    if (IS_EDIT) {
        loadSteps();
    } else {
        toggleEmptyState();
    }

    var stepModal       = document.getElementById('step-modal');
    var stepModalTitle  = document.getElementById('step-modal-title');
    var stepModalName   = document.getElementById('step-modal-name');
    var stepModalDesc   = document.getElementById('step-modal-desc');
    var stepModalDur    = document.getElementById('step-modal-duration');
    var stepModalLabel  = document.getElementById('step-modal-save-label');
    var stepModalSave   = document.getElementById('step-modal-save');

    function openStepModal(card) {
        EDIT_CARD = card;
        stepModalName.classList.remove('is-invalid');
        if (card) {
            stepModalTitle.textContent = 'Edit Step';
            stepModalLabel.textContent = 'Save Changes';
            stepModalName.value = card.querySelector('.updoc-step-title-input').value;
            stepModalDesc.value = card.querySelector('.updoc-step-desc textarea').value;
            stepModalDur.value  = card.querySelector('.updoc-step-duration input').value;
        } else {
            stepModalTitle.textContent = 'Add Step';
            stepModalLabel.textContent = 'Add Step';
            stepModalName.value = '';
            stepModalDesc.value = '';
            stepModalDur.value  = '';
        }
        openModal(stepModal);
        setTimeout(function () { stepModalName.focus(); }, 80);
    }

    function closeStepModal() {
        closeModal(stepModal);
        EDIT_CARD = null;
    }

    document.getElementById('step-modal-close').addEventListener('click',  closeStepModal);
    document.getElementById('step-modal-cancel').addEventListener('click', closeStepModal);
    stepModal.addEventListener('click', function (e) {
        if (e.target === stepModal) { closeStepModal(); }
    });
    stepModal.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeStepModal(); }
    });

    stepModalSave.addEventListener('click', function () {
        var name = stepModalName.value.trim();
        if (!name) {
            stepModalName.classList.add('is-invalid');
            stepModalName.focus();
            return;
        }
        stepModalName.classList.remove('is-invalid');

        var desc = stepModalDesc.value.trim();
        var dur  = stepModalDur.value;
        var durVal = dur !== '' ? parseInt(dur, 10) : null;

        stepModalSave.disabled = true;

        if (EDIT_CARD) {
            var card = EDIT_CARD;
            card.querySelector('.updoc-step-title-input').value   = name;
            card.querySelector('.updoc-step-desc textarea').value = desc;
            card.querySelector('.updoc-step-duration input').value = dur;

            var stepId = card.dataset.stepId;
            if (PROJECT_ID && stepId) {
                apiPost('update_step', {
                    project_id:       PROJECT_ID,
                    step_id:          stepId,
                    title:            name,
                    description:      desc,
                    step_order:       parseInt(card.dataset.stepOrder || 1, 10),
                    duration_minutes: durVal
                }).then(function () {
                    stepModalSave.disabled = false;
                    closeStepModal();
                }).catch(function () {
                    stepModalSave.disabled = false;
                    closeStepModal();
                });
            } else {
                stepModalSave.disabled = false;
                closeStepModal();
            }
        } else {
            var idx = stepList.querySelectorAll('.updoc-step-card').length + 1;
            var stepData = {
                id: null, step_order: idx,
                title: name, description: desc, duration_minutes: durVal
            };

            if (PROJECT_ID) {
                apiPost('create_step', {
                    project_id:       PROJECT_ID,
                    title:            name,
                    description:      desc,
                    step_order:       idx,
                    duration_minutes: durVal
                }).then(function (data) {
                    if (data.error) {
                        feedback(data.error, 'error');
                        stepModalSave.disabled = false;
                        return;
                    }
                    stepData.id = data.id;
                    var card = buildStepCard(stepData);
                    var inp = card.querySelector('.step-img-upload');
                    if (inp) {
                        inp.disabled          = false;
                        inp.dataset.projectId = PROJECT_ID;
                        inp.dataset.stepId    = data.id;
                    }
                    stepList.appendChild(card);
                    renumberSteps();
                    stepModalSave.disabled = false;
                    closeStepModal();
                }).catch(function () {
                    feedback('Failed to create step.', 'error');
                    stepModalSave.disabled = false;
                });
            } else {
                var card = buildStepCard(stepData);
                stepList.appendChild(card);
                renumberSteps();
                stepModalSave.disabled = false;
                closeStepModal();
            }
        }
    });

    document.getElementById('save-project-btn').addEventListener('click', saveProject);

    function saveProject() {
        var title       = document.getElementById('proj-title').value.trim();
        var description = mde.value().trim();
        var status      = parseInt(document.getElementById('proj-status').value, 10);

        if (!title) {
            feedback('Project title is required.', 'error');
            document.getElementById('proj-title').focus();
            return;
        }

        var btn         = document.getElementById('save-project-btn');
        var originalHtml = btn.innerHTML;
        btn.disabled    = true;
        btn.innerHTML   = '<i class="fa-solid fa-spinner spinner-icon"></i> Saving\u2026';

        var action  = IS_EDIT ? 'update_project' : 'create_project';
        var payload = { title: title, description: description, status: status };
        if (aiUsed) { payload.ai_generated = 1; }
        if (IS_EDIT) { payload.project_id = PROJECT_ID; }

        var stepsText = [];
        stepList.querySelectorAll('.updoc-step-card').forEach(function (card) {
            var t = card.querySelector('.updoc-step-title-input').value.trim();
            var d = card.querySelector('.updoc-step-desc textarea').value.trim();
            if (t || d) { stepsText.push(t + ' ' + d); }
        });
        payload.steps_text = stepsText.join(' \n');

        apiPost(action, payload).then(function (data) {
            if (data.error) {
                feedback(data.error, 'error');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                return;
            }

            var projId = IS_EDIT ? PROJECT_ID : data.id;
            if (!IS_EDIT) { PROJECT_ID = data.id; IS_EDIT = true; }

            return saveAllSteps(projId).then(function () {
                window.location.href = 'updoc-view?id=' + encodeURIComponent(projId);
            });
        }).catch(function () {
            feedback('Unexpected error. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }

    function saveAllSteps(projId) {
        var cards = Array.from(stepList.querySelectorAll('.updoc-step-card'));
        var chain = Promise.resolve();
        cards.forEach(function (card, idx) {
            var stepId   = card.dataset.stepId || '';
            var title    = card.querySelector('.updoc-step-title-input').value.trim();
            var desc     = card.querySelector('.updoc-step-desc textarea').value.trim();
            var duration = card.querySelector('.updoc-step-duration input').value;

            if (!title) return;

            var payload = {
                project_id:       projId,
                title:            title,
                description:      desc,
                step_order:       idx + 1,
                duration_minutes: duration !== '' ? parseInt(duration, 10) : null,
            };

            if (stepId) {
                payload.step_id = stepId;
                chain = chain.then(function () { return apiPost('update_step', payload); });
            } else {
                chain = chain.then(function () {
                    return apiPost('create_step', payload).then(function (data) {
                        if (data.id) {
                            card.dataset.stepId = data.id;
                            var inp = card.querySelector('.step-img-upload');
                            if (inp) {
                                inp.disabled          = false;
                                inp.dataset.projectId = projId;
                                inp.dataset.stepId    = data.id;
                            }
                            var tags = card.querySelectorAll('.updoc-material-tag');
                            tags.forEach(function (tag) {
                                var fid = tag.dataset.facteurId;
                                if (fid) {
                                    apiPost('add_material', { project_id: projId, step_id: data.id, facteur_id: fid });
                                }
                            });
                        }
                    });
                });
            }
        });
        return chain;
    }

    function addPostSaveLinks() {
        var actions = document.querySelector('.updoc-actions');
        if (!actions || actions.querySelector('.view-link')) return;
        var viewLink   = '<a href="updoc-view?id=' + encodeURIComponent(PROJECT_ID) + '" class="updoc-cancel-btn view-link"><i class="fa-solid fa-eye"></i> View project</a>';
        var exportBtn  = '<button type="button" class="updoc-export-btn" id="export-pdf-btn" data-project-id="' + escAttr(PROJECT_ID) + '"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>';
        actions.querySelector('.updoc-save-btn').insertAdjacentHTML('afterend', viewLink + exportBtn);
        document.getElementById('export-pdf-btn').addEventListener('click', triggerPdfExport);
    }

    var exportBtn = document.getElementById('export-pdf-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', triggerPdfExport);
    }

    function triggerPdfExport() {
        if (!PROJECT_ID) { feedback('Save the project first to export PDF.', 'error'); return; }
        window.open('export-pdf?id=' + encodeURIComponent(PROJECT_ID), '_blank');
    }

    var aiModal     = document.getElementById('ai-modal');
    var aiCtxInput  = document.getElementById('ai-context-input');
    var aiGenBtn    = document.getElementById('ai-gen-submit');

    document.getElementById('ai-generate-btn').addEventListener('click', function () {
        aiCtxInput.value = document.getElementById('proj-title').value;
        openModal(aiModal);
        setTimeout(function () { aiCtxInput.focus(); }, 80);
    });

    document.getElementById('ai-modal-cancel').addEventListener('click', function () {
        closeModal(aiModal);
    });
    document.getElementById('ai-modal-close').addEventListener('click', function () {
        closeModal(aiModal);
    });
    aiModal.addEventListener('click', function (e) {
        if (e.target === aiModal) { closeModal(aiModal); }
    });

    aiGenBtn.addEventListener('click', function () {
        var context = aiCtxInput.value.trim();
        if (!context) { return; }

        aiGenBtn.disabled    = true;
        aiGenBtn.innerHTML   = '<i class="fa-solid fa-spinner spinner-icon"></i> Generating…';

        fetch('gemini-api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ type: 'generate_all', context: context })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) {
                feedback(data.error, 'error');
                throw new Error(data.error);
            }

            var raw = data.text || '';
            var descMatch  = raw.match(/DESCRIPTION:\s*([\s\S]*?)(?=\nSTEPS:|$)/i);
            var stepsMatch = raw.match(/STEPS:\s*([\s\S]*)/i);

            if (descMatch && descMatch[1].trim()) {
                mde.value(descMatch[1].trim());
                mde.codemirror.refresh();
            }
            if (stepsMatch && stepsMatch[1].trim()) {
                parseAndAddAiSteps(stepsMatch[1].trim());
            }

            closeModal(aiModal);
            aiGenBtn.disabled  = false;
            aiGenBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
            aiUsed = true;
            feedback('AI suggestions applied! Review and save.', 'success');
        })
        .catch(function (err) {
            aiGenBtn.disabled  = false;
            aiGenBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Generate';
            if (!document.querySelector('.updoc-feedback.error')) {
                feedback('AI generation failed. Check that GEMINI_API_KEY is set.', 'error');
            }
        });
    });

    function parseAndAddAiSteps(text) {
        var lines = text.split('\n');
        var parsed = [];
        lines.forEach(function (line) {

            line = line.replace(/\r/g, '').trim();
            var numMatch = line.match(/^\d+\.\s+(.+)$/);
            if (!numMatch) return;

            var rest = numMatch[1];
            rest = rest.replace(/^\*{1,3}/, '').replace(/\*{1,3}$/, '').trim();

            var sepMatch = rest.match(/^(.+?)\*{0,3}\s+[-–]\s+(.+)$/) ||
                           rest.match(/^(.+?)\*{0,3}:\s+(.+)$/);

            if (sepMatch) {
                parsed.push({
                    title:       sepMatch[1].replace(/\*+/g, '').trim(),
                    description: sepMatch[2].replace(/\*+/g, '').trim()
                });
            } else {
                parsed.push({ title: rest.replace(/\*+/g, '').trim(), description: '' });
            }
        });
        if (!parsed.length) return;

        stepList.querySelectorAll('.updoc-step-card').forEach(function (card) {
            var t = card.querySelector('.updoc-step-title-input').value.trim();
            if (!t && !card.dataset.stepId) card.remove();
        });

        var frag = document.createDocumentFragment();
        var baseIndex = stepList.querySelectorAll('.updoc-step-card').length;
        parsed.forEach(function (s, i) {
            var card = buildStepCard({ id: null, step_order: baseIndex + i + 1, title: s.title, description: s.description });
            frag.appendChild(card);
        });
        stepList.appendChild(frag);
        renumberSteps();
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) { return escHtml(str); }

})();