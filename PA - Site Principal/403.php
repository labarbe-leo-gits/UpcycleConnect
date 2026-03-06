<?php
session_start();
$_SESSION['error_code'] = '403';
include_once __DIR__ . '/pages/public/error.php';

