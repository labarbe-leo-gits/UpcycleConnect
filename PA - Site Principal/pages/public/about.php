<?php
$title = "About";
include_once '../../includes/header.php';
?>

<main class="container page-about">
	<section class="hero">
		<h1>About UpcycleConnect</h1>
		<p class="lead">The intelligent upcycling platform - turning yesterday's waste into tomorrow's resources.</p>
	</section>

	<section class="intro">
		<p>Founded in 2021, <strong>UpcycleConnect</strong> is an innovative leader in waste reduction and material valorization. We believe that yesterday's waste is tomorrow's resource. By leveraging technology, we breathe new life into unused or obsolete objects through the creative process of upcycling - transforming discarded materials into high-value, aesthetic, and useful products.</p>
	</section>

	<section class="mission">
		<h2>Our Mission & Vision</h2>
		<p>We are more than a marketplace: we are a community-driven ecosystem dedicated to the circular economy. Our platform connects individuals, artisans and businesses so that no material goes to waste.</p>
		<ul>
			<li><strong>Sustainability:</strong> Reducing environmental impact by measuring an "Upcycling Score" for every project.</li>
			<li><strong>Innovation:</strong> Intelligent inventory management and advanced filtering to make reclaimed materials accessible.</li>
			<li><strong>Education:</strong> Training citizens through workshops and resources to foster sustainable habits.</li>
		</ul>
	</section>

	<section class="offers">
		<h2>What We Offer</h2>
		<p>Our platform serves three distinct pillars of the upcycling world:</p>
		<div class="grid-3">
			<div>
				<h3>For Individuals</h3>
				<ul>
					<li>Give or Sell: Easily list items for donation or sale.</li>
					<li>Smart Drop-off: Use secure container systems with barcode access.</li>
					<li>Impact Tracking: Monitor your contribution via your personal dashboard.</li>
				</ul>
			</div>
			<div>
				<h3>For Professionals & Artisans</h3>
				<ul>
					<li>Material Sourcing: Priority access to high-quality reclaimed materials.</li>
					<li>Business Tools: Manage subscriptions, billing, and eco-impact analytics.</li>
					<li>Project Showcasing: Document and promote your transformation projects.</li>
				</ul>
			</div>
			<div>
				<h3>For the Community</h3>
				<ul>
					<li>Forums & Workshops: Join events and training led by experts.</li>
					<li>Advice: Access a dedicated "Guides" space for DIY inspiration.</li>
					<li>Education: Participate in certified sustainability training programs.</li>
				</ul>
			</div>
		</div>
	</section>

	<section class="presence">
		<h2>Our Presence</h2>
		<p>Headquartered at <strong>174, rue La Fayette - Paris (10th Arr.)</strong>, we have rapidly expanded our footprint to support local creators. You can find our workshops, conference halls, and smart-box containers in:</p>
		<ul>
			<li>Paris: 11th, 13th and 16th Arrondissements</li>
			<li>Greater Paris: Bourg-la-Reine, Ivry and Montreuil</li>
			<li>International: Our first relay-hub is now active in Switzerland</li>
		</ul>

		<div class="map-wrapper" aria-label="Map showing UpcycleConnect headquarters">
			<!-- Leaflet map container -->
			<div id="upcycle-map" role="application" aria-label="Map of UpcycleConnect HQ"></div>
		</div>
	</section>

	<section class="team">
		<h2>Meet the Leadership</h2>
		<p>Our growth is driven by a passionate team of experts dedicated to technological and environmental excellence:</p>

		<div class="team-carousel" aria-roledescription="carousel">
			<button class="carousel-btn prev" aria-label="Previous member">‹</button>
			<div class="carousel-track">
				<figure class="carousel-item" data-name="Sylvain Levy">
					<img src="../../assets/img/team/sylvain-levy.jpg" alt="Sylvain Levy">
					<figcaption>
						<strong>Sylvain Levy</strong>
						<span>CEO</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Norman Thavaud">
					<img src="../../assets/img/team/norman-thavaud.jpg" alt="Norman Thavaud">
					<figcaption>
						<strong>Norman Thavaud</strong>
						<span>CTO</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Laink Terracid">
					<img src="../../assets/img/team/laink-terracid.png" alt="Laink Terracid">
					<figcaption>
						<strong>Laink Terracid</strong>
						<span>Marketing Director</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Antoine Maclair">
					<img src="../../assets/img/team/antoine-maclair.jpg" alt="Antoine Maclair">
					<figcaption>
						<strong>Antoine Maclair</strong>
						<span>Happiness Manager (Montreuil)</span>
					</figcaption>
				</figure>
			</div>
			<button class="carousel-btn next" aria-label="Next member">›</button>
			<div class="carousel-dots" aria-hidden="false"></div>
		</div>
	</section>

</main>

<?php
include_once '../../includes/footer.php';
?>