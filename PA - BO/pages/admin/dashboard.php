<?php
$title = "Dashboard";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

include_once '../../includes/admin-header.php';

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();


?>

<div class="container" id="main-content" style="margin-top:40px;visibility:hidden;">
    <div class="portal-grid">
        <div class="portal-card blue">
            <h3><i class="fa-solid fa-users"></i> User count</h3>
            <p id="user-count">0</p>
            <p class="dashboard-small">New accounts today: 0</p>
            <p class="dashboard-small" id="user-delta">+</p>
        </div>
        <div class="portal-card green">
            <h3><i class="fa-solid fa-warehouse"></i> Containers</h3>
            <p id="container-count">0</p>
            <p class="dashboard-small">New deposits today: 0</p>
            <p class="dashboard-small" id="container-delta">+</p>
        </div>
        <div class="portal-card yellow">
            <h3><i class="fa-solid fa-money-bill-wave"></i> Income</h3>
            <p id="income-total">0.00 &euro;</p>
            <p class="dashboard-small">Today: 0.00 &euro;</p>
            <p class="dashboard-small" id="income-delta">+</p>
        </div>
        <div class="portal-card red">
            <h3><i class="fa-solid fa-file-lines"></i> Projects (UpDoc)</h3>
            <p id="project-count">0</p>
            <p class="dashboard-small">AI generated: 0%</p>
            <p class="dashboard-small" id="project-delta">+</p>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:40px;margin-top:40px;">
        <div style="flex:1 1 400px;min-width:300px;height:320px;display:flex;flex-direction:column;">
            <canvas id="pie-chart"></canvas>
            <span style="text-align:center;margin-top:8px;font-weight:600;">Material repartition</span>
        </div>
        <div style="flex:1 1 400px;min-width:300px;height:320px;display:flex;flex-direction:column;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                <label for="activity-range-select" style="margin:0;font-weight:600;">Activity range:</label>
                <select id="activity-range-select" style="padding:4px 8px;border:1px solid #ccc;border-radius:4px;">
                    <option value="daily">Daily</option>
                    <option value="weekly" selected>Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="annually">Annually</option>
                </select>

                <label for="activity-file-select" style="margin:0;font-weight:600;">Activity file:</label>
                <select id="activity-file-select" style="padding:4px 8px;border:1px solid #ccc;border-radius:4px;">
                    <option value="login" selected>login.log</option>
                    <option value="register">register.log</option>
                </select>
            </div>
            <canvas id="line-chart"></canvas>
            <span id="line-chart-title" style="text-align:center;margin-top:8px;font-weight:600;">Users activity over time (weekly)</span>
        </div>
    </div>
</div>

<script>
window.dashboardData = {};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script src="/PA/PA%20-%20BO/assets/js/admin-dashboard.js" defer></script>

<?php
include_once '../../includes/footer.php';
?>