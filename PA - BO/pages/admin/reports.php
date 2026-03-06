<?php
$title = "Reports";
include_once '../../includes/admin-header.php';
?>

<div class="container">
    <h2>Reports</h2>
    <p>Administrative reports and user complaints will appear here.</p>
    <div id="reports-container" class="admin-list">
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="skeleton skeleton-line"></div>
        <?php endfor; ?>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>
