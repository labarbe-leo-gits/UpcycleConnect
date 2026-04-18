<?php
$title = "User Profile";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getLoggedInUser() : null;
trackLastPage();

if ($isLoggedIn) {
    if ($user['user_type'] == 1) {
        include_once '../../includes/customers-header.php';
    } else {
        include_once '../../includes/pro-header.php';
    }
} else {
    include_once '../../includes/header.php';
}

$targetUsername = isset($_GET['username']) ? trim($_GET['username']) : null;
$profileError = '';
$publicUser = null;
$publicOffers = [];
$publicProjects = [];
$showPublicProjects = false;
$isCurrentUser = false;

if ($targetUsername) {
    $userResponse = askAPI('profile/' . urlencode($targetUsername), 'GET');
    $publicUser = json_decode($userResponse, true);
    if (!is_array($publicUser) || isset($publicUser['error']) || empty($publicUser['id'])) {
        $profileError = is_array($publicUser) && isset($publicUser['error']) ? $publicUser['error'] : 'Unable to load user profile.';
        $publicUser = null;
    } else {
        $detailsResponse = askAPI('/users/' . urlencode($publicUser['id']), 'GET');
        $detailsUser = json_decode($detailsResponse, true);
        if (is_array($detailsUser) && !isset($detailsUser['error'])) {
            $publicUser = array_merge($publicUser, $detailsUser);
        }

        $offersResponse = askAPI('/users/' . urlencode($publicUser['id']) . '/annonces', 'GET');
        $offersData = json_decode($offersResponse, true);
        if (is_array($offersData)) {
            foreach ($offersData as $offer) {
                if (isset($offer['status']) && (int)$offer['status'] === 0) {
                    $publicOffers[] = $offer;
                }
            }
        }

        if ((int)($publicUser['user_type'] ?? 0) === 2) {
            $showPublicProjects = true;
            $projectsResponse = askAPI('/users/' . urlencode($publicUser['id']) . '/projects', 'GET');
            $projectsData = json_decode($projectsResponse, true);
            if (is_array($projectsData)) {
                foreach ($projectsData as $project) {
                    if (isset($project['status']) && (int)$project['status'] === 1) {
                        $publicProjects[] = $project;
                    }
                }
            }
        }

        $friendRequestExists = false;
        if ($isLoggedIn && !$isCurrentUser) {
            $statusResponse = askAPI('/friends/status/' . urlencode($publicUser['id']), 'GET');
            $statusData = json_decode($statusResponse, true);
            if (is_array($statusData) && isset($statusData['exists']) && $statusData['exists']) {
                $friendRequestExists = true;
            }
        }
    }
}

$userTypes = [1 => 'Customer', 2 => 'Professional', 3 => 'Admin', 4 => 'Employee'];
?>

<link rel="stylesheet" href="../../assets/css/customers.css">
<link rel="stylesheet" href="../../assets/css/profile-badges.css">
<link rel="stylesheet" href="../../assets/css/user_profile.css">
<?php if ($showPublicProjects): ?>
<link rel="stylesheet" href="../../assets/css/updoc.css">
<?php endif; ?>

