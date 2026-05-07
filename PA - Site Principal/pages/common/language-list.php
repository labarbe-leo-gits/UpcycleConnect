<?php
require_once '../../config/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo askAPI('/translations', 'GET');
