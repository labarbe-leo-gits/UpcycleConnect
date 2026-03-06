
<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
$errorCode = isset($_SESSION['error_code']) ? $_SESSION['error_code'] : 'REDIRECT';

if ($errorCode === 'REDIRECT') {
    header('Location: index');
    exit();
}

$errorMappingPath = __DIR__ . '/../../assets/json/error-mapping.json';
$errorMessage = 'An unknown error occurred.';
if (file_exists($errorMappingPath)) {
	$mapping = json_decode(file_get_contents($errorMappingPath), true);
	if (isset($mapping[$errorCode])) {
		$errorMessage = $mapping[$errorCode];
	} else if ($errorCode !== 'unknown') {
		$errorMessage = 'Error code ' . htmlspecialchars($errorCode) . ': An unknown error occurred.';
	}
}
$title = "Error";
$extraCss = ['/PA/PA%20-%20Site%20Principal/assets/css/error-page.css'];
include_once __DIR__ . '/../../includes/header.php';
?>


<main class="error-page-main">
	<div class="error-page-box">
		<h1 class="error-page-title">Oops!</h1>
		<p class="error-page-message">
			<?= htmlspecialchars($errorMessage) ?>
		</p>
		<?php if ($errorCode !== 'unknown'): ?>
			<p class="error-page-code">(Error code: <?= htmlspecialchars($errorCode) ?>)</p>
		<?php endif; ?>
		<a class="error-page-link" href="/PA%20-%20Site%20Principal/pages/public/index">Return to Home</a>
	</div>
</main>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>