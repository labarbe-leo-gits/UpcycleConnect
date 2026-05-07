	<footer class="site-footer">
		<div class="footer-inner container">
			<div class="footer-col brand">
				<h3 data-i18n="public.footer.brand_name">UpcycleConnect</h3>
				<p data-i18n="public.footer.brand_description">Connecting creators with reclaimed materials to build a more circular future.</p>
				<p class="small">© <span id="year"></span> <span data-i18n="public.footer.brand_name">UpcycleConnect</span> - <span data-i18n="public.footer.all_rights_reserved">All rights reserved.</span></p>
			</div>

			<div class="footer-col language">
				<h4 data-i18n="nav.language">Language</h4>
				<label for="language-select" class="sr-only" data-i18n="lang.select_language">Select language</label>
				<div class="language-select-wrapper">
					<select id="language-select" class="language-selector" aria-label="Language"></select>
				</div>
			</div>

			<div class="footer-col links">
				<h4 data-i18n="public.footer.quick_links">Quick Links</h4>
				<ul>
				<li><a href="/pages/public/index" data-i18n="public.footer.link_home">Home</a></li>
				<li><a href="/pages/public/about" data-i18n="public.footer.link_about">About</a></li>
				<li><a href="/pages/public/contact" data-i18n="public.footer.link_contact">Contact</a></li>
				<li><a href="/pages/customers/index" data-i18n="public.footer.link_portal">Portal</a></li>
				<li><a href="/pages/public/cgu" data-i18n="public.footer.link_terms">Terms & Conditions</a></li>
				</ul>
			</div>

			<div class="footer-col contact">
				<h4 data-i18n="public.footer.contact_heading">Contact</h4>
				<address>
					174 Rue La Fayette<br>
					75010 Paris, France<br>
					<a href="mailto:hello@upcycleconnect.example">upcycleconnect@gmail.com</a>
				</address>
			</div>

			<div class="footer-col social">
				<h4 data-i18n="public.footer.stay_connected">Stay Connected</h4>
				<div class="social-links">
					<a href="#" aria-label="Twitter" data-i18n-aria-label="public.footer.social_twitter"><i class="fa-brands fa-twitter"></i></a>
					<a href="#" aria-label="Facebook" data-i18n-aria-label="public.footer.social_facebook"><i class="fa-brands fa-facebook"></i></a>
					<a href="#" aria-label="Instagram" data-i18n-aria-label="public.footer.social_instagram"><i class="fa-brands fa-instagram"></i></a>
				</div>
			</div>
		</div>

		<button id="chatbot-open-btn" class="chatbot-open-btn" type="button" aria-label="Open chatbot" data-i18n-aria-label="public.footer.chatbot_open">
			<i class="fa-solid fa-robot" aria-hidden="true"></i>
		</button>
	</footer>

	<div id="chatbot-overlay" class="chatbot-overlay" aria-hidden="true">
		<div class="chatbot-panel" role="dialog" aria-modal="true" aria-labelledby="chatbot-title">
			<div class="chatbot-header">
				<div class="chatbot-title-wrap">
					<img class="chatbot-avatar" src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRN95FA9uv1Lijtf5B_ZMTDwNJen_HZuTCOiw&s" alt="Kévin avatar" data-i18n-alt="public.footer.chatbot_avatar_alt">
					<div>
						<span id="chatbot-title" data-i18n="public.footer.chatbot_name">Kévin</span>
						<small data-i18n="public.footer.chatbot_role">Compiler</small>
					</div>
				</div>
				<div class="chatbot-toolbar">
					<button id="chatbot-new-btn" class="chatbot-new-btn" type="button" aria-label="Start fresh chat" title="Start fresh chat" data-i18n-aria-label="public.footer.chatbot_start_fresh" data-i18n-title="public.footer.chatbot_start_fresh"><i class="fa-solid fa-rotate-right"></i></button>
					<button id="chatbot-close-btn" class="chatbot-close-btn" type="button" aria-label="Close chat" data-i18n-aria-label="public.footer.chatbot_close"><i class="fa-solid fa-xmark"></i></button>
				</div>
			</div>
			<div class="chatbot-messages" id="chatbot-messages" aria-live="polite" aria-atomic="false"></div>
			<form id="chatbot-form" class="chatbot-input-row" autocomplete="off">
				<input id="chatbot-input" type="text" placeholder="Ask Kévin a question..." data-i18n-placeholder="public.footer.chatbot_prompt" autocomplete="off" required>
				<button type="submit" aria-label="Send message" data-i18n-aria-label="public.footer.chatbot_send_message"><i class="fa-solid fa-paper-plane"></i></button>
			</form>
		</div>
	</div>

	<link rel="stylesheet" href="/assets/css/bot.css">
	<script src="/assets/js/bot.js"></script>
</body>
</html>