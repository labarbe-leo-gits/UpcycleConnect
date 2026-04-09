	<footer class="site-footer">
		<div class="footer-inner container">
			<div class="footer-col brand">
				<h3>UpcycleConnect</h3>
				<p>Connecting creators with reclaimed materials to build a more circular future.</p>
				<p class="small">© <span id="year"></span> UpcycleConnect - All rights reserved.</p>
			</div>

			<div class="footer-col links">
				<h4>Quick Links</h4>
				<ul>
					<li><a href="/PA/PA%20-%20Site%20Principal/pages/public/index">Home</a></li>
					<li><a href="/PA/PA%20-%20Site%20Principal/pages/public/about">About</a></li>
					<li><a href="/PA/PA%20-%20Site%20Principal/pages/public/contact">Contact</a></li>
					<li><a href="/PA/PA%20-%20Site%20Principal/pages/customers/index">Portal</a></li>
					<li><a href="/PA/PA%20-%20Site%20Principal/pages/public/cgu">Terms & Conditions</a></li>
				</ul>
			</div>

			<div class="footer-col contact">
				<h4>Contact</h4>
				<address>
					174 Rue La Fayette<br>
					75010 Paris, France<br>
					<a href="mailto:hello@upcycleconnect.example">upcycleconnect@gmail.com</a>
				</address>
			</div>

			<div class="footer-col social">
				<h4>Stay Connected</h4>
				<div class="social-links">
					<a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
					<a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
					<a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
				</div>
			</div>
		</div>

		<button id="chatbot-open-btn" class="chatbot-open-btn" type="button" aria-label="Open chatbot">
			<i class="fa-solid fa-robot" aria-hidden="true"></i>
		</button>
	</footer>

	<div id="chatbot-overlay" class="chatbot-overlay" aria-hidden="true">
		<div class="chatbot-panel" role="dialog" aria-modal="true" aria-labelledby="chatbot-title">
			<div class="chatbot-header">
				<div class="chatbot-title-wrap">
					<img class="chatbot-avatar" src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRN95FA9uv1Lijtf5B_ZMTDwNJen_HZuTCOiw&s" alt="Kévin avatar">
					<div>
						<span id="chatbot-title">Kévin</span>
						<small>Compiler</small>
					</div>
				</div>
				<div class="chatbot-toolbar">
					<button id="chatbot-new-btn" class="chatbot-new-btn" type="button" aria-label="Start fresh chat" title="Start fresh chat"><i class="fa-solid fa-rotate-right"></i></button>
					<button id="chatbot-close-btn" class="chatbot-close-btn" type="button" aria-label="Close chat"><i class="fa-solid fa-xmark"></i></button>
				</div>
			</div>
			<div class="chatbot-messages" id="chatbot-messages" aria-live="polite" aria-atomic="false"></div>
			<form id="chatbot-form" class="chatbot-input-row" autocomplete="off">
				<input id="chatbot-input" type="text" placeholder="Ask Kévin a question..." autocomplete="off" required>
				<button type="submit" aria-label="Send message"><i class="fa-solid fa-paper-plane"></i></button>
			</form>
		</div>
	</div>

	<link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/bot.css">
	<script src="/PA/PA%20-%20Site%20Principal/assets/js/bot.js"></script>
</body>
</html>