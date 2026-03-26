<?php
$title = "Home";
include_once '../../includes/header.php';

include_once '../../config/db.php';
include_once '../../includes/auth.php';

$user = getLoggedInUser();
trackLastPage();

?>

<section class="hero-landing-wrapper">
	<div class="hero-inner">
		<section class="hero-landing">
			<div class="hero-content">
				<div class="header-text">
					<h1>Welcome to</h1>
					<img data-blob-src="../../assets/img/brand/UpcyclePetiSignVersion.png" alt="UpcycleConnect logo" class="logo">
					<div class="by-petisign-badge">
						<img data-blob-src="../../assets/img/brand/petisign.png" alt="PétiSign logo" class="petisign-logo">
						<span>By PétiSign</span>
					</div>
				</div>
				<p class="lead">Connecting people, artisans and businesses to give new life to used materials - simple, local, and sustainable.</p>
				<div class="hero-ctas">
					<a class="btn-cta" href="about">Get to know us</a>
					<a class="btn-cta btn-outline" href="contact">Contact us</a>
				</div>

				<div class="hero-down" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 16l-6-6h12l-6 6z" fill="white" fill-opacity="0.95" />
					</svg>
				</div>
			</div>

			<aside class="hero-visual" aria-hidden="true" role="img" aria-label="Decorative hero image"></aside>
		</section>
	</div>
</section>

<section class="home-illustration-intro">
    <div class="container">
        <div class="intro-content">
            <div class="intro-text">
                <h2>Join the circular economy movement</h2>
                <p>Discover and promote high-quality upcycling offers from local artisans and businesses. Our community supports reuse, repair, and creative transformation.</p>
                <div class="intro-h-tags">
                    <span><i class="fa-solid fa-seedling"></i> Green-friendly</span>
                    <span><i class="fa-solid fa-recycle"></i> Circular reuse</span>
                    <span><i class="fa-solid fa-handshake-angle"></i> Local connection</span>
                </div>
				<section class="big-home-card-section">
    <div class="container">
        <div class="big-home-card">
            <div class="big-card-text">
                <h3>Explore trending deals from our top green partners</h3>
                <p style="text-align: left;">Boost your impact with carefully selected upcycled offers. Each item is subject for inspection, transparency, and circular value. We make sure that any offers on our platform reflects their real aspect. Your support helps reduce waste and pollution !</p>
                <ul>
                    <li>Profesionnal Offers</li>
                    <li>Verified condition & material data</li>
                    <li>UpcyclingScore for every purchase</li>
                </ul>
                <a href="../common/offers" class="btn-cta">Browse all offers</a>
            </div>
            <div class="big-card-image">
                <img src="https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=950&q=80" alt="Upcycling process illustration" />
            </div>
        </div>
    </div>
</section>
            </div>
            <div class="intro-illustrations">
                <img src="https://images.unsplash.com/photo-1545239351-1141bd82e8a6?auto=format&fit=crop&w=920&q=80" alt="Upcycle illustration" />
            </div>
        </div>
    </div>
</section>

<section class="featured-offers-section">
    <div class="container">
        <div class="featured-offers-header">
            <h2>Featured promoted offers</h2>
            <a class="btn-view-all" href="../common/offers?promoted=1">View all promoted</a>
        </div>
        <div id="featured-promoted-row" class="featured-offers-row" aria-live="polite"></div>
    </div>
</section>

<section class="featured-offers-section">
    <div class="container">
        <div class="featured-offers-header">
            <h2>Discover random offers</h2>
            <a class="btn-view-all" href="../common/offers">View all offers</a>
        </div>
        <div id="featured-random-row" class="featured-offers-row" aria-live="polite"></div>
    </div>
</section>

<div id="easterEggModal" role="dialog" aria-modal="true" aria-labelledby="easterEggTitle" aria-describedby="easterEggDesc">
	<div class="egg-card">
		<button class="egg-close" aria-label="Close Easter Egg Modal">&times;</button>
		<h2 id="easterEggTitle">Oops !</h2>
		<p id="easterEggDesc">A wild Louis Lucien Ivan Detraux !</p>
		<img data-blob-src="../../assets/img/team/LouisLucienIvanDetraux.jpg" alt="">
	</div>
</div>

<script src="../../assets/js/featured-offers.js"></script>
<script src="../../assets/js/easter-egg.js"></script>

<?php
include_once '../../includes/footer.php';
?>