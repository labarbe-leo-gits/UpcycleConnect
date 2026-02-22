<?php
// Logout handler
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    logout();
}

logout();
?>
