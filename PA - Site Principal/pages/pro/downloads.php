<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dl_verify') {
    session_start();
    header('Content-Type: application/json');
    $answer = intval($_POST['answer'] ?? -999);
    if (isset($_SESSION['dl_captcha']) && $answer === $_SESSION['dl_captcha']) {
        $_SESSION['dl_captcha_ok'] = true;
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

$title   = "Downloads";
$extraCss = ["../../assets/css/downloads.css"];
include_once '../../includes/pro-header.php';

$num1 = rand(2, 9);
$num2 = rand(1, 9);
$_SESSION['dl_captcha']    = $num1 + $num2;
$_SESSION['dl_captcha_ok'] = false;

// Answer Sheet for Troll Mode :
// Integral of 2x from 0 to 1 = 1
// Integral of sin(x) from 0 to pi = 2
// Integral of x from 0 to 2 = 2
// Integral of 2x from 0 to 3 = 9
// Integral of 1/x from 1 to e = 1
// Integral of cos(x) from 0 to pi/2 = 1

$trollMode = (rand(1, 3) === 1);
$integrals = [
    ['lower' => '0',   'upper' => '1',   'expr' => '2x',      'answer' => 1],
    ['lower' => '0',   'upper' => '&pi;','expr' => 'sin(x)',  'answer' => 2],
    ['lower' => '0',   'upper' => '2',   'expr' => 'x',       'answer' => 2],
    ['lower' => '0',   'upper' => '3',   'expr' => '2x',      'answer' => 9],
    ['lower' => '1',   'upper' => 'e',   'expr' => '1/x',     'answer' => 1],
    ['lower' => '0',   'upper' => '&pi;/2', 'expr' => 'cos(x)', 'answer' => 1],
];
$integral = $integrals[array_rand($integrals)];
?>

<main>
<div class="dl-wrapper">

    <div id="dl-skeleton">

        <div class="dl-skel-hero">
            <div class="skeleton skel-app-icon"></div>
            <div class="skel-app-info">
                <div class="skeleton skel-title"></div>
                <div class="skeleton skel-subtitle"></div>
                <div class="dl-skel-badges">
                    <div class="skeleton skel-tag"></div>
                    <div class="skeleton skel-tag"></div>
                    <div class="skeleton skel-tag"></div>
                </div>
            </div>
        </div>

        <div class="dl-skel-features">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="dl-skel-feat-card">
                <div class="skeleton skel-feat-icon"></div>
                <div class="skeleton skel-feat-title"></div>
                <div class="skeleton skel-feat-line"></div>
                <div class="skeleton skel-feat-line skel-feat-line--short"></div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="dl-skel-bottom">
            <div class="skeleton skel-captcha-box"></div>
            <div class="skeleton skel-dl-btn"></div>
        </div>

    </div>

    <div id="dl-content" class="hidden">

        <div class="dl-hero-card">
            <div class="dl-app-icon">
                <i class="fa-solid fa-recycle"></i>
            </div>
            <div class="dl-app-info">
                <h1 class="dl-app-name" data-i18n="pro.downloads.title">UpcycleConnect</h1>
                <p class="dl-app-tagline" data-i18n="pro.downloads.tagline">The professional toolkit for craftsmen &amp; artisans - manage jobs, offers, and clients on the go.</p>
                <div class="dl-app-meta">
                    <span class="dl-badge"><i class="fa-brands fa-android"></i> <span data-i18n="pro.downloads.android">Android</span></span>
                    <span class="dl-badge"><i class="fa-solid fa-code-branch"></i> <span data-i18n="pro.downloads.version_info">v1.0.0</span></span>
                    <span class="dl-badge"><i class="fa-solid fa-shield-halved"></i> <span data-i18n="pro.downloads.safe_verified">Safe &amp; verified</span></span>
                </div>
            </div>
        </div>

        <div class="dl-features">
            <div class="dl-feature-card">
                <div class="dl-feat-icon"><i class="fa-solid fa-barcode"></i></div>
                <h3 data-i18n="pro.downloads.container_retrieval">Container Retrieval</h3>
                <p data-i18n="pro.downloads.container_retrieval_description">Scan the barcode on any UpcycleConnect container to instantly retrieve your reserved materials on the go.</p>
            </div>
            <div class="dl-feature-card">
                <div class="dl-feat-icon"><i class="fa-solid fa-hammer"></i></div>
                <h3 data-i18n="pro.downloads.project_showcase">Project Showcase</h3>
                <p data-i18n="pro.downloads.project_showcase_description">Create, track and publish your upcycling projects - from raw materials to finished creations.</p>
            </div>
            <div class="dl-feature-card">
                <div class="dl-feat-icon"><i class="fa-solid fa-bell"></i></div>
                <h3 data-i18n="pro.downloads.push_notifications">Push Notifications</h3>
                <p data-i18n="pro.downloads.push_notifications_description">Get real-time alerts for new matching offers, container availability, and order updates.</p>
            </div>
        </div>

        <!-- Requirements -->
        <div class="dl-requirements">
            <h2><i class="fa-solid fa-circle-info"></i> <span data-i18n="pro.downloads.requirements">Requirements</span></h2>
            <ul>
                <li><i class="fa-brands fa-android"></i> <span data-i18n="pro.downloads.android_requirement">Android 8.0 (Oreo) or later</span></li>
                <li><i class="fa-solid fa-microchip"></i> <span data-i18n="pro.downloads.min_ram">Minimum 2 GB RAM</span></li>
                <li><i class="fa-solid fa-hard-drive"></i> <span data-i18n="pro.downloads.free_storage">~80 MB free storage</span></li>
                <li><i class="fa-solid fa-wifi"></i> <span data-i18n="pro.downloads.internet_required">Internet connection required</span></li>
                <li><i class="fa-solid fa-camera"></i> <span data-i18n="pro.downloads.camera_access">Camera access (barcode scanning)</span></li>
                <li><i class="fa-solid fa-location-dot"></i> <span data-i18n="pro.downloads.location_access">Location access (container nearby)</span></li>
            </ul>
        </div>

        <?php if ($trollMode): ?>
        <div class="dl-captcha-card dl-captcha-troll" id="dl-captcha-card">
            <div class="dl-captcha-header">
                <div class="dl-captcha-robot-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <h3 data-i18n="pro.downloads.verify_human">Verify you're human</h3>
                    <p data-i18n="pro.downloads.captcha_intro">Just a quick challenge before your download.</p>
                </div>
            </div>
            <div class="dl-integral-display">
                <span class="dl-integral-symbol">&#x222B;</span>
                <span class="dl-integral-bounds">
                    <sup><?= $integral['upper'] ?></sup>
                    <sub><?= $integral['lower'] ?></sub>
                </span>
                <span class="dl-integral-expr"><?= $integral['expr'] ?> dx</span>
                <span class="dl-integral-eq">= ?</span>
            </div>
            <div class="dl-captcha-row">
                <input
                    type="text"
                    id="captcha-answer"
                    class="dl-captcha-input"
                    placeholder="Answer…"
                        data-i18n-placeholder="pro.downloads.answer_placeholder"
                        autocomplete="off"
                        inputmode="decimal"
                    >
                    <button id="captcha-verify-btn" class="dl-verify-btn" type="button">
                        <i class="fa-solid fa-check"></i> <span data-i18n="pro.downloads.verify">Verify</span>
                    </button>
                </div>
                <button id="captcha-skip-btn" class="dl-skip-btn" type="button">
                    <i class="fa-solid fa-face-dizzy"></i> <span data-i18n="pro.downloads.cant_solve">I can't solve this</span>
                </button>
                <p id="captcha-msg" class="dl-captcha-msg" aria-live="polite"></p>
            </div>
            <script>window._dlTroll = true; window._dlTrollAnswer = <?= intval($integral['answer']) ?>;</script>
        <?php else: ?>
        <div class="dl-captcha-card" id="dl-captcha-card">
            <div class="dl-captcha-header">
                <div class="dl-captcha-robot-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <h3 data-i18n="pro.downloads.verify_human">Verify you're human</h3>
                    <p data-i18n="pro.downloads.captcha_solve">Solve this quick challenge to unlock your download.</p>
                </div>
            </div>
            <p class="dl-math-label">
                <span data-i18n="pro.downloads.math_prompt">What is</span> <strong><?= $num1 ?></strong> + <strong><?= $num2 ?></strong>&nbsp;?
            </p>
            <div class="dl-captcha-row">
                <input
                    type="number"
                    id="captcha-answer"
                    class="dl-captcha-input"
                    placeholder="Answer…"
                    data-i18n-placeholder="pro.downloads.answer_placeholder"
                    min="0"
                    max="99"
                    autocomplete="off"
                    inputmode="numeric"
                >
                <button id="captcha-verify-btn" class="dl-verify-btn" type="button">
                    <i class="fa-solid fa-check"></i> <span data-i18n="pro.downloads.verify">Verify</span>
                </button>
            </div>
            <p id="captcha-msg" class="dl-captcha-msg" aria-live="polite"></p>
        </div>
        <script>window._dlTroll = false;</script>
        <?php endif; ?>

        <div class="dl-action">
            <a id="download-btn"
               href="#"
               class="dl-download-btn locked"
               aria-disabled="true"
               tabindex="-1">
                <i class="fa-solid fa-download"></i>
                <span class="btn-text">
                    <span data-i18n="pro.downloads.download_apk">Download APK</span>
                    <small class="btn-sub" data-i18n="pro.downloads.version_label">v1.0.0 · Android 8.0+</small>
</div>
</main>

<script src="../../assets/js/downloads.js"></script>

<?php
include_once '../../includes/footer.php';
?>
