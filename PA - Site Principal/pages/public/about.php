<?php
$title = "About";
include_once '../../includes/header.php';
?>

<main class="container page-about">
	<section class="hero">
		<h1 data-i18n="public.about.title">About UpcycleConnect</h1>
		<p class="lead" data-i18n="public.about.lead">The intelligent upcycling platform - turning yesterday's waste into tomorrow's resources.</p>
	</section>

	<section class="intro">
		<p data-i18n="public.about.founded">Founded in 2021, <strong>UpcycleConnect</strong> is an innovative leader in waste reduction and material valorization. We believe that yesterday's waste is tomorrow's resource. By leveraging technology, we breathe new life into unused or obsolete objects through the creative process of upcycling - transforming discarded materials into high-value, aesthetic, and useful products.</p>
	</section>

	<section class="mission">
		<h2 data-i18n="public.about.mission_title">Our Mission & Vision</h2>
		<p data-i18n="public.about.mission_text">We are more than a marketplace: we are a community-driven ecosystem dedicated to the circular economy. Our platform connects individuals, artisans and businesses so that no material goes to waste.</p>
		<ul>
			<li><strong data-i18n="public.about.sustainability">Sustainability:</strong> Reducing environmental impact by measuring an "Upcycling Score" for every project.</li>
			<li><strong data-i18n="public.about.innovation">Innovation:</strong> Intelligent inventory management and advanced filtering to make reclaimed materials accessible.</li>
			<li><strong data-i18n="public.about.education">Education:</strong> Training citizens through workshops and resources to foster sustainable habits.</li>
		</ul>
	</section>

	<section class="offers">
		<h2 data-i18n="public.about.what_we_offer">What We Offer</h2>
		<p data-i18n="public.about.platform_pillars">Our platform serves three distinct pillars of the upcycling world:</p>
		<div class="grid-3">
			<div>
				<h3 data-i18n="public.about.for_individuals">For Individuals</h3>
				<ul>
					<li data-i18n="public.about.give_or_sell">Give or Sell: Easily list items for donation or sale.</li>
					<li data-i18n="public.about.smart_dropoff">Smart Drop-off: Use secure container systems with barcode access.</li>
					<li data-i18n="public.about.impact_tracking">Impact Tracking: Monitor your contribution via your personal dashboard.</li>
				</ul>
			</div>
			<div>
				<h3 data-i18n="public.about.for_professionals">For Professionals & Artisans</h3>
				<ul>
					<li data-i18n="public.about.material_sourcing">Material Sourcing: Priority access to high-quality reclaimed materials.</li>
					<li data-i18n="public.about.business_tools">Business Tools: Manage subscriptions, billing, and eco-impact analytics.</li>
					<li data-i18n="public.about.project_showcasing">Project Showcasing: Document and promote your transformation projects.</li>
				</ul>
			</div>
			<div>
				<h3 data-i18n="public.about.for_the_community">For the Community</h3>
				<ul>
					<li data-i18n="public.about.forums_workshops">Forums & Workshops: Join events and training led by experts.</li>
					<li data-i18n="public.about.guides">Advice: Access a dedicated "Guides" space for DIY inspiration.</li>
					<li data-i18n="public.about.education_programs">Education: Participate in certified sustainability training programs.</li>
				</ul>
			</div>
		</div>
	</section>

	<section class="presence">
		<h2 data-i18n="public.about.presence_title">Our Presence</h2>
		<p><span data-i18n="public.about.headquartered">Headquartered at</span> <strong>174, rue La Fayette - Paris (10th Arr.)</strong><span data-i18n="public.about.presence_desc">, we have rapidly expanded our footprint to support local creators. You can find our workshops, conference halls, and smart-box containers in:</span></p>
		<ul>
			<li data-i18n="public.about.paris_locations">Paris: 11th, 13th and 16th Arrondissements</li>
			<li data-i18n="public.about.greater_paris">Greater Paris: Bourg-la-Reine, Ivry and Montreuil</li>
			<li data-i18n="public.about.international">International: Our first relay-hub is now active in Switzerland</li>
		</ul>

		<div class="map-wrapper" aria-label="Map showing UpcycleConnect headquarters" data-i18n-aria-label="public.about.map_label">
			<!-- Leaflet map container -->
			<div id="upcycle-map" role="application" aria-label="Map of UpcycleConnect HQ" data-i18n-aria-label="public.about.map_role"></div>
		</div>
	</section>

	<section class="team">
		<h2 data-i18n="public.about.meet_leadership">Meet the Leadership</h2>
		<p data-i18n="public.about.leadership_desc">Our growth is driven by a passionate team of experts dedicated to technological and environmental excellence:</p>

		<div class="team-carousel" aria-roledescription="carousel">
			<button class="carousel-btn prev" aria-label="Previous member" data-i18n-aria-label="public.about.previous_member">‹</button>
			<div class="carousel-track">
				<figure class="carousel-item" data-name="Sylvain Levy">
					<img data-blob-src="../../assets/img/team/sylvain-levy.jpg" alt="Sylvain Levy" data-i18n-alt="public.about.team_sylvain_levy">
					<figcaption>
						<strong>Sylvain Levy</strong>
						<span data-i18n="public.about.ceo">CEO</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Pierre Chabrier">
					<img data-blob-src="../../assets/img/team/pierre-chabrier.jpg" alt="Pierre Chabrier" data-i18n-alt="public.about.team_pierre_chabrier">
					<figcaption>
						<strong>Pierre Chabrier</strong>
						<span data-i18n="public.about.hr">HR</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Norman Thavaud">
					<img data-blob-src="../../assets/img/team/norman-thavaud.jpg" alt="Norman Thavaud" data-i18n-alt="public.about.team_norman_thavaud">
					<figcaption>
						<strong>Norman Thavaud</strong>
						<span data-i18n="public.about.cto">CTO</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Ronnand Peuplus">
					<img data-blob-src="../../assets/img/team/ronnand-peuplus.jpg" alt="Ronnand Peuplus" data-i18n-alt="public.about.team_ronnand_peuplus">
					<figcaption>
						<strong>Ronnand Peuplus</strong>
						<span data-i18n="public.about.sales_director">Sales Director</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Laink Terracid">
					<img data-blob-src="../../assets/img/team/laink-terracid.png" alt="Laink Terracid" data-i18n-alt="public.about.team_laink_terracid">
					<figcaption>
						<strong>Laink Terracid</strong>
						<span data-i18n="public.about.marketing_director">Marketing Director</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Antoine Maclair">
					<img data-blob-src="../../assets/img/team/antoine-maclair.jpg" alt="Antoine Maclair" data-i18n-alt="public.about.team_antoine_maclair">
					<figcaption>
						<strong>Antoine Maclair</strong>
						<span data-i18n="public.about.happiness_manager">Happiness Manager (Montreuil)</span>
					</figcaption>
				</figure>

				<figure class="carousel-item" data-name="Frédéric Molas">
					<img data-blob-src="../../assets/img/team/frederic-molas.jpg" alt="Frédéric Molas" data-i18n-alt="public.about.team_frederic_molas">
					<figcaption>
						<strong>Frédéric Molas</strong>
						<span data-i18n="public.about.regional_director">Regional Director (Swiss)</span>
					</figcaption>
				</figure>
			</div>
			<button class="carousel-btn next" aria-label="Next member" data-i18n-aria-label="public.about.next_member">›</button>
			<div class="carousel-dots" aria-hidden="false"></div>
		</div>
	</section>

</main>

<?php
include_once '../../includes/footer.php';
?>