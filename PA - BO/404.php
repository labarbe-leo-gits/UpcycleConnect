<?php
session_start();
$_SESSION['error_code'] = '404';
include_once './pages/public/error.php';

