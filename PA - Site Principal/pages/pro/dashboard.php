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
        <h1><i class="fas fa-tachometer-alt"></i> <span data-i18n="pro.dashboard.title">Dashboard</span></h1>
        <a href="subscription" class="btn btn-outline btn-sm" id="dash-sub-btn" style="display:none">
            <i class="fas fa-crown"></i>
            <span id="dash-sub-btn-label" data-i18n="pro.dashboard.my_subscription">My subscription</span>
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
            <div class="kpi-card"><i class="fas fa-tags kpi-icon"></i><div><span class="kpi-value" id="kpi-annonces">--</span><span class="kpi-label" data-i18n="pro.dashboard.published_listings">Published listings</span></div></div>
            <div class="kpi-card"><i class="fas fa-euro-sign kpi-icon"></i><div><span class="kpi-value" id="kpi-revenue">--</span><span class="kpi-label" data-i18n="pro.dashboard.total_revenue">Total revenue</span></div></div>
            <div class="kpi-card"><i class="fas fa-leaf kpi-icon"></i><div><span class="kpi-value" id="kpi-score">--</span><span class="kpi-label" data-i18n="pro.dashboard.upcycling_score">Upcycling score</span></div></div>
            <div class="kpi-card"><i class="fas fa-weight-hanging kpi-icon"></i><div><span class="kpi-value" id="kpi-weight">--</span><span class="kpi-label" data-i18n="pro.dashboard.materials_processed">Materials processed</span></div></div>
        </div>
        <div class="dash-charts-grid">
            <div class="chart-panel" id="panel-materials">
                <h2 class="panel-title"><i class="fas fa-boxes"></i> <span data-i18n="pro.dashboard.materials">Materials</span></h2>
                <div class="chart-canvas-wrap" id="materials-chart-wrap"><canvas id="chart-materials"></canvas></div>
                <p class="empty-state" id="materials-empty" style="display:none" data-i18n="pro.dashboard.no_material_data">No material data yet.</p>
            </div>
            <div class="chart-panel" id="panel-eco">
                <h2 class="panel-title"><i class="fas fa-seedling"></i> <span data-i18n="pro.dashboard.ecological_impact">Ecological impact</span></h2>
                <div class="eco-metrics-stack">
                    <div class="eco-metric-card"><span class="eco-m-label"><i class="fas fa-cloud"></i> <span data-i18n="pro.dashboard.co2_saved">CO₂ saved</span></span><span class="eco-m-value" id="eco-co2">--</span><span class="eco-m-unit" data-i18n="pro.dashboard.kg_estimated">kg estimated</span></div>
                    <div class="eco-metric-card"><span class="eco-m-label"><i class="fas fa-recycle"></i> <span data-i18n="pro.dashboard.materials_valorised">Materials valorised</span></span><span class="eco-m-value" id="eco-weight">--</span><span class="eco-m-unit" data-i18n="pro.dashboard.kg_total_weight">kg total weight</span></div>
                    <div class="eco-metric-card highlight"><span class="eco-m-label"><i class="fas fa-star"></i> <span data-i18n="pro.dashboard.upcycling_score">Upcycling score</span></span><span class="eco-m-value" id="eco-score">--</span><span class="eco-m-unit" data-i18n="pro.dashboard.updated_on_every_sale">updated on every sale</span></div>
                </div>
            </div>
            <div class="chart-panel panel-alerts" id="panel-alerts">
                <h2 class="panel-title"><i class="fas fa-bell"></i> <span data-i18n="pro.dashboard.priority_alerts">Priority alerts</span> <span class="panel-badge">72h</span></h2>
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
                    <h2 data-i18n="pro.dashboard.unlock_full_dashboard">Unlock your full dashboard</h2>
                    <p data-i18n="pro.dashboard.detailed_analytics">Detailed analytics, ecological impact tracking and priority collection alerts.</p>
                </div>
                <button id="btn-subscribe" class="btn btn-primary btn-lg"><i class="fas fa-crown"></i> <span data-i18n="pro.dashboard.go_premium">Go Premium</span></button>
                <p class="billing-note" style="margin-top:10px" data-i18n="pro.dashboard.cancel_anytime">Cancel anytime</p>
            </div>
            <div class="dash-plan-compare">
                <div class="plan free">
                    <h3 data-i18n="pro.dashboard.free">Free</h3>
                    <p class="price">€0 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.post_listings">Post listings</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.access_to_containers">Access to containers</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.community_forum">Community forum</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.messaging">Messaging</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.basic_statistics">Basic statistics</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.dashboard.advanced_dashboards">Advanced dashboards</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.dashboard.ecological_analysis">Ecological analysis</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.dashboard.material_statistics">Material stats</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.dashboard.priority_alerts">Priority alerts</span></li>
                    </ul>
                    <span class="current-plan" data-i18n="pro.dashboard.your_current_plan">Your current plan</span>
                </div>
                <div class="plan premium">
                    <div class="popular-badge" data-i18n="pro.dashboard.recommended">Recommended</div>
                    <h3><i class="fas fa-crown"></i> <span data-i18n="pro.dashboard.premium">Premium</span></h3>
                    <p class="price" id="free-price-display">€29.99 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.everything_in_free">Everything in Free</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.advanced_dashboards">Advanced dashboards</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.ecological_impact_analysis">Ecological impact analysis</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.material_statistics">Material statistics</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.priority_collection_alerts">Priority collection alerts</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.dashboard.priority_support">Priority support</span></li>
                    </ul>
                    <button id="btn-subscribe-2" class="btn btn-primary btn-lg"><i class="fas fa-crown"></i> <span data-i18n="pro.dashboard.go_premium">Go Premium</span></button>
                    <p class="billing-note" data-i18n="pro.dashboard.cancel_anytime">Cancel anytime</p>
                </div>
            </div>
        </div>
    </div>

</main>

<?php if (!$isAjax) include_once '../../includes/footer.php'; ?>