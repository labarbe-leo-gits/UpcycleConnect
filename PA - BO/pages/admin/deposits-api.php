<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

header('Content-Type: application/json');

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id !== '') {
    $response = askAPI('/deposits/' . urlencode($id), 'GET');
} else {
    $response = askAPI('/deposits', 'GET');
}

echo $response;
