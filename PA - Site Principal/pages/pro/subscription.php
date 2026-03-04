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
        <h1>Premium Subscription</h1>
        <p class="subtitle">Unlock advanced tools for your business</p>
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
                <span>Premium active</span>
            </div>
            <p>You have access to all advanced UpcycleConnect features.</p>
            <button
                id="btn-manage"
                class="btn btn-outline"
                data-url="create-billing-portal"
            >
                <i class="fas fa-cog"></i> Manage my subscription
            </button>
        </section>

        <section class="features-grid">
            <h2>Your premium features</h2>
            <div class="features">
                <div class="feature unlocked">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Advanced dashboards</h3>
                    <p>Visualise your performance and business growth.</p>
                    <a href="dashboard" class="btn btn-sm">Access</a>
                </div>
                <div class="feature unlocked">
                    <i class="fas fa-leaf"></i>
                    <h3>Ecological impact analysis</h3>
                    <p>Precisely track your carbon footprint and upcycling score.</p>
                    <a href="dashboard#ecology" class="btn btn-sm">Access</a>
                </div>
                <div class="feature unlocked">
                    <i class="fas fa-boxes"></i>
                    <h3>Material statistics</h3>
                    <p>Overview of materials available in your area.</p>
                    <a href="dashboard#materials" class="btn btn-sm">Access</a>
                </div>
                <div class="feature unlocked">
                    <i class="fas fa-bell"></i>
                    <h3>Priority alerts</h3>
                    <p>Get collection alerts tailored to your trade first.</p>
                    <a href="notifications" class="btn btn-sm">Access</a>
                </div>
            </div>
        </section>
    </div>

    <div id="sub-freemium" class="hidden">
        <section class="freemium-banner">
            <div class="plan-comparison">

                <div class="plan free">
                    <h2>Free</h2>
                    <p class="price">€0 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Post listings</li>
                        <li><i class="fas fa-check"></i> Access to containers</li>
                        <li><i class="fas fa-check"></i> Community forum</li>
                        <li><i class="fas fa-check"></i> Messaging</li>
                        <li class="locked"><i class="fas fa-lock"></i> Advanced dashboards</li>
                        <li class="locked"><i class="fas fa-lock"></i> Detailed ecological analysis</li>
                        <li class="locked"><i class="fas fa-lock"></i> Material statistics</li>
                        <li class="locked"><i class="fas fa-lock"></i> Priority alerts</li>
                    </ul>
                    <span class="current-plan">Your current plan</span>
                </div>

                <div class="plan premium">
                    <div class="popular-badge">Recommended</div>
                    <h2><i class="fas fa-crown"></i> Premium</h2>
                    <p class="price" id="price-display">€29.99 / month</p>
                    <ul>
                        <li><i class="fas fa-check"></i> Everything in Free</li>
                        <li><i class="fas fa-check"></i> Advanced dashboards</li>
                        <li><i class="fas fa-check"></i> Detailed ecological impact analysis</li>
                        <li><i class="fas fa-check"></i> Available material statistics</li>
                        <li><i class="fas fa-check"></i> Priority collection alerts</li>
                        <li><i class="fas fa-check"></i> Priority support</li>
                    </ul>
                    <button id="btn-subscribe" class="btn btn-primary btn-lg">
                        <i class="fas fa-crown"></i> Go Premium
                    </button>
                    <p class="billing-note">Cancel anytime</p>
                </div>

            </div>
        </section>
    </div>
</main>

<?php if (!$isAjax) include_once '../../includes/footer.php'; ?>
