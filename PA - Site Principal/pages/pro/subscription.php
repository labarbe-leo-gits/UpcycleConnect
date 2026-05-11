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

    <div id="sub-freemium" class="hidden">
        <section class="freemium-banner">
            <div class="plan-comparison">

                <div class="plan free">
                    <h2 data-i18n="pro.subscription.free">Free</h2>
                    <p class="price">€0 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.post_listings">Post listings</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.access_to_containers">Access to containers</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.community_forum">Community forum</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.messaging">Messaging</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.subscription.advanced_dashboards">Advanced dashboards</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.subscription.detailed_ecological_analysis">Detailed ecological analysis</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.subscription.material_statistics">Material statistics</span></li>
                        <li class="locked"><i class="fas fa-lock"></i> <span data-i18n="pro.subscription.priority_alerts">Priority alerts</span></li>
                    </ul>
                    <span class="current-plan" data-i18n="pro.subscription.your_current_plan">Your current plan</span>
                </div>

                <div class="plan premium">
                    <div class="popular-badge" data-i18n="pro.subscription.recommended">Recommended</div>
                    <h2><i class="fas fa-crown"></i> <span data-i18n="pro.subscription.premium">Premium</span></h2>
                    <p class="price" id="price-display">€29.99 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.everything_in_free">Everything in Free</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.advanced_dashboards">Advanced dashboards</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.detailed_ecological_impact_analysis">Detailed ecological impact analysis</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.available_material_statistics">Available material statistics</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.priority_collection_alerts">Priority collection alerts</span></li>
                        <li><i class="fas fa-check"></i> <span data-i18n="pro.subscription.priority_support">Priority support</span></li>
                    </ul>
                    <button id="btn-subscribe" class="btn btn-primary btn-lg">
                        <i class="fas fa-crown"></i> <span data-i18n="pro.subscription.go_premium">Go Premium</span>
                    </button>
                    <p class="billing-note" data-i18n="pro.subscription.cancel_anytime">Cancel anytime</p>
                </div>

            </div>
        </section>
    </div>
</main>

<?php if (!$isAjax) include_once '../../includes/footer.php'; ?>
