function setReportModalOpen(isOpen) {
    const modal = document.getElementById('report-modal');
    modal.classList.toggle('is-open', isOpen);
    modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    document.body.classList.toggle('modal-open', isOpen);
}

function openReportModal() { setReportModalOpen(true); }
function closeReportModal() { setReportModalOpen(false); }

function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const datePart = String(dateStr).split('T')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    const d = new Date(dateStr);
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
            <div class="service-buttons">
                <button type="button" class="btn-secondary" onclick="viewReport('${report.id}')">View Details</button>
                <button type="button" class="btn-secondary" onclick="exportReport('${report.id}')">Export</button>
            </div>
        </div>
    `).join('');
}

async function viewReport(reportId) {
    alert('Report details feature coming soon');
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

        if (response.ok) {
            closeReportModal();
            loadCurrentMonth();
            loadReports();
            document.getElementById('report-form').reset();
        }
    } catch (error) {
        console.error('Error generating report:', error);
        alert('Error generating report');
    }
});

document.getElementById('main-content').style.visibility = 'visible';
document.getElementById('initial-loader').style.display = 'none';
loadCurrentMonth();
loadReports();