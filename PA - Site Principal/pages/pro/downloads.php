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
                <h1 class="dl-app-name">UpcycleConnect</h1>
                <p class="dl-app-tagline">The professional toolkit for craftsmen &amp; artisans — manage jobs, offers, and clients on the go.</p>
                <div class="dl-app-meta">
                    <span class="dl-badge"><i class="fa-brands fa-android"></i> Android</span>
                    <span class="dl-badge"><i class="fa-solid fa-code-branch"></i> v1.0.0</span>
                    <span class="dl-badge"><i class="fa-solid fa-shield-halved"></i> Safe &amp; verified</span>
                </div>
            </div>
        </div>

        <div class="dl-features">
            <div class="dl-feature-card">
                <div class="dl-feat-icon"><i class="fa-solid fa-barcode"></i></div>
                <h3>Container Retrieval</h3>
                <p>Scan the barcode on any UpcycleConnect container to instantly retrieve your reserved materials on the go.</p>
            </div>
            <div class="dl-feature-card">
                <div class="dl-feat-icon"><i class="fa-solid fa-hammer"></i></div>
                <h3>Project Showcase</h3>
                <p>Create, track and publish your upcycling projects — from raw materials to finished creations.</p>
            </div>
            <div class="dl-feature-card">
                <div class="dl-feat-icon"><i class="fa-solid fa-bell"></i></div>
                <h3>Push Notifications</h3>
                <p>Get real-time alerts for new matching offers, container availability, and order updates.</p>
            </div>
        </div>

        <!-- Requirements -->
        <div class="dl-requirements">
            <h2><i class="fa-solid fa-circle-info"></i> Requirements</h2>
            <ul>
                <li><i class="fa-brands fa-android"></i> Android 8.0 (Oreo) or later</li>
                <li><i class="fa-solid fa-microchip"></i> Minimum 2 GB RAM</li>
                <li><i class="fa-solid fa-hard-drive"></i> ~80 MB free storage</li>
                <li><i class="fa-solid fa-wifi"></i> Internet connection required</li>
                <li><i class="fa-solid fa-camera"></i> Camera access (barcode scanning)</li>
                <li><i class="fa-solid fa-location-dot"></i> Location access (container nearby)</li>
            </ul>
        </div>

        <?php if ($trollMode): ?>
        <div class="dl-captcha-card dl-captcha-troll" id="dl-captcha-card">
            <div class="dl-captcha-header">
                <div class="dl-captcha-robot-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <h3>Verify you're human</h3>
                    <p>Just a quick challenge before your download.</p>
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
                    autocomplete="off"
                    inputmode="decimal"
                >
                <button id="captcha-verify-btn" class="dl-verify-btn" type="button">
                    <i class="fa-solid fa-check"></i> Verify
                </button>
            </div>
            <button id="captcha-skip-btn" class="dl-skip-btn" type="button">
                <i class="fa-solid fa-face-dizzy"></i> I can't solve this
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
                    <h3>Verify you're human</h3>
                    <p>Solve this quick challenge to unlock your download.</p>
                </div>
            </div>
            <p class="dl-math-label">
                What is <strong><?= $num1 ?></strong> + <strong><?= $num2 ?></strong>&nbsp;?
            </p>
            <div class="dl-captcha-row">
                <input
                    type="number"
                    id="captcha-answer"
                    class="dl-captcha-input"
                    placeholder="Answer…"
                    min="0"
                    max="99"
                    autocomplete="off"
                    inputmode="numeric"
                >
                <button id="captcha-verify-btn" class="dl-verify-btn" type="button">
                    <i class="fa-solid fa-check"></i> Verify
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
                    <span>Download APK</span>
                    <small class="btn-sub">v1.0.0 · Android 8.0+</small>
                </span>
            </a>
        </div>

    </div>

</div>
</main>

<script src="../../assets/js/downloads.js"></script>

<?php
include_once '../../includes/footer.php';
?>
