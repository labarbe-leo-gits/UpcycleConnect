<?php
$title    = 'Dashboard';
$extraCss = ['../../assets/css/subscription.css'];

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    require_once '../../config/db.php';
    require_once '../../includes/auth.php';
    requireUserType(2);
} else {
    include_once '../../includes/pro-header.php';
    echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
    if (ob_get_level()) { @ob_flush(); }
    @flush();
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../../assets/js/dashboard.js" defer></script>

<main class="dashboard-page">

    <div class="page-header">
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
        <a href="subscription" class="btn btn-outline btn-sm" id="dash-sub-btn" style="display:none">
            <i class="fas fa-crown"></i>
            <span id="dash-sub-btn-label">My subscription</span>
        </a>
    </div>

    <div id="dash-skeleton" class="dash-skeleton-wrap">
        <div class="dash-kpi-row">
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="kpi-card"><div class="kpi-icon-wrap skeleton"></div><div><span class="kpi-value skeleton skeleton-text">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><span class="kpi-label skeleton skeleton-text" style="min-width:80px">&nbsp;</span></div></div>
            <?php endfor; ?>
        </div>
        <div class="dash-charts-grid">
            <div class="chart-panel skeleton" style="border:none"></div>
            <div class="chart-panel skeleton" style="border:none"></div>
            <div class="chart-panel skeleton" style="border:none"></div>
        </div>
    </div>

    <div id="dash-premium" class="dash-content" style="display:none">
        <div class="dash-kpi-row">
            <div class="kpi-card"><i class="fas fa-tags kpi-icon"></i><div><span class="kpi-value" id="kpi-annonces">--</span><span class="kpi-label">Published listings</span></div></div>
            <div class="kpi-card"><i class="fas fa-euro-sign kpi-icon"></i><div><span class="kpi-value" id="kpi-revenue">--</span><span class="kpi-label">Total revenue</span></div></div>
            <div class="kpi-card"><i class="fas fa-leaf kpi-icon"></i><div><span class="kpi-value" id="kpi-score">--</span><span class="kpi-label">Upcycling score</span></div></div>
            <div class="kpi-card"><i class="fas fa-weight-hanging kpi-icon"></i><div><span class="kpi-value" id="kpi-weight">--</span><span class="kpi-label">Materials processed</span></div></div>
        </div>
        <div class="dash-charts-grid">
            <div class="chart-panel" id="panel-materials">
                <h2 class="panel-title"><i class="fas fa-boxes"></i> Materials</h2>
                <div class="chart-canvas-wrap" id="materials-chart-wrap"><canvas id="chart-materials"></canvas></div>
                <p class="empty-state" id="materials-empty" style="display:none">No material data yet.</p>
            </div>
            <div class="chart-panel" id="panel-eco">
                <h2 class="panel-title"><i class="fas fa-seedling"></i> Ecological impact</h2>
                <div class="eco-metrics-stack">
                    <div class="eco-metric-card"><span class="eco-m-label"><i class="fas fa-cloud"></i> CO₂ saved</span><span class="eco-m-value" id="eco-co2">--</span><span class="eco-m-unit">kg estimated</span></div>
                    <div class="eco-metric-card"><span class="eco-m-label"><i class="fas fa-recycle"></i> Materials valorised</span><span class="eco-m-value" id="eco-weight">--</span><span class="eco-m-unit">kg total weight</span></div>
                    <div class="eco-metric-card highlight"><span class="eco-m-label"><i class="fas fa-star"></i> Upcycling score</span><span class="eco-m-value" id="eco-score">--</span><span class="eco-m-unit">updated on every sale</span></div>
                </div>
            </div>
            <div class="chart-panel panel-alerts" id="panel-alerts">
                <h2 class="panel-title"><i class="fas fa-bell"></i> Priority alerts <span class="panel-badge">72h</span></h2>
                <div id="alerts-content" class="alerts-scroll"></div>
            </div>
        </div>
    </div>

    <div id="dash-free" class="dash-content" style="display:none">
        <div class="dash-kpi-row">
            <div class="kpi-card"><i class="fas fa-tags kpi-icon"></i><div><span class="kpi-value" id="free-kpi-annonces">--</span><span class="kpi-label">Published listings</span></div></div>
            <div class="kpi-card"><i class="fas fa-euro-sign kpi-icon"></i><div><span class="kpi-value" id="free-kpi-revenue">--</span><span class="kpi-label">Total revenue</span></div></div>
            <div class="kpi-card"><i class="fas fa-leaf kpi-icon"></i><div><span class="kpi-value" id="free-kpi-score">--</span><span class="kpi-label">Upcycling score</span></div></div>
            <div class="kpi-card kpi-locked" title="Available with Premium"><i class="fas fa-lock kpi-icon"></i><div><span class="kpi-value">--</span><span class="kpi-label">Advanced insights</span></div></div>
        </div>
        <div class="dash-freemium-body">
            <div class="freemium-left">
                <div class="teaser-header">
                    <i class="fas fa-crown"></i>
                    <h2>Unlock your full dashboard</h2>
                    <p>Detailed analytics, ecological impact tracking and priority collection alerts.</p>
                </div>
                <button id="btn-subscribe" class="btn btn-primary btn-lg"><i class="fas fa-crown"></i> Go Premium</button>
                <p class="billing-note" style="margin-top:10px">Cancel anytime</p>
            </div>
            <div class="dash-plan-compare">
                <div class="plan free">
                    <h3>Free</h3>
                    <p class="price">€0 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Post listings</li>
                        <li><i class="fas fa-check"></i> Access to containers</li>
                        <li><i class="fas fa-check"></i> Community forum</li>
                        <li><i class="fas fa-check"></i> Messaging</li>
                        <li><i class="fas fa-check"></i> Basic statistics</li>
                        <li class="locked"><i class="fas fa-lock"></i> Advanced dashboards</li>
                        <li class="locked"><i class="fas fa-lock"></i> Ecological analysis</li>
                        <li class="locked"><i class="fas fa-lock"></i> Material stats</li>
                        <li class="locked"><i class="fas fa-lock"></i> Priority alerts</li>
                    </ul>
                    <span class="current-plan">Your current plan</span>
                </div>
                <div class="plan premium">
                    <div class="popular-badge">Recommended</div>
                    <h3><i class="fas fa-crown"></i> Premium</h3>
                    <p class="price" id="free-price-display">€29.99 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Everything in Free</li>
                        <li><i class="fas fa-check"></i> Advanced dashboards</li>
                        <li><i class="fas fa-check"></i> Ecological impact analysis</li>
                        <li><i class="fas fa-check"></i> Material statistics</li>
                        <li><i class="fas fa-check"></i> Priority collection alerts</li>
                        <li><i class="fas fa-check"></i> Priority support</li>
                    </ul>
                    <button id="btn-subscribe-2" class="btn btn-primary btn-lg"><i class="fas fa-crown"></i> Go Premium</button>
                    <p class="billing-note">Cancel anytime</p>
                </div>
            </div>
        </div>
    </div>

</main>

<?php if (!$isAjax) include_once '../../includes/footer.php'; ?>