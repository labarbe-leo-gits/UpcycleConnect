<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

$page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

$response = askAPI("/conteneurs?page={$page}&limit={$limit}", "GET");
header('Content-Type: application/json');
echo $response;
