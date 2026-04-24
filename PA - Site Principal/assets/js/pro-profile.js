(function () {
    var loaded = false;

    function fmt(n, d) {
        return Number(n).toLocaleString('en-GB', { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    function renderPremium(priceDisplay) {
        var el = document.getElementById('acc-sub-content');
        el.innerHTML =
            '<div class="acc-sub-actions">'
            + '<div class="acc-sub-premium-status"><i class="fas fa-crown"></i> Premium active</div>'
            + '</div>'
            + '<p style="font-size:14px;color:#065f46;margin:0 0 16px;">You have access to all advanced UpcycleConnect features.</p>'
            + '<div class="acc-sub-actions">'
            + '<a href="dashboard" class="sub-quick-btn primary"><i class="fas fa-chart-bar"></i> Go to Dashboard</a>'
            + '<button id="acc-btn-manage" class="sub-quick-btn" data-url="create-billing-portal"><i class="fas fa-cog"></i> Manage subscription</button>'
            + '</div>';

        var btnManage = document.getElementById('acc-btn-manage');
        if (btnManage) {
            btnManage.addEventListener('click', function () {
                btnManage.disabled = true;
                btnManage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                fetch(btnManage.dataset.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.portal_url) {
                        window.location.href = data.portal_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnManage.disabled = false;
                        btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage subscription';
                    }
                }).catch(function () {
                    alert('Network error.');
                    btnManage.disabled = false;
                    btnManage.innerHTML = '<i class="fas fa-cog"></i> Manage subscription';
                });
            });
        }
    }

    function renderFree(priceDisplay) {
        var display = priceDisplay || '€29.99 / month';
        var el = document.getElementById('acc-sub-content');
        el.innerHTML =
            '<p class="acc-sub-free-cta">You are on the <strong>Free</strong> plan. Upgrade to unlock advanced analytics and tools.</p>'
            + '<div class="acc-sub-actions">'
            + '<a href="subscription" class="sub-quick-btn primary"><i class="fas fa-crown"></i> Go Premium</a>'
            + '<a href="subscription" class="sub-quick-btn"><i class="fas fa-info-circle"></i> Learn more</a>'
            + '</div>'
            

        var btnSub = document.getElementById('acc-btn-subscribe');
        if (btnSub) {
            btnSub.addEventListener('click', function () {
                btnSub.disabled = true;
                btnSub.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                fetch('create-subscription-checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btnSub.disabled = false;
                        btnSub.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                    }
                }).catch(function () {
                    alert('Network error.');
                    btnSub.disabled = false;
                    btnSub.innerHTML = '<i class="fas fa-crown"></i> Go Premium';
                });
            });
        }
    }

    function loadSubscription() {
        if (loaded) return;
        loaded = true;
        fetch('subscription-api', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            document.getElementById('acc-sub-skeleton').style.display = 'none';
            document.getElementById('acc-sub-content').style.display  = '';

            var quickLabel = document.getElementById('sub-quick-label');
            var quickBtn   = document.getElementById('sub-quick-access');

            if (data.is_premium) {
                if (quickLabel) quickLabel.textContent = 'My subscription';
                if (quickBtn)   quickBtn.classList.add('primary');
                renderPremium(data.price_display);
            } else {
                if (quickLabel) quickLabel.textContent = 'Go Premium';
                renderFree(data.price_display);
            }
        }).catch(function () {
            document.getElementById('acc-sub-skeleton').style.display = 'none';
            document.getElementById('acc-sub-content').style.display  = '';
            document.getElementById('acc-sub-content').innerHTML =
                '<p style="color:#9ca3af;font-size:14px;">Unable to load subscription status.</p>';
        });
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str);
    }

    function buildContractCard(c) {
        var status = c.status === 1 ? 'Active' : 'Inactive';
        var statusCls = c.status === 1 ? 'status-confirmed' : 'status-cancelled';
        var title = c.contract_ref || c.subscription_id || c.id || 'Subscription';
        var amount = (c.amount != null && !isNaN(parseFloat(c.amount))) ? parseFloat(c.amount).toFixed(2) : null;
        var start = c.start_date || '';
        var end = c.end_date || '';

        var el = document.createElement('div');
        el.className = 'acc-card';
        el.setAttribute('role', 'listitem');
        el.innerHTML =
            '<div class="acc-card-icon"><i class="fa-solid fa-file-contract"></i></div>' +
            '<div class="acc-card-body">' +
              '<div class="acc-card-title">' + escHtml(title) + '</div>' +
              '<div class="acc-card-meta"><span class="acc-status ' + statusCls + '">' + escHtml(status) + '</span></div>' +
              (amount ? '<div class="acc-card-amount">€ ' + escHtml(amount) + '</div>' : '') +
              ((start || end) ? '<div class="acc-card-date"><i class="fa-regular fa-calendar"></i> ' + escHtml(start + (start && end ? ' → ' : '') + end) + '</div>' : '') +
              '<div class="acc-card-actions" style="display:flex;gap:5px;justify-content:center;align-items:center;width:100%;border-top:1px solid #ccc;padding-top:10px;flex-wrap:wrap;">' +
                '<button class="btn-secondary manage-contract-btn" style="padding:6px;" data-subscription-id="' + escAttr(c.subscription_id || '') + '"><i class="fas fa-cog"></i> Manage subscription</button>' +
              '</div>' +
            '</div>';
        return el;
    }

    var contractsCache = [];
    var contractsPage = 1;
    var contractsPerPage = 4;

    function updateContractsNav(data) {
        var grid = document.getElementById('contracts-grid');
        if (!grid) return;
        var prevBtn = grid.querySelector('.acc-prev');
        var nextBtn = grid.querySelector('.acc-next');
        var pageInfo = grid.querySelector('.acc-page-info');

        if (!prevBtn || !nextBtn || !pageInfo) return;

        var total = data.length;
        var pages = Math.max(1, Math.ceil(total / contractsPerPage));
        var page = contractsPage;

        prevBtn.disabled = page <= 1;
        nextBtn.disabled = page >= pages;

        if (pageInfo) {
            var from = (page - 1) * contractsPerPage + 1;
            var to = Math.min(page * contractsPerPage, total);
            pageInfo.textContent = total > 0 ? from + '–' + to + ' of ' + total : '';
        }

        // Hide nav if only one page
        var nav = grid.querySelector('.acc-nav');
        if (nav) {
            nav.style.display = pages <= 1 ? 'none' : '';
        }
    }

    function renderContractsPage() {
        var grid = document.getElementById('contracts-grid');
        var track = grid ? grid.querySelector('.acc-track') : null;
        var empty = document.getElementById('contracts-empty');
        if (!grid || !track || !empty) {
            return;
        }

        track.innerHTML = '';
        if (!contractsCache || contractsCache.length === 0) {
            grid.style.display = 'none';
            empty.style.display = '';
            return;
        }

        empty.style.display = 'none';
        grid.style.display = '';

        var start = (contractsPage - 1) * contractsPerPage;
        var pageItems = contractsCache.slice(start, start + contractsPerPage);

        pageItems.forEach(function (c) {
            track.appendChild(buildContractCard(c));
        });

        updateContractsNav(contractsCache);

        track.querySelectorAll('.manage-contract-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting…';
                fetch('create-billing-portal', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.portal_url) {
                        window.location.href = data.portal_url;
                    } else {
                        alert(data.error || 'An error occurred.');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-cog"></i> Manage subscription';
                    }
                }).catch(function () {
                    alert('Network error.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cog"></i> Manage subscription';
                });
            });
        });

        var gridPrev = grid.querySelector('.acc-prev');
        var gridNext = grid.querySelector('.acc-next');
        if (gridPrev) {
            gridPrev.onclick = function () {
                if (contractsPage > 1) {
                    contractsPage -= 1;
                    renderContractsPage();
                }
            };
        }
        if (gridNext) {
            gridNext.onclick = function () {
                var maxPage = Math.ceil(contractsCache.length / contractsPerPage);
                if (contractsPage < maxPage) {
                    contractsPage += 1;
                    renderContractsPage();
                }
            };
        }
    }

    function renderContracts(contracts) {
        contractsCache = Array.isArray(contracts) ? contracts : [];
        contractsPage = 1;
        renderContractsPage();
    }

    function loadContracts() {
        var skeleton = document.getElementById('contracts-skeleton');
        var grid     = document.getElementById('contracts-grid');
        var empty    = document.getElementById('contracts-empty');
        if (skeleton) skeleton.style.display = '';
        if (grid)     grid.style.display     = 'none';
        if (empty)    empty.style.display    = 'none';

        fetch('contracts-api', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (skeleton) skeleton.style.display = 'none';
            if (Array.isArray(data)) {
                renderContracts(data);
            } else if (data && Array.isArray(data.contracts)) {
                renderContracts(data.contracts);
            } else {
                if (grid) grid.style.display = 'none';
                if (empty) {
                    empty.style.display = '';
                    empty.textContent = 'Unable to load contracts.';
                }
            }
        }).catch(function () {
            if (skeleton) skeleton.style.display = 'none';
            if (grid) grid.style.display = 'none';
            if (empty) {
                empty.style.display = '';
                empty.textContent = 'Unable to load contracts.';
            }
        });
    }

    var invoicesCache = [];
    var invoicesPage = 1;
    var invoicesPerPage = 4;

    function buildInvoiceCard(inv) {
        var status = inv.status || 'Unknown';
        var statusCls = status.toLowerCase().includes('paid') ? 'status-confirmed' : (status.toLowerCase().includes('failed') ? 'status-cancelled' : 'status-pending');
        var title = inv.stripe_invoice_id || inv.id || 'Invoice';
        var amount = (inv.amount_paid != null ? inv.amount_paid : inv.amount_due) || 0;
        var amountStr = '€ ' + parseFloat(amount).toFixed(2);
        var periodStart = inv.period_start || '';
        var periodEnd = inv.period_end || '';
        var dueDate = inv.due_date || '';
        var invoiceUrl = inv.invoice_url || '';
        var receiptUrl = inv.receipt_url || '';

        var el = document.createElement('div');
        el.className = 'acc-card';
        el.setAttribute('role', 'listitem');
        el.innerHTML =
            '<div class="acc-card-icon"><i class="fa-solid fa-receipt"></i></div>' +
            '<div class="acc-card-body">' +
              '<div class="acc-card-title">' + escHtml(title) + '</div>' +
              '<div class="acc-card-meta"><span class="acc-status ' + statusCls + '">' + escHtml(status) + '</span></div>' +
              '<div class="acc-card-amount">' + escHtml(amountStr) + '</div>' +
              ((periodStart || periodEnd) ? '<div class="acc-card-date"><i class="fa-regular fa-calendar"></i> ' + escHtml(periodStart + (periodStart && periodEnd ? ' → ' : '') + periodEnd) + '</div>' : '') +
              (dueDate ? '<div class="acc-card-date"><i class="fa-regular fa-clock"></i> Due ' + escHtml(dueDate) + '</div>' : '') +
              '<div class="acc-card-actions" style="display:flex;gap:5px;justify-content:center;align-items:center;width:100%;border-top:1px solid #ccc;padding-top:10px;flex-wrap:wrap;">' +
                (invoiceUrl ? '<a class="btn-secondary" href="' + escAttr(invoiceUrl) + '" target="_blank" rel="noopener"><i class="fas fa-file-invoice"></i> View invoice</a>' : '') +
                (receiptUrl ? '<a class="btn-secondary" href="' + escAttr(receiptUrl) + '" target="_blank" rel="noopener"><i class="fas fa-download"></i> Receipt</a>' : '') +
              '</div>' +
            '</div>';
        return el;
    }

    function updateInvoicesNav() {
        var grid = document.getElementById('billing-grid');
        if (!grid) return;
        var prevBtn = grid.querySelector('.acc-prev');
        var nextBtn = grid.querySelector('.acc-next');
        var pageInfo = grid.querySelector('.acc-page-info');
        if (!prevBtn || !nextBtn || !pageInfo) return;

        var total = invoicesCache.length;
        var pages = Math.max(1, Math.ceil(total / invoicesPerPage));
        var page = invoicesPage;

        prevBtn.disabled = page <= 1;
        nextBtn.disabled = page >= pages;

        var from = (page - 1) * invoicesPerPage + 1;
        var to = Math.min(page * invoicesPerPage, total);
        pageInfo.textContent = total > 0 ? from + '–' + to + ' of ' + total : '';

        var nav = grid.querySelector('.acc-nav');
        if (nav) {
            nav.style.display = pages <= 1 ? 'none' : '';
        }
    }

    function renderInvoicesPage() {
        var grid = document.getElementById('billing-grid');
        var track = grid ? grid.querySelector('.acc-track') : null;
        var empty = document.getElementById('billing-empty');
        if (!grid || !track || !empty) {
            return;
        }

        track.innerHTML = '';
        if (!invoicesCache || invoicesCache.length === 0) {
            grid.style.display = 'none';
            empty.style.display = '';
            return;
        }

        empty.style.display = 'none';
        grid.style.display = '';

        var start = (invoicesPage - 1) * invoicesPerPage;
        var pageItems = invoicesCache.slice(start, start + invoicesPerPage);

        pageItems.forEach(function (inv) {
            track.appendChild(buildInvoiceCard(inv));
        });

        updateInvoicesNav();

        var gridPrev = grid.querySelector('.acc-prev');
        var gridNext = grid.querySelector('.acc-next');
        if (gridPrev) {
            gridPrev.onclick = function () {
                if (invoicesPage > 1) {
                    invoicesPage -= 1;
                    renderInvoicesPage();
                }
            };
        }
        if (gridNext) {
            gridNext.onclick = function () {
                var maxPage = Math.ceil(invoicesCache.length / invoicesPerPage);
                if (invoicesPage < maxPage) {
                    invoicesPage += 1;
                    renderInvoicesPage();
                }
            };
        }
    }

    function renderInvoices(invoices) {
        invoicesCache = Array.isArray(invoices) ? invoices : [];
        invoicesPage = 1;
        renderInvoicesPage();
    }

    function loadInvoices() {
        var skeleton = document.getElementById('billing-skeleton');
        var grid     = document.getElementById('billing-grid');
        var empty    = document.getElementById('billing-empty');
        if (skeleton) skeleton.style.display = '';
        if (grid)     grid.style.display = 'none';
        if (empty)    empty.style.display = 'none';

        fetch('invoices-api', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (skeleton) skeleton.style.display = 'none';
            if (Array.isArray(data)) {
                renderInvoices(data);
            } else if (data && Array.isArray(data.invoices)) {
                renderInvoices(data.invoices);
            } else {
                if (grid) grid.style.display = 'none';
                if (empty) {
                    empty.style.display = '';
                    empty.textContent = 'Unable to load invoices.';
                }
            }
        }).catch(function () {
            if (skeleton) skeleton.style.display = 'none';
            if (grid) grid.style.display = 'none';
            if (empty) {
                empty.style.display = '';
                empty.textContent = 'Unable to load invoices.';
            }
        });
    }

    var toggle = document.querySelector('#acc-subscription .accordion-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var body    = this.closest('.profile-accordion').querySelector('.accordion-body');
            var chevron = this.querySelector('.accordion-chevron');
            var open    = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', open ? 'false' : 'true');
            body.style.display = open ? 'none' : '';
            if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
            if (!open) loadSubscription();
        });
    }

    window.loadContracts = loadContracts;
    window.loadInvoices = loadInvoices;
})();


