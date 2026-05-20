<?php
$title = "Revenue Reports";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

include_once '../../includes/admin-header.php';

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();

?>

<div class="container" id="main-content" style="visibility:hidden;">
    <h2 class="admin-page-title">Revenue Reports</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" type="button" onclick="openReportModal()">
            <i class="fa-solid fa-file-lines"></i> Generate Report
        </button>
    </div>

    <div id="current-month-stats-container" style="margin-bottom:20px;">
        <div class="service-item">
            <div class="service-header">
                <h3><i class="fa-solid fa-chart-line"></i> Current Month Overview</h3>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;padding:12px 0;">
                <div>
                    <p class="service-location" style="color:#666;font-size:12px;">SUBSCRIPTION REVENUE</p>
                    <p class="service-description" id="current-subscription">0€</p>
                </div>
                <div>
                    <p class="service-location" style="color:#666;font-size:12px;">COMMISSION REVENUE</p>
                    <p class="service-description" id="current-commission">0€</p>
                </div>
                <div>
                    <p class="service-location" style="color:#666;font-size:12px;">PARTNERSHIP REVENUE</p>
                    <p class="service-description" id="current-partnership">0€</p>
                </div>
                <div>
                    <p class="service-location" style="color:#666;font-size:12px;">TRAINING REVENUE</p>
                    <p class="service-description" id="current-training">0€</p>
                </div>
            </div>
            <div style="padding:12px;background:#e8f5e9;border-radius:5px;margin-top:12px;">
                <p class="service-location" style="font-weight:bold;">Total This Month: <span id="current-total">0€</span></p>
            </div>
        </div>
    </div>

    <h2 class="admin-page-title" style="margin-top:20px;">Historical Reports</h2>
    <div id="reports-container" class="admin-list">
        <div class="loader"></div>
    </div>
</div>

<div class="add-modal" id="report-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" onclick="closeReportModal()">&times;</span>
        <h2>Generate Revenue Report</h2>
        <form id="report-form">
            <div class="field">
                <label for="report-from">From Date</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" id="report-from" class="form-control" required>
                </div>
            </div>
            <div class="field">
                <label for="report-to">To Date</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" id="report-to" class="form-control" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeReportModal()">Cancel</button>
                <button type="submit" class="add-offer-button">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= base_url('assets/js/admin-reports.js') ?>"></script>

<?php include_once '../../includes/admin-footer.php'; ?>
