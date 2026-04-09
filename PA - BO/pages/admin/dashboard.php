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
        <div class="portal-card purple">
            <h3><i class="fa-solid fa-bullhorn"></i> Annonces</h3>
            <p id="annonce-count">0</p>
            <p class="dashboard-small detail"><i class="fa-solid fa-list"></i> Total listings in catalog: <strong>0</strong></p>
            <p class="dashboard-small detail"><i class="fa-solid fa-check"></i> Listings are live</p>
        </div>
        <div class="portal-card teal">
            <h3><i class="fa-solid fa-box-open"></i> Deposits</h3>
            <p id="deposit-count">0</p>
            <p class="dashboard-small detail"><i class="fa-solid fa-hourglass-half"></i> Pending requests: <strong>0</strong></p>
            <p class="dashboard-small detail"><i class="fa-solid fa-user-clock"></i> Awaiting review</p>
        </div>
        <div class="portal-card orange">
            <h3><i class="fa-solid fa-calendar-day"></i> Events</h3>
            <p id="event-count">0</p>
            <p class="dashboard-small detail"><i class="fa-solid fa-calendar-plus"></i> Upcoming events: <strong>0</strong></p>
            <p class="dashboard-small detail"><i class="fa-solid fa-calendar-check"></i> Total events</p>
        </div>
        <div class="portal-card pink">
            <h3><i class="fa-solid fa-user-clock"></i> Pending regs</h3>
            <p id="pending-count">0</p>
            <p class="dashboard-small detail"><i class="fa-solid fa-user-clock"></i> Awaiting validation</p>
            <p class="dashboard-small detail"><i class="fa-solid fa-circle-info"></i> Review queue</p>
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

                <label for="graph-live-toggle" style="margin:0;display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                    <input type="checkbox" id="graph-live-toggle" style="width:16px;height:16px;">
                    Live graph mode
                </label>
            </div>
            <canvas id="line-chart"></canvas>
            <span id="line-chart-title" style="text-align:center;margin-top:8px;font-weight:600;">Users activity over time (weekly)</span>
        </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:40px;margin-top:40px;">
        <div style="flex:1 1 400px;min-width:300px;height:320px;display:flex;flex-direction:column;">
            <canvas id="db-table-chart"></canvas>
            <span style="text-align:center;margin-top:8px;font-weight:600;">Database table counts</span>
        </div>
        <div style="flex:1 1 400px;min-width:300px;display:flex;flex-direction:column;">
            <div class="portal-card gray" style="flex:1;min-height:0;padding:20px;">
                <h3><i class="fa-solid fa-server"></i> Server & system</h3>
                <div id="server-summary" class="server-stats-grid">Loading server info...</div>
            </div>
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