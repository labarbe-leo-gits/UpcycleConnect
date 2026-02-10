<?php

$title = "Service Details";
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();

$serviceUuid = isset($_GET['uuid']) ? $_GET['uuid'] : null;

if (!$serviceUuid) {
    header('Location: services.php');
    exit;
}

$serviceData = askAPI("/products/services/" . $serviceUuid, "GET");
$service = json_decode($serviceData, true);

if (isset($service['error'])) {
    echo '<div class="container"><p class="error-message">Service not found.</p></div>';
    include_once '../../includes/footer.php';
    exit;
}

$price = floatval($service['price'] ?? 0);
$priceDisplay = ($price == 0) ? "Free" : "€ " . number_format($price, 2);

$serviceType = intval($service['type'] ?? 0);
$typeLabel = '';
$typeIcon = '';
switch($serviceType) {
    case 1:
        $typeLabel = 'Formation';
        $typeIcon = 'fa-graduation-cap';
        break;
    case 2:
        $typeLabel = 'Event';
        $typeIcon = 'fa-calendar-days';
        break;
    case 3:
        $typeLabel = 'Consulting';
        $typeIcon = 'fa-user-tie';
        break;
    default:
        $typeLabel = 'Other';
        $typeIcon = 'fa-circle-question';
}

$serviceDate = null;
if (isset($service['service_date']) && !empty($service['service_date'])) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $service['service_date']);
    if ($dateObj) {
        $serviceDate = $dateObj->format('d/m/Y');
    }
}

$creatorName = null;
if (isset($service['created_by']) && !empty($service['created_by'])) {
    $userResponse = askAPI("/users/" . $service['created_by'], "GET");
    $userData = json_decode($userResponse, true);
    if (isset($userData['username'])) {
        $creatorName = $userData['username'];
    }
}
?>

<div class="container">
    <div class="service-details skeleton-detail-container">
        <div class="service-header">
            <div class="title-wrapper">
                <div class="skeleton skeleton-detail-title"></div>
                <div class="skeleton skeleton-detail-badge"></div>
            </div>
        </div>
        
        <div class="service-info">
            <div class="info-section">
                <div class="skeleton skeleton-detail-heading"></div>
                <div class="skeleton skeleton-detail-text"></div>
                <div class="skeleton skeleton-detail-text" style="width: 90%;"></div>
                <div class="skeleton skeleton-detail-text" style="width: 70%;"></div>
            </div>
            
            <div class="info-grid">
                <div class="skeleton-detail-info-item">
                    <div class="skeleton skeleton-detail-icon"></div>
                    <div style="flex: 1;">
                        <div class="skeleton skeleton-detail-label"></div>
                        <div class="skeleton skeleton-detail-value"></div>
                    </div>
                </div>
                <div class="skeleton-detail-info-item">
                    <div class="skeleton skeleton-detail-icon"></div>
                    <div style="flex: 1;">
                        <div class="skeleton skeleton-detail-label"></div>
                        <div class="skeleton skeleton-detail-value"></div>
                    </div>
                </div>
                <div class="skeleton-detail-info-item">
                    <div class="skeleton skeleton-detail-icon"></div>
                    <div style="flex: 1;">
                        <div class="skeleton skeleton-detail-label"></div>
                        <div class="skeleton skeleton-detail-value"></div>
                    </div>
                </div>
            </div>
            
            <div class="service-actions">
                <div class="skeleton skeleton-detail-button"></div>
                <div class="skeleton skeleton-detail-button-secondary"></div>
            </div>
        </div>
    </div>

    <div class="service-details actual-content" style="display: none;">
        <div class="service-header">
            <div class="title-wrapper">
                <h1><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($service['name'] ?? 'Unnamed Service'); ?></h1>
                <span class="service-type-badge type-<?php echo strtolower($typeLabel); ?>">
                    <i class="fa-solid <?php echo $typeIcon; ?>"></i> <?php echo $typeLabel; ?>
                </span>
            </div>
        </div>
        
        <div class="service-info">
            <div class="info-section">
                <h2>Description</h2>
                <p class="description-text"><?php echo nl2br(htmlspecialchars($service['description'] ?? 'No description available')); ?></p>
            </div>
            
            <div class="info-grid">
                <?php if ($serviceDate): ?>
                <div class="info-item">
                    <i class="fa-regular fa-calendar"></i>
                    <div class="info-content">
                        <span class="label">Date</span>
                        <span class="value"><?php echo $serviceDate; ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($creatorName): ?>
                <div class="info-item">
                    <i class="fa-solid fa-user"></i>
                    <div class="info-content">
                        <span class="label">Organized by</span>
                        <span class="value"><?php echo htmlspecialchars($creatorName); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($service['service_road']) || !empty($service['service_city']) || !empty($service['service_zip'])): ?>
                <div class="info-item location">
                    <i class="fa-solid fa-location-dot"></i>
                    <div class="info-content">
                        <span class="label">Location</span>
                        <span class="value">
                            <?php 
                            $location = [];
                            if (!empty($service['service_road'])) $location[] = htmlspecialchars($service['service_road']);
                            if (!empty($service['service_city'])) $location[] = htmlspecialchars($service['service_city']);
                            if (!empty($service['service_zip'])) $location[] = htmlspecialchars($service['service_zip']);
                            echo implode(', ', $location);
                            ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-item price-item">
                    <?php if ($price === 0): ?>
                        <i class="fa-solid fa-tag"></i>
                    <?php endif; ?>
                    <div class="info-content">
                        <span class="label">Price</span>
                        <span class="value price"><?php echo $priceDisplay; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="service-actions">
                <button class="btn-primary" onclick="handlePurchase()">
                    <?php echo $price > 0 ? 'Purchase' : 'Get'; ?>
                </button>
                <a href="services" class="btn-secondary">Back to Services</a>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    setTimeout(function() {
        document.querySelector('.skeleton-detail-container').style.display = 'none';
        document.querySelector('.actual-content').style.display = 'block';
    }, 500);
});

function handlePurchase() {
    window.location.href = 'order?product_uuid=<?php echo $serviceUuid; ?>';
}
</script>

<?php
include_once '../../includes/footer.php';
?>
