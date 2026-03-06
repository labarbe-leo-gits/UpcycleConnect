<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

header('Content-Type: application/json');

$response = askAPI('/conteneurs', 'GET');
echo $response;
