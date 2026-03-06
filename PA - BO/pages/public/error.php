<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errorCode = isset($_SESSION['error_code']) ? $_SESSION['error_code'] : 'REDIRECT';

if ($errorCode === 'REDIRECT') {
	header('Location: /PA/PA%20-%20BO/pages/public/login');
    exit();
}

$errorMappingPath = '../../assets/json/error-mapping.json';
$errorMessage = 'An unknown error occurred.';
if (file_exists($errorMappingPath)) {
	$mapping = json_decode(file_get_contents($errorMappingPath), true);
	if (isset($mapping[$errorCode])) {
		$errorMessage = $mapping[$errorCode];
	} else if ($errorCode !== 'unknown') {
		$errorMessage = 'Error code ' . htmlspecialchars($errorCode) . ': An unknown error occurred.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>UpcycleAdmin - Error</title>
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
	<link rel="stylesheet" href="/PA/PA%20-%20BO/assets/css/style.css">
	<link rel="stylesheet" href="/PA/PA%20-%20BO/assets/css/error-page.css">
</head>
<body class="error-page-body">
	<header class="error-page-header">UpcycleConnect</header>

	<main class="error-page-wrap">
		<div class="error-page-card">
			<h1 class="error-page-title">Oops!</h1>
			<p class="error-page-message">
				<?= htmlspecialchars($errorMessage) ?>
			</p>
			<?php if ($errorCode !== 'unknown'): ?>
				<p class="error-page-code">(Error code: <?= htmlspecialchars($errorCode) ?>)</p>
			<?php endif; ?>
			<?php $isAdmin = isset($_SESSION['user_type']) && (int) $_SESSION['user_type'] === 3; ?>
			<a class="error-page-link" href="<?= $isAdmin ? '/PA/PA%20-%20BO/pages/admin/dashboard' : '/PA/PA%20-%20BO/pages/public/login.php' ?>">
				<?= $isAdmin ? 'Return to Dashboard' : 'Go to Login' ?>
			</a>
		</div>
	</main>

	<footer class="error-page-footer">&copy; <?= date('Y') ?> UpcycleConnect. All rights reserved.</footer>
</body>
</html>
