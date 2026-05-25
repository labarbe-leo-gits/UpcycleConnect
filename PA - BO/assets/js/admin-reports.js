function setModalOpen(modalId, isOpen) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

function openModal(modalId) { setModalOpen(modalId, true); }
function closeModal(modalId) { setModalOpen(modalId, false); }

function setReportModalOpen(isOpen) { setModalOpen('report-modal', isOpen); }
function openReportModal() { openModal('report-modal'); }
function closeReportModal() { closeModal('report-modal'); }

let pendingDeleteReportId = null;

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const normalized = String(dateStr).replace(' ', 'T');
    const datePart = normalized.split('T')[0];
    const parts = datePart.split('-');
    if (parts.length === 3 && parts[0].length === 4) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    const d = new Date(normalized);
    if (isNaN(d)) return dateStr;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}
async function loadCurrentMonth() {
    try {
        const response = await fetch('/pages/api/current-month-revenue.php');
        const data = await response.json();
        
        document.getElementById('current-subscription').innerText = data.subscription_revenue.toFixed(2) + '€';
        document.getElementById('current-commission').innerText = data.commission_revenue.toFixed(2) + '€';
        document.getElementById('current-partnership').innerText = data.partnership_revenue.toFixed(2) + '€';
        document.getElementById('current-training').innerText = data.training_revenue.toFixed(2) + '€';
        document.getElementById('current-total').innerText = data.total_revenue.toFixed(2) + '€';
    } catch (error) {
        console.error('Error loading current month:', error);
    }
}

async function loadReports() {
    try {
        const response = await fetch('/pages/api/revenue-reports.php');
        const reports = await response.json();
        renderReports(reports);
    } catch (error) {
        console.error('Error loading reports:', error);
    }
}

function renderReports(reports) {
    const container = document.getElementById('reports-container');
    if (!reports || reports.length === 0) {
        container.innerHTML = '<div class="service-item" style="grid-column:1/-1;text-align:center;"><h3 style="justify-content:center;">No revenue reports found</h3><p class="service-description">Generate a report to see historical data.</p></div>';
        return;
    }

    container.innerHTML = reports.map(report => `
        <div class="service-item">
            <div class="service-header">
                <h3><i class="fa-solid fa-file-invoice-dollar"></i> Report: ${formatDateDisplay(report.report_period_start)} to ${formatDateDisplay(report.report_period_end)}</h3>
                <span class="badge badge-oauth">${report.total_revenue.toFixed(2)}€</span>
            </div>
            <div style="padding:8px 0;">
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:12px;">
                    <div>
                        <strong>Subscriptions</strong>
                        <p>${report.subscription_revenue.toFixed(2)}€ (${report.subscription_count} contracts)</p>
                    </div>
                    <div>
                        <strong>Commissions</strong>
                        <p>${report.commission_revenue.toFixed(2)}€ (${report.commission_count} transactions)</p>
                    </div>
                    <div>
                        <strong>Partnerships</strong>
                        <p>${report.partnership_revenue.toFixed(2)}€ (${report.partnership_count} campaigns)</p>
                    </div>
                    <div>
                        <strong>Training</strong>
                        <p>${report.training_revenue.toFixed(2)}€ (${report.training_participants} participants)</p>
                    </div>
                </div>
                <p class="service-location" style="color:#999;font-size:12px;">Generated: ${formatDateDisplay(report.generated_at)}</p>
            </div>
            <div class="service-buttons report-actions" style="display:grid;
grid-template-columns:repeat(3,1fr);">
                <button type="button" class="btn-secondary icon-only" style="width:100%;" title="View details" onclick="viewReport('${report.id}')">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn-secondary icon-only" style="width:100%;" title="Export report" onclick="exportReport('${report.id}')">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn-danger icon-only" style="width:100%;" title="Delete report" onclick="openDeleteReportModal('${report.id}')">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    `).join('');
}

