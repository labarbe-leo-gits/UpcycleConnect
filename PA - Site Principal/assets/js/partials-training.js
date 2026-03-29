(function() {
    'use strict';

    const apiBase = '../common/updoc-api';
    const container = document.getElementById('training-list');
    const empty = document.getElementById('training-empty');

    function loadTrainings() {
        if (!container) return;
        container.innerHTML = '<div class="skeleton" style="height:88px;">Chargement...</div>';

        fetch(apiBase + '?type=projects', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            const items = (data.items || data) || [];
            if (!items.length) {
                empty.style.display = 'block';
                container.innerHTML = '';
                return;
            }
            empty.style.display = 'none';
            container.innerHTML = '';

            items.forEach(project => {
                const card = document.createElement('div');
                card.className = 'project-card';

                const statusMap = {0: 'En attente', 1: 'Validé', 2: 'Rejeté'};

                card.innerHTML = `
                    <h3>${escapeHtml(project.title)}</n3>
                    <p>${escapeHtml(project.description)}</p>
                    <small>Status: ${statusMap[project.status] || 'Inconnu'}</small>
                    <div class="project-actions">
                        <button class="btn-secondary" data-id="${project.id}" data-action="view">Voir</button>
                        <button class="btn-primary" data-id="${project.id}" data-action="edit">Modifier</button>
                    </div>
                `;

                card.querySelector('[data-action="view"]').addEventListener('click', () => {
                    window.location.href = `../customers/updoc-view?project_id=${encodeURIComponent(project.id)}`;
                });

                card.querySelector('[data-action="edit"]').addEventListener('click', () => {
                    const title = prompt('Titre', project.title || '');
                    const description = prompt('Description', project.description || '');
                    if (!title || !description) return;
                    fetch('../common/updoc-api', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ id: project.id, title: title.trim(), description: description.trim() })
                    })
                    .then(r => r.json())
                    .then(() => loadTrainings())
                    .catch(console.error);
                });

                container.appendChild(card);
            });
        })
        .catch(err => {
            console.error('Cannot load trainings', err);
            container.innerHTML = '<p class="error-message">Impossible de charger les formations.</p>';
        });
    }

    function createTraining() {
        const title = prompt('Titre de la formation');
        const description = prompt('Description de la formation');
        if (!title || !description) return;

        fetch('../common/updoc-api', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ title: title.trim(), description: description.trim() })
        })
        .then(r => r.json())
        .then(() => loadTrainings())
        .catch(err => {
            if (typeof window.showToast === 'function') {
                window.showToast('Training creation failed: ' + (err.message || err), 'error');
            } else {
                console.error(err);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const addBtn = document.getElementById('add-training');
        if (addBtn) {
            addBtn.addEventListener('click', createTraining);
        }
        loadTrainings();
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
})();