<main class="container my-5">
    <div id="error-alert" class="alert alert-danger d-none"></div>

    <?php if (!$targetUsername): ?>
        <div class="alert alert-warning">No username provided in the URL. Usage: ?username=johndoe</div>
    <?php elseif ($profileError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($profileError) ?></div>
    <?php else: ?>
        <?php
            $firstName = $publicUser['first_name'] ?? '';
            $lastName = $publicUser['last_name'] ?? '';
            $typeLabel = $userTypes[$publicUser['user_type'] ?? 0] ?? 'Unknown';
            $score = (int)($publicUser['upcycling_score'] ?? 0);
            $userXp = (int)($publicUser['user_xp'] ?? 0);
            $userLevel = min(10, max(0, floor($userXp / 1200)));
            $levelProgress = $userXp % 1200;
            $levelProgressPercent = round(($levelProgress / 1200) * 100, 2);
            $isCurrentUser = $isLoggedIn && isset($user['username']) && $user['username'] === ($publicUser['username'] ?? '');
            $showFriendButton = $isLoggedIn && !$isCurrentUser && empty($friendRequestExists);
            $badgeList = isset($publicUser['badges']) && is_array($publicUser['badges']) ? $publicUser['badges'] : [];
            $profilePictureUrl = '';
            if (!empty($publicUser['oauth_provider'])) {
                $pictureResponse = askAPI('/users/' . urlencode($publicUser['id']) . '/profile-picture', 'GET');
                $pictureData = json_decode($pictureResponse, true);
                if (is_array($pictureData) && !empty($pictureData['profile_picture_url'])) {
                    $profilePictureUrl = $pictureData['profile_picture_url'];
                }
            }
        ?>

        <div class="profile-card">
            <div class="profile-header-flex">
                <div class="profile-picture-section<?= $profilePictureUrl !== '' ? ' loaded' : '' ?>">
                    <div class="img-spinner" aria-hidden="true"></div>
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($profilePictureUrl) ?>" alt="Profile Picture" class="profile-pic-large" id="profile-pic-preview">
                    <?php else: ?>
                        <img src="../../../files/uploads/user/<?= htmlspecialchars($publicUser['profile_picture'] ?? 'defaultUser.png') ?>"
                            alt="Profile Picture" class="profile-pic-large" id="profile-pic-preview">
                    <?php endif; ?>
                </div>
                <div class="profile-info-section">
                    <h2>Profile of <?= htmlspecialchars($publicUser['username'] ?? '') ?></h2>
                    <?php if ($firstName !== '' || $lastName !== ''): ?>
                        <p class="balance-note" style="margin-top:-.25rem;margin-bottom:.75rem;">
                            <i class="fa-solid fa-user"></i>
                            <?= htmlspecialchars(trim($firstName . ' ' . $lastName)) ?>
                        </p>
                    <?php endif; ?>
                    <div class="profile-fields">
                        <div class="profile-field-row">
                            <span class="profile-label">User ID:</span>
                            <span><?= htmlspecialchars($publicUser['id'] ?? '') ?></span>
                            <button type="button" class="btn-copy" data-copy="<?= htmlspecialchars($publicUser['id'] ?? '') ?>" title="Copy User ID"><i class="fa-solid fa-copy"></i></button>
                        </div>
                        <div class="profile-field-row">
                            <span class="profile-label">Username:</span>
                            <span><?= htmlspecialchars($publicUser['username'] ?? '') ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span class="profile-label">First name:</span>
                            <span><?= htmlspecialchars($firstName !== '' ? $firstName : 'N/A') ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span class="profile-label">Last name:</span>
                            <span><?= htmlspecialchars($lastName !== '' ? $lastName : 'N/A') ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span class="profile-label">User type:</span>
                            <span><?= htmlspecialchars($typeLabel) ?></span>
                        </div>
                        <div class="profile-field-row">
                            <span class="profile-label">Company:</span>
                            <span><?= htmlspecialchars($publicUser['company_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <?php if (!empty($showFriendButton) && empty($friendRequestExists)): ?>
                        <button class="btn-primary btn-inline mt-3" id="btn-add-friend" style="width: auto; min-width: 0; min-height: 34px; display: inline-flex; align-items: center; gap: 0.5rem; padding: 8px 14px; margin-top:15px;">
                            <i class="fas fa-user-plus"></i> Become Friend
                        </button>
                    <?php endif; ?>
                    <?php if ($isLoggedIn && !$isCurrentUser && !empty($friendRequestExists)): ?>
                        <button class="btn-primary btn-inline mt-3" id="btn-start-discussion" style="width: auto; min-width: 0; min-height: 34px; display: inline-flex; align-items: center; gap: 0.5rem; padding: 8px 14px; margin-top:15px; margin-left: 10px;">
                            <i class="fas fa-comments"></i> Message
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <hr>
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="general">General</button>
                <button class="tab-btn" data-tab="badges">Badges</button>
                <button class="tab-btn" data-tab="upcyclingScore">Upcycling Score</button>
            </div>

            <div class="tab-content" id="general-tab">
                <div class="profile-fields">
                    <div class="profile-field-row">
                        <span class="profile-label">Joined:</span>
                        <span><?= htmlspecialchars(!empty($publicUser['created_at']) ? date('d/m/Y', strtotime($publicUser['created_at'])) : 'N/A') ?></span>
                    </div>
                </div>

                <div class="profile-accordion" id="acc-annonces" data-section="annonces">
                    <button class="accordion-toggle" type="button" aria-expanded="false">
                        <span><i class="fa-solid fa-tag"></i> Available Offers</span>
                        <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                    </button>
                    <div class="accordion-body" style="display:none">
                        <div class="acc-skeleton-row" aria-hidden="true" style="display:none">
                            <div class="acc-skel-card"></div>
                            <div class="acc-skel-card"></div>
                            <div class="acc-skel-card"></div>
                            <div class="acc-skel-card"></div>
                        </div>
                        <div class="acc-carousel" style="display:none">
                            <div class="acc-track" role="list"></div>
                            <div class="acc-nav">
                                <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                                <span class="acc-page-info"></span>
                                <button class="btn-secondary acc-next" type="button">See more <i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <p class="acc-empty" style="display:none">This user has no available offers at the moment.</p>
                    </div>
                </div>

                <?php if ($showPublicProjects): ?>
                    <div class="profile-accordion" id="acc-projects" data-section="projects">
                        <button class="accordion-toggle" type="button" aria-expanded="false">
                            <span><i class="fa-solid fa-book-open"></i> UpDoc Projects</span>
                            <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                        </button>
                        <div class="accordion-body" style="display:none">
                            <div class="acc-skeleton-row" aria-hidden="true" style="display:none">
                                <div class="acc-skel-card"></div>
                                <div class="acc-skel-card"></div>
                                <div class="acc-skel-card"></div>
                                <div class="acc-skel-card"></div>
                            </div>
                            <div class="acc-carousel" style="display:none">
                                <div class="acc-track" role="list"></div>
                                <div class="acc-nav">
                                    <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                                    <span class="acc-page-info"></span>
                                    <button class="btn-secondary acc-next" type="button">See more <i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                            </div>
                            <p class="acc-empty" style="display:none">This user has no published UpDoc projects at the moment.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="badges-tab" style="display:none;">
                <?php if (empty($badgeList)): ?>
                    <p style="color:#888;">No public badges are available for this user.</p>
                <?php else: ?>
                    <div class="badges-grid" style="display:flex;flex-wrap:wrap;gap:1.5em;align-items:center;">
                        <?php
                            $defaultBadge = '/PA/files/badges/default.png';
                            foreach ($badgeList as $badge):
                                $imgPath = '/PA/files/badges/' . rawurlencode($badge['file_name'] ?? 'default.png');
                        ?>
                            <div class="badge-card">
                                <img data-blob-src="<?= htmlspecialchars($imgPath) ?>" src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==" alt="<?= htmlspecialchars($badge['name'] ?? 'Badge') ?>" class="badge-img" onerror="this.onerror=null;this.src='<?= htmlspecialchars($defaultBadge) ?>';">
                                <div class="badge-title"><?= htmlspecialchars($badge['name'] ?? 'Badge') ?></div>
                                <div class="badge-desc"><?= htmlspecialchars($badge['description'] ?? '') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="upcyclingScore-tab" style="display:none;">
                <div class="upcycling-gauge-container">
                    <canvas id="upcycling-gauge-chart" width="200" height="100" aria-hidden="true"></canvas>
                    <div class="gauge-text">
                        <span id="upcycling-score-value"><?= htmlspecialchars((string)$score) ?> kg CO₂ avoided</span>
                    </div>
                </div>
                <p class="upcycling-note">This figure represents the total environmental benefit of the user's offers !</p>
            </div>
        </div>

        <div id="modal-friend-request" class="modal-overlay" aria-hidden="true">
            <div class="modal">
                <div class="modal-header" style="justify-content:center;">
                    <h2>Send a Friend Request</h2>
                    <button class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">

                    <div id="friend-request-form-state">
                        <p>Send an invitation to <strong id="modal-target-username"><?= htmlspecialchars($publicUser['username'] ?? '') ?></strong></p>
                        <div class="mb-3">
                            <label for="friend-request-message" class="form-label">Message (optional):</label>
                            <textarea id="friend-request-message" class="form-control" rows="3" placeholder="Add a personal note like on LinkedIn..."></textarea>
                        </div>
                        <div id="friend-request-error" class="text-danger d-none mb-2"></div>
                    </div>
                    <div id="friend-request-success-state" class="d-none text-center">
                        <div style="font-size: 48px; margin-bottom: 1rem;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        </div>
                        <h3>Friend Request Sent!</h3>
                        <p>Your friend request has been sent to <strong id="success-target-username"><?= htmlspecialchars($publicUser['username'] ?? '') ?></strong></p>
                    </div>
                </div>
                <div class="modal-actions">

                    <div id="friend-request-form-actions">
                        <button class="btn-secondary modal-close-btn" type="button">Cancel</button>
                        <button class="btn-primary" id="btn-confirm-friend-request" type="button">Send</button>
                    </div>
                    <div id="friend-request-success-actions" class="d-none" style="width: 100%; text-align: center;">
                        <button class="btn-primary modal-close-btn" type="button">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.targetUsername = "<?= htmlspecialchars($targetUsername, ENT_QUOTES, 'UTF-8') ?>";
            window.publicUserId = "<?= htmlspecialchars($publicUser['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
            window.publicOffers = <?= json_encode(array_values($publicOffers), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            window.publicProjects = <?= json_encode(array_values($publicProjects), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            window.API_TOKEN = "<?= $_SESSION['token'] ?? $_SESSION['jwt_token'] ?? '' ?>";
            window.API_BASE = "<?= htmlspecialchars($API_URL_BROWSER, ENT_QUOTES, 'UTF-8') ?>";
        </script>
        <script src="../../assets/js/user_profile.js"></script>
    <?php endif; ?>
</main>

<?php
include_once '../../includes/footer.php';
?>