(function () {
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(function (tc) { tc.style.display = 'none'; });
            var el = document.getElementById(tab + '-tab');
            if (el) el.style.display = '';
            if (tab === 'contracts') {
                loadContracts();
            }
            if (tab === 'billing') {
                loadInvoices();
            }
        });
    });
})();

(function () {
    var currentSecret = '';

    function postMFA(formType, extra) {
        var fd = new FormData();
        fd.append('form_type', formType);
        if (extra) Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) {
            return r.text().then(function (text) {
                if (!r.ok) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        return { success: false, message: text || 'Server error.' };
                    }
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Unexpected MFA response:', text);
                    return { success: false, message: 'Unexpected server response. Please try again.' };
                }
            });
        });
    }

    function setFeedback(el, msg, isError) {
        if (!el) return;
        el.innerHTML = '<div class="' + (isError ? 'error-message' : 'success-message') + '">' + msg + '</div>';
    }

    var setupBtn = document.getElementById('mfa-setup-btn');
    if (setupBtn) {
        setupBtn.addEventListener('click', function () {
            setupBtn.disabled = true;
            setupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            postMFA('mfa_setup').then(function (data) {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                if (!data.success) { setFeedback(document.getElementById('mfa-feedback'), data.message || 'Error.', true); return; }
                currentSecret = data.secret;
                document.getElementById('mfa-secret-display').textContent = data.secret;
                var qrEl = document.getElementById('mfa-qr-code');
                qrEl.innerHTML = '';
                (function renderQR() {
                    if (typeof QRCode === 'undefined') { setTimeout(renderQR, 100); return; }
                    new QRCode(qrEl, { text: data.otp_url, width: 200, height: 200 });
                })();
                document.getElementById('mfa-setup-panel').style.display = 'block';
                setupBtn.style.display = 'none';
            }).catch(function () {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var enableBtn = document.getElementById('mfa-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            var code = (document.getElementById('mfa-verify-code').value || '').trim();
            if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Please enter a valid 6-digit code.', true);
                return;
            }
            enableBtn.disabled = true;
            enableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            postMFA('mfa_enable', { secret: currentSecret, code: code }).then(function (data) {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-setup-feedback'), '2FA enabled successfully! Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-setup-feedback'), data.message || 'Invalid code.', true);
                }
            }).catch(function () {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var disableBtn = document.getElementById('mfa-disable-btn');
    if (disableBtn) {
        disableBtn.addEventListener('click', async function () {
            var confirmed = true;
            if (typeof showConfirmModal === 'function') {
                confirmed = await showConfirmModal('Are you sure you want to disable 2FA? This will make your account less secure.', 'Disable 2FA');
            } else {
                confirmed = confirm('Are you sure you want to disable 2FA? This will make your account less secure.');
            }
            if (!confirmed) return;

            disableBtn.disabled = true;
            disableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Disabling...';
            postMFA('mfa_disable').then(function (data) {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), '2FA disabled. Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Unable to disable 2FA.', true);
                }
            }).catch(function () {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var otpInput = document.getElementById('mfa-verify-code');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
})();