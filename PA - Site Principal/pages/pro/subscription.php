<?php
$title    = 'Premium Subscription';
$extraCss = ['../../assets/css/subscription.css'];
$extraJs  = ['../../assets/js/subscription.js'];

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

<main class="pro-main subscription-page">
    <div class="page-header">
        <h1 data-i18n="pro.subscription.title">Premium Subscription</h1>
        <p class="subtitle" data-i18n="pro.subscription.subtitle">Unlock advanced tools for your business</p>
    </div>

    <div id="sub-loading">
        <div class="plan-comparison">
            <div class="skeleton skeleton-plan-card"></div>
            <div class="skeleton skeleton-plan-card"></div>
        </div>
    </div>

    <div id="sub-premium" class="hidden">
        <section class="premium-status active">
            <div class="status-badge">
                <i class="fas fa-crown"></i>
                <span data-i18n="pro.subscription.premium_active">Premium active</span>
            </div>
            <p data-i18n="pro.subscription.access_advanced_features">You have access to all advanced UpcycleConnect features.</p>
            <button
                id="btn-manage"
                class="btn btn-outline"
                data-url="create-billing-portal"
            >
                <i class="fas fa-cog"></i> <span data-i18n="pro.subscription.manage_subscription">Manage my subscription</span>
            </button>
        </section>

        <section class="features-grid">
            <h2 data-i18n="pro.subscription.your_premium_features">Your premium features</h2>
            <div class="features">
                <div class="feature unlocked">
                    <i class="fas fa-chart-bar"></i>
                    <h3 data-i18n="pro.subscription.advanced_dashboards">Advanced dashboards</h3>
                    <p data-i18n="pro.subscription.advanced_dashboards_description">Visualise your performance and business growth.</p>
                    <a href="dashboard" class="btn btn-sm" data-i18n="pro.subscription.access">Access</a>
                </div>
                <div class="feature unlocked">
                    <i class="fas fa-leaf"></i>
                    <h3 data-i18n="pro.subscription.ecological_impact_analysis">Ecological impact analysis</h3>
                    <p data-i18n="pro.subscription.ecological_impact_description">Precisely track your carbon footprint and upcycling score.</p>
                    <a href="dashboard#ecology" class="btn btn-sm" data-i18n="pro.subscription.access">Access</a>
                </div>
                <div class="feature unlocked">
                    <i class="fas fa-boxes"></i>
                    <h3 data-i18n="pro.subscription.material_statistics">Material statistics</h3>
                    <p data-i18n="pro.subscription.material_statistics_description">Overview of materials available in your area.</p>
                    <a href="dashboard#materials" class="btn btn-sm" data-i18n="pro.subscription.access">Access</a>
                </div>
                <div class="feature unlocked">
                    <i class="fas fa-bell"></i>
                    <h3 data-i18n="pro.subscription.priority_alerts">Priority alerts</h3>
                    <p data-i18n="pro.subscription.priority_alerts_description">Get collection alerts tailored to your trade first.</p>
                    <a href="notifications" class="btn btn-sm" data-i18n="pro.subscription.access">Access</a>
                </div>
            </div>
        </section>
    </div>

    <div id="sub-freemium" class="hidden"></div>

    <section class="freemium-banner">
        <div class="subscription-intro">
            <h2 data-i18n="pro.subscription.choose_plan">Choose a plan</h2>
            <p data-i18n="pro.subscription.choose_plan_description">Pick the tier that matches your activity. You can edit these plans later from the admin portal.</p>
        </div>

        <div id="tiers-grid" class="tiers-grid"></div>

        <div class="subscription-note">
            <p data-i18n="pro.subscription.cancel_anytime">Cancel anytime</p>
        </div>
    </section>
</main>

<?php if (!$isAjax) include_once '../../includes/footer.php'; ?>
