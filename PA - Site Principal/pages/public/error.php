
<?php
session_start();
$errorCode = isset($_SESSION['error_code']) ? $_SESSION['error_code'] : 'REDIRECT';

if ($errorCode === 'REDIRECT') {
    header('Location: index');
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
$title = "Error";
include_once '../../includes/header.php';
?>


<main class="container" style="min-height: 60vh; display: flex; flex-direction: column; align-items: center; justify-content: center;">
	<h1 style="font-size: 3rem; color: #c0392b; margin-bottom: 1rem;">Oops!</h1>
	<p style="font-size: 1.5rem; color: #333; margin-bottom: 0.5rem;">
		<?= htmlspecialchars($errorMessage) ?>
	</p>
	<?php if ($errorCode !== 'unknown'): ?>
		<p style="color: #888;">(Error code: <?= htmlspecialchars($errorCode) ?>)</p>
	<?php endif; ?>
	<?php if ($errorCode === '404'): ?>
		<div style="margin: 2rem 0;">
				   <div style="display: flex; flex-direction: column; align-items: center;">
					   <?php
					   $gamesnacks = [
						   ["url" => "https://gamesnacks.com/embed/games/stackbounce", "desc" => "Stack Bounce (click to bounce!)"],
						   ["url" => "https://gamesnacks.com/embed/games/elementblocks", "desc" => "Element Blocks (drag to fit!)"],
					   ];
					   $pick = $gamesnacks[array_rand($gamesnacks)];
					   ?>
					   
					   <button id="showGameBtn" style="padding: 0.7rem 1.5rem; font-size: 1.1rem; background: #2980b9; color: #fff; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 1.5rem; transition: background 0.2s, opacity 0.4s, transform 0.4s;">Play a quick game?</button>
					   <div id="gameContainer" style="display:none; flex-direction: column; align-items: center;">
						   <iframe id="gameIframe" width="340" height="600" style="border:none; background:#f7f7f7; border-radius:12px; box-shadow:0 2px 8px #0002;"></iframe>
						   <p style="color:#888; font-size:0.9rem; margin-top:0.5rem;">(Enjoy: <?= htmlspecialchars($pick['desc']) ?>)</p>
					   </div>
					   <script>
					   const btn = document.getElementById('showGameBtn');
					   btn.addEventListener('click', function() {
						   btn.style.opacity = '0';
						   btn.style.transform = 'scale(0.8)';
						   setTimeout(function() {
							   btn.style.display = 'none';
							   var gameContainer = document.getElementById('gameContainer');
							   var gameIframe = document.getElementById('gameIframe');
							   gameIframe.src = <?= json_encode($pick['url']) ?>;
							   gameContainer.style.display = 'flex';
						   }, 400);
					   });
					   </script>
				   </div>
		</div>
	<?php endif; ?>
	<a href="/PA%20-%20Site%20Principal/pages/public/index" style="margin-top: 2rem; color: #2980b9; text-decoration: underline;">Return to Home</a>
</main>

<?php
include_once '../../includes/footer.php';
?>