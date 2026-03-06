<?php
$title = "Requests";
include_once '../../includes/admin-header.php';
?>

<div class="container">
    <h2>System Requests</h2>
    <div class="requests-section">
        <p>This page will aggregate deposit requests, payment requests and payouts. Select a category from the menu above.</p>
    </div>
    <div id="deposits-container" class="admin-list"></div>
    <div id="payment-requests-container" class="admin-list"></div>
    <div id="payouts-container" class="admin-list"></div>
</div>


<?php
include_once '../../includes/footer.php';
?>
