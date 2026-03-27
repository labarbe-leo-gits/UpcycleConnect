(function () {
    'use strict';

    function fmtDate(str) {
        if (!str) return '';
        var ts = Date.parse(str);
        if (isNaN(ts)) return '';
        var d = new Date(ts);
        return d.toLocaleDateString('en-GB') + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    var ORDER_STATUS  = { 0: 'Pending', 1: 'Confirmed', 2: 'Cancelled' };
    var ORDER_STATUS_CLASS = { 0: 'status-pending', 1: 'status-confirmed', 2: 'status-cancelled' };

    var ANNONCE_STATUS = { 0: 'Available', 1: 'Sold', 2: 'Approved', 3: 'Rejected' };
    var ANNONCE_STATUS_CLASS = { 0: 'status-available', 1: 'status-sold', 2: 'status-approved', 3: 'status-rejected' };

    var PAYOUT_STATUS = { 0: 'Pending', 1: 'Approved', 2: 'Rejected' };
    var PAYOUT_STATUS_CLASS = { 0: 'status-pending', 1: 'status-approved', 2: 'status-rejected' };

    function buildOrderCard(item) {
        var status     = parseInt(item.status ?? 0);
        var statusLbl  = ORDER_STATUS[status]      ?? 'Unknown';
        var statusCls  = ORDER_STATUS_CLASS[status] ?? 'status-pending';
        var amount     = parseFloat(item.amount ?? 0).toFixed(2);
        var title      = item.annonce_title || 'Order';
        var date       = fmtDate(item.created_at);

        var hasRefund   = item.has_refund_request === true;
        var rStatus     = item.refund_request_status != null ? parseInt(item.refund_request_status) : null;
        var rStatusLbl  = rStatus != null ? (REFUND_STATUS[rStatus]       ?? 'Pending') : null;
        var rStatusCls  = rStatus != null ? (REFUND_STATUS_CLASS[rStatus] ?? 'status-pending') : null;

        var refundHtml = '';
        if (status === 1) {
            if (hasRefund) {
                refundHtml = '<span class="acc-refund-tag acc-status ' + rStatusCls + '"><i class="fa-solid fa-rotate-left"></i> Refund ' + escHtml(rStatusLbl) + '</span>';
            } else {
                refundHtml = '<button class="btn-secondary acc-refund-btn" style="padding:6px;" data-order-id="' + escAttr(item.id) + '" data-order-title="' + escAttr(title) + '"><i class="fa-solid fa-rotate-left"></i> Request Refund</button>';
            }
        }

        var el = document.createElement('div');
        el.className = 'acc-card';
        el.setAttribute('role', 'listitem');
        el.innerHTML =
            '<div class="acc-card-icon"><i class="fa-solid fa-box-open"></i></div>' +
            '<div class="acc-card-body">' +
              '<div class="acc-card-title">' + escHtml(title) + '</div>' +
              '<div class="acc-card-meta"><span class="acc-status ' + statusCls + '">' + escHtml(statusLbl) + '</span></div>' +
              '<div class="acc-card-amount">\u20ac ' + amount + ' <span class="acc-ttc-tag">TTC</span></div>' +
              (date ? '<div class="acc-card-date"><i class="fa-regular fa-calendar"></i> ' + escHtml(date) + '</div>' : '') +
              '<div class="acc-card-actions" style="display:flex;gap:5px;justify-content:center;align-items:center;width:100%;border-top:1px solid #ccc;padding-top:10px;flex-wrap:wrap;">' +
                refundHtml +
                '<button class="btn-secondary details-btn" style="padding:6px;" data-order-id="' + escAttr(item.id) + '"><i class="fa-solid fa-info"></i> View Details</button>' +
              '</div>' +
            '</div>';
        return el;
    }

    function buildAnnonceCard(item) {
        var status     = parseInt(item.status ?? 0);
        var statusLbl  = ANNONCE_STATUS[status]      ?? 'Unknown';
        var statusCls  = ANNONCE_STATUS_CLASS[status] ?? 'status-available';
        var priceTTC   = parseFloat(item.price_ttc ?? 0);
        var priceStr   = priceTTC > 0 ? '\u20ac ' + priceTTC.toFixed(2) : 'Free';
        var views      = parseInt(item.view_count ?? 0);
        var date       = fmtDate(item.created_at);

        var el = document.createElement('div');
        el.className = 'acc-card acc-card--annonce';
        el.setAttribute('role', 'listitem');

        var imgHtml = '';
        if (item.image) {
            imgHtml = '<div class="acc-card-thumb"><img src="' + escAttr(item.image) + '" alt="" loading="lazy"></div>';
        } else {
            imgHtml = '<div class="acc-card-thumb acc-card-thumb--placeholder"><i class="fa-solid fa-image"></i></div>';
        }

        el.innerHTML =
            imgHtml +
            '<div class="acc-card-body">' +
              '<div class="acc-card-title">' + escHtml(item.title ?? 'Offer') + '</div>' +
              '<div class="acc-card-meta">' +
                '<span class="acc-status ' + statusCls + '">' + escHtml(statusLbl) + '</span>' +
                '<span class="acc-views"><i class="fa-solid fa-eye"></i> ' + views + '</span>' +
              '</div>' +
              '<div class="acc-card-amount">' + escHtml(priceStr) + ' <span class="acc-ttc-tag">TTC</span></div>' +
              (date ? '<div class="acc-card-date"><i class="fa-regular fa-calendar"></i> ' + escHtml(date) + '</div>' : '') +
            '</div>';
        return el;
    }

    function buildPayoutCard(item) {
        var status     = parseInt(item.status ?? 0);
        var statusLbl  = PAYOUT_STATUS[status]      ?? 'Unknown';
        var statusCls  = PAYOUT_STATUS_CLASS[status] ?? 'status-pending';
        var amount     = parseFloat(item.amount ?? 0).toFixed(2);
        var date       = fmtDate(item.created_at);

        var el = document.createElement('div');
        el.className = 'acc-card';
        el.setAttribute('role', 'listitem');
        el.innerHTML =
            '<div class="acc-card-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>' +
            '<div class="acc-card-body">' +
              '<div class="acc-card-title">Payout request</div>' +
              '<div class="acc-card-meta"><span class="acc-status ' + statusCls + '">' + escHtml(statusLbl) + '</span></div>' +
              '<div class="acc-card-amount">\u20ac ' + amount + '</div>' +
              (date ? '<div class="acc-card-date"><i class="fa-regular fa-calendar"></i> ' + escHtml(date) + '</div>' : '') +
            '</div>';
        return el;
    }

    var REFUND_STATUS      = { 0: 'Pending', 1: 'Approved', 2: 'Rejected' };
    var REFUND_STATUS_CLASS = { 0: 'status-pending', 1: 'status-confirmed', 2: 'status-cancelled' };

    function buildRefundCard(item) {
        var status    = parseInt(item.status ?? 0);
        var statusLbl = REFUND_STATUS[status]       ?? 'Unknown';
        var statusCls = REFUND_STATUS_CLASS[status] ?? 'status-pending';
        var date      = fmtDate(item.created_at);
        var updated   = fmtDate(item.updated_at);
        var reason    = item.reason ?? '';
        var txId      = item.order_transaction ?? null;
        var amount    = item.order_amount != null ? '\u20ac ' + parseFloat(item.order_amount).toFixed(2) : null;

        var el = document.createElement('div');
        el.className = 'acc-card acc-card--refund';
        el.setAttribute('role', 'listitem');
        el.innerHTML =
            '<div class="acc-card-icon"><i class="fa-solid fa-rotate-left"></i></div>' +
            '<div class="acc-card-body">' +
              '<div class="acc-card-title">Refund request</div>' +
              '<div class="acc-card-meta"><span class="acc-status ' + statusCls + '">' + escHtml(statusLbl) + '</span></div>' +
              (amount ? '<div class="acc-card-amount">' + escHtml(amount) + ' <span class="acc-ttc-tag">TTC</span></div>' : '') +
              (txId   ? '<div class="acc-card-date"><i class="fa-solid fa-receipt"></i> ' + escHtml(txId) + '</div>' : '') +
              (reason ? '<div class="acc-card-date acc-card-reason"><i class="fa-solid fa-comment"></i> ' + escHtml(reason.length > 80 ? reason.slice(0, 80) + '\u2026' : reason) + '</div>' : '') +
              (date   ? '<div class="acc-card-date"><i class="fa-regular fa-calendar"></i> Submitted: ' + escHtml(date) + '</div>' : '') +
              (updated && updated !== date ? '<div class="acc-card-date"><i class="fa-regular fa-clock"></i> Updated: ' + escHtml(updated) + '</div>' : '') +
            '</div>';
        return el;
    }

    var BUILDERS = { orders: buildOrderCard, annonces: buildAnnonceCard, payouts: buildPayoutCard, refunds: buildRefundCard };

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function escAttr(str) { return escHtml(str); }

    function initAccordion(root) {
        
        if (root.id === 'acc-subscription') {
            return;
        }

        var section  = root.dataset.section;
        var toggle   = root.querySelector('.accordion-toggle');
        var body     = root.querySelector('.accordion-body');
        var skelRow  = root.querySelector('.acc-skeleton-row');
        var carousel = root.querySelector('.acc-carousel');
        var track    = root.querySelector('.acc-track');
        var prevBtn  = root.querySelector('.acc-prev');
        var nextBtn  = root.querySelector('.acc-next');
        var pageInfo = root.querySelector('.acc-page-info');
        var emptyMsg = root.querySelector('.acc-empty');
        
        if (body) body.style.display = 'none';
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');


        if (!(section in BUILDERS)) {
            if (toggle) {
                toggle.addEventListener('click', function () {
                    var open = this.getAttribute('aria-expanded') === 'true';
                    this.setAttribute('aria-expanded', open ? 'false' : 'true');
                    if (body) body.style.display = open ? 'none' : '';

                    if (root) {
                        if (open) {
                            root.classList.remove('is-open');
                        } else {
                            root.classList.add('is-open');
                        }
                    }
                });
            }
            return;
        }

        var state = { loaded: false, loading: false, page: 1, total: 0 };
        var cache = {};

        function setNavState(data) {
            if (prevBtn) prevBtn.disabled = !data.has_prev;
            if (nextBtn) nextBtn.disabled = !data.has_more;
            if (pageInfo) {
                var from = (data.page - 1) * data.limit + 1;
                var to   = Math.min(data.page * data.limit, data.total);
                pageInfo.textContent = data.total > 0
                    ? from + '\u2013' + to + ' of ' + data.total
                    : '';
            }
        }

        function hide(el)  { el.style.display = 'none'; }
        function show(el, displayVal) { el.style.display = displayVal || 'block'; }

        function renderItems(items, data) {
            track.innerHTML = '';
            if (!items || items.length === 0) {
                hide(carousel);
                show(emptyMsg);
                return;
            }
            hide(emptyMsg);
            show(carousel, 'block');
            var builder = BUILDERS[section];
            items.forEach(function (item) {
                track.appendChild(builder(item));
            });
            setNavState(data);
        }

        function fetchPage(page) {
            if (state.loading) return;
            if (cache[page]) {
                state.page = page;
                renderItems(cache[page].items, cache[page].data);
                return;
            }
            state.loading = true;
            show(skelRow, 'grid');
            hide(carousel);
            hide(emptyMsg);

            var apiUrl = (window.profileSectionApiPath || 'profile-section-api')
                + '?section=' + encodeURIComponent(section)
                + '&page=' + page;

            fetch(apiUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                state.loading = false;
                state.loaded  = true;
                state.page    = page;
                state.total   = data.total || 0;
                cache[page]   = { items: data.items, data: data };
                hide(skelRow);
                renderItems(data.items, data);
            })
            .catch(function () {
                state.loading = false;
                hide(skelRow);
                show(emptyMsg);
                emptyMsg.textContent = 'Unable to load data. Please try again.';
            });
        }

        toggle.addEventListener('click', function () {
            if (root.classList.contains('is-open')) {
                hide(body);
                toggle.setAttribute('aria-expanded', 'false');
                root.classList.remove('is-open');
            } else {
                show(body, 'block');
                toggle.setAttribute('aria-expanded', 'true');
                root.classList.add('is-open');
                if (!state.loaded && !state.loading) {
                    fetchPage(1);
                }
            }
        });

        if (prevBtn) prevBtn.addEventListener('click', function () { fetchPage(state.page - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { fetchPage(state.page + 1); });
    }

    document.querySelectorAll('.profile-accordion').forEach(initAccordion);

    function collapseAll() {
        document.querySelectorAll('.profile-accordion').forEach(function(root) {
            var body = root.querySelector('.accordion-body');
            var toggle = root.querySelector('.accordion-toggle');
            if (body) body.style.display = 'none';
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
        });
    }
    collapseAll();
    document.addEventListener('DOMContentLoaded', collapseAll);

})();

(function () {
    'use strict';

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtDate(str) {
        if (!str) return '—';
        var ts = Date.parse(str);
        if (isNaN(ts)) return str;
        var d = new Date(ts);
        return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    var ORDER_STATUS       = { 0: 'Pending', 1: 'Confirmed', 2: 'Cancelled' };
    var ORDER_STATUS_CLS   = { 0: 'status-pending', 1: 'status-confirmed', 2: 'status-cancelled' };
    var ANNONCE_STATUS     = { 0: 'Available', 1: 'Sold', 2: 'Approved', 3: 'Rejected' };
    var ANNONCE_STATUS_CLS = { 0: 'status-available', 1: 'status-sold', 2: 'status-approved', 3: 'status-rejected' };

    function statusBadge(label, cls) {
        return '<span class="acc-status ' + esc(cls) + '">' + esc(label) + '</span>';
    }

    function openModal(id) {
        var overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeModal(id) {
        var overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    var detailsOverlay = document.getElementById('order-details-modal');
    var detailsSkel    = document.getElementById('order-details-skeleton');
    var detailsContent = document.getElementById('order-details-content');
    var detailsActions = document.getElementById('order-details-actions');
    var detailsError   = document.getElementById('od-error-msg');

    function showDetailsSkeleton() {
        if (detailsSkel)    { detailsSkel.style.display    = 'flex'; }
        if (detailsContent) { detailsContent.style.display = 'none'; }
        if (detailsActions) { detailsActions.style.display = 'none'; }
        if (detailsError)   { detailsError.style.display   = 'none'; }
    }

    function showDetailsContent() {
        if (detailsSkel)    { detailsSkel.style.display    = 'none'; }
        if (detailsContent) { detailsContent.style.display = 'block'; }
        if (detailsActions) { detailsActions.style.display = 'flex'; }
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val ?? '—';
    }

    function setHtml(id, html) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function populateOrderDetails(data) {
        var order   = data.order   || {};
        var annonce = data.annonce || null;

        setText('od-transaction-id', order.transaction_id ?? '—');
        setText('od-order-id',       order.id            ?? '—');
        setText('od-amount',         order.amount != null ? '€ ' + parseFloat(order.amount).toFixed(2) : '—');

        var oStatus = parseInt(order.status ?? 0);
        setHtml('od-status', statusBadge(ORDER_STATUS[oStatus] ?? 'Unknown', ORDER_STATUS_CLS[oStatus] ?? 'status-pending'));
        setText('od-created-at', fmtDate(order.created_at));
        setText('od-updated-at', fmtDate(order.updated_at));

        var txBtn = document.getElementById('od-copy-txid');
        if (txBtn) txBtn.dataset.copy = order.transaction_id ?? '';
        var oidBtn = document.getElementById('od-copy-oid');
        if (oidBtn) oidBtn.dataset.copy = order.id ?? '';

        var annonceSection = document.getElementById('od-annonce-section');
        var noAnnonce      = document.getElementById('od-no-annonce');

        if (annonce) {
            if (annonceSection) annonceSection.style.display = 'block';
            if (noAnnonce)      noAnnonce.style.display      = 'none';

            setText('od-annonce-title',       annonce.title       ?? '—');
            setText('od-annonce-description', annonce.description ?? '—');
            setText('od-annonce-price',       annonce.price != null ? '€ ' + parseFloat(annonce.price).toFixed(2) : '—');
            setText('od-annonce-material',    annonce.type_materiaux ?? '—');

            var aStatus = parseInt(annonce.status ?? 0);
            setHtml('od-annonce-status', statusBadge(ANNONCE_STATUS[aStatus] ?? 'Unknown', ANNONCE_STATUS_CLS[aStatus] ?? 'status-available'));
        } else {
            if (annonceSection) annonceSection.style.display = 'none';
            if (noAnnonce)      noAnnonce.style.display      = 'block';
        }
    }

    function loadOrderDetails(orderId) {
        showDetailsSkeleton();
        openModal('order-details-modal');

        fetch('profile-order-api?action=details&order_id=' + encodeURIComponent(orderId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) {
                showDetailsContent();
                if (detailsError) {
                    detailsError.textContent = data.error;
                    detailsError.style.display = 'block';
                }
                return;
            }
            populateOrderDetails(data);
            showDetailsContent();
        })
        .catch(function () {
            showDetailsContent();
            if (detailsError) {
                detailsError.textContent = 'Unable to load order details. Please try again.';
                detailsError.style.display = 'block';
            }
        });
    }

    ['close-order-details-modal', 'close-order-details-btn'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', function () { closeModal('order-details-modal'); });
    });

    if (detailsOverlay) {
        detailsOverlay.addEventListener('click', function (e) {
            if (e.target === detailsOverlay) closeModal('order-details-modal');
        });
    }

    var refundOverlay    = document.getElementById('refund-modal');
    var refundForm       = document.getElementById('refund-request-form');
    var refundLoading    = document.getElementById('refund-loading');
    var refundFormWrap   = document.getElementById('refund-form-wrap');
    var refundFeedback   = document.getElementById('refund-feedback');
    var refundOrderIdEl  = document.getElementById('refund-order-id');
    var refundOrderHint  = document.getElementById('refund-order-hint');
    var refundReasonEl   = document.getElementById('refund-reason');
    var refundSubmitBtn  = document.getElementById('refund-submit-btn');

    function openRefundModal(orderId, orderTitle) {
        if (refundOrderIdEl)  refundOrderIdEl.value     = orderId;
        if (refundOrderHint)  refundOrderHint.textContent = 'Order: ' + (orderTitle || orderId);
        if (refundReasonEl)   refundReasonEl.value       = '';
        if (refundFeedback)   refundFeedback.innerHTML   = '';
        if (refundFormWrap)   refundFormWrap.style.display = 'block';
        if (refundLoading)    refundLoading.style.display  = 'none';
        if (refundSubmitBtn)  { refundSubmitBtn.disabled = false; refundSubmitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Request'; }
        openModal('refund-modal');
    }

    ['close-refund-modal', 'cancel-refund-modal'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', function () { closeModal('refund-modal'); });
    });

    if (refundOverlay) {
        refundOverlay.addEventListener('click', function (e) {
            if (e.target === refundOverlay) closeModal('refund-modal');
        });
    }

    if (refundForm) {
        refundForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var orderId = (refundOrderIdEl ? refundOrderIdEl.value : '').trim();
            var reason  = (refundReasonEl  ? refundReasonEl.value  : '').trim();

            if (!reason) {
                if (refundFeedback) refundFeedback.innerHTML = '<div class="error-message">Please provide a reason for the refund.</div>';
                return;
            }

            if (refundFormWrap)  refundFormWrap.style.display  = 'none';
            if (refundLoading)   refundLoading.style.display   = 'block';

            fetch('profile-order-api?action=refund&order_id=' + encodeURIComponent(orderId), {
                method:  'POST',
                headers: {
                    'Content-Type':      'application/json',
                    'X-Requested-With':  'XMLHttpRequest'
                },
                body: JSON.stringify({ reason: reason })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (refundLoading) refundLoading.style.display = 'none';
                if (data.success) {
                    closeModal('refund-modal');
                    window.location.reload();
                } else {
                    if (refundFormWrap) refundFormWrap.style.display = 'block';
                    if (refundFeedback) refundFeedback.innerHTML = '<div class="error-message">' + esc(data.error || 'Unable to submit request.') + '</div>';
                }
            })
            .catch(function () {
                if (refundLoading)   refundLoading.style.display   = 'none';
                if (refundFormWrap)  refundFormWrap.style.display  = 'block';
                if (refundFeedback)  refundFeedback.innerHTML = '<div class="error-message">Network error. Please try again.</div>';
            });
        });
    }

    document.addEventListener('click', function (e) {

        var detailsBtn = e.target.closest('.details-btn');
        if (detailsBtn) {
            var orderId = detailsBtn.dataset.orderId || '';
            if (orderId) loadOrderDetails(orderId);
            return;
        }

        var refundBtn = e.target.closest('.acc-refund-btn');
        if (refundBtn) {
            var rOrderId    = refundBtn.dataset.orderId    || '';
            var rOrderTitle = refundBtn.dataset.orderTitle || '';
            if (rOrderId) openRefundModal(rOrderId, rOrderTitle);
            return;
        }

        var copyBtn = e.target.closest('.od-copy-btn');
        if (copyBtn && copyBtn.dataset.copy) {
            navigator.clipboard.writeText(copyBtn.dataset.copy).catch(function () {});
            var icon = copyBtn.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid fa-check';
                setTimeout(function () { icon.className = 'fa-solid fa-copy'; }, 1500);
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        ['order-details-modal', 'refund-modal'].forEach(closeModal);
    });

})();