async function viewReport(reportId) {
    try {
        const response = await fetch(`/pages/api/revenue-report.php?id=${reportId}`);
        if (!response.ok) {
            console.error('Failed to load report details', response.statusText);
            alert('Unable to load report details.');
            return;
        }

        const report = await response.json();
        document.getElementById('report-details-title').innerText = `Revenue report details`;
        document.getElementById('report-details-period').innerText = `Period: ${formatDateDisplay(report.report_period_start)} - ${formatDateDisplay(report.report_period_end)}`;
        document.getElementById('report-details-generated').innerText = formatDateDisplay(report.generated_at);
        document.getElementById('report-details-start').innerText = formatDateDisplay(report.report_period_start);
        document.getElementById('report-details-end').innerText = formatDateDisplay(report.report_period_end);
        document.getElementById('report-details-total').innerText = report.total_revenue.toFixed(2) + '€';
        document.getElementById('report-details-subscription').innerText = `${report.subscription_revenue.toFixed(2)}€ - ${report.subscription_count} contracts`;
        document.getElementById('report-details-commission').innerText = `${report.commission_revenue.toFixed(2)}€ - ${report.commission_count} transactions`;
        document.getElementById('report-details-partnership').innerText = `${report.partnership_revenue.toFixed(2)}€ - ${report.partnership_count} campaigns`;
        document.getElementById('report-details-training').innerText = `${report.training_revenue.toFixed(2)}€ - ${report.training_participants} participants`;

        openModal('report-details-modal');
    } catch (error) {
        console.error('Error loading report details:', error);
        alert('Error loading report details');
    }
}

function openDeleteReportModal(reportId) {
    pendingDeleteReportId = reportId;
    document.getElementById('delete-report-message').innerText = 'Delete this revenue report? This action cannot be undone.';
    openModal('delete-report-modal');
}

async function confirmDeleteReport() {
    if (!pendingDeleteReportId) {
        alert('No report selected for deletion.');
        return;
    }

    try {
        const response = await fetch(`/pages/api/revenue-report.php?id=${pendingDeleteReportId}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (!response.ok || result.error) {
            console.error('Error deleting report:', result);
            alert(result.error || 'Error deleting report');
            return;
        }

        closeModal('delete-report-modal');
        pendingDeleteReportId = null;
        //alert('Revenue report deleted successfully.');
        await loadReports();
    } catch (error) {
        console.error('Error deleting report:', error);
        alert('Error deleting report');
    }
}

async function exportReport(reportId) {
    try {
        const response = await fetch(`/pages/api/revenue-report.php?id=${reportId}`);
        const report = await response.json();
        
        const csv = [
            ['Revenue Report'],
            [`Period: ${report.report_period_start} to ${report.report_period_end}`],
            [],
            ['Revenue Type', 'Amount (€)', 'Count/Participants'],
            ['Subscriptions', report.subscription_revenue.toFixed(2), report.subscription_count],
            ['Commissions', report.commission_revenue.toFixed(2), report.commission_count],
            ['Partnerships', report.partnership_revenue.toFixed(2), report.partnership_count],
            ['Training', report.training_revenue.toFixed(2), report.training_participants],
            [],
            ['Total Revenue', report.total_revenue.toFixed(2), '']
        ].map(row => row.join(',')).join('\n');
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `revenue-report-${report.report_period_start}-to-${report.report_period_end}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error exporting report:', error);
    }
}

document.getElementById('report-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const response = await fetch('/pages/api/revenue-report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                report_period_start: document.getElementById('report-from').value,
                report_period_end: document.getElementById('report-to').value
            })
        });

        const result = await response.json();
        if (!response.ok || result.error || !result.id) {
            console.error('Error generating report:', result);
            alert(result.error || 'Error generating report');
            return;
        }

        alert(`Revenue report generated for ${formatDateDisplay(result.report_period_start)} to ${formatDateDisplay(result.report_period_end)}.`);
        closeReportModal();
        loadCurrentMonth();
        await loadReports();
        document.getElementById('report-form').reset();
    } catch (error) {
        console.error('Error generating report:', error);
        alert('Error generating report');
    }
});

document.getElementById('main-content').style.visibility = 'visible';
document.getElementById('initial-loader').style.display = 'none';
loadCurrentMonth();
loadReports();