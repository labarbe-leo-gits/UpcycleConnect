<?php
$title = "Contact";
include_once '../../includes/header.php';
?>

<div class="container">
    <h1>Contact Us</h1>
    <p>We'd love to hear from you! Whether you have questions about our services, want to share feedback, or are interested in partnership opportunities, feel free to reach out.</p>

    <h2>Follow Us</h2>
    <p>Stay connected and follow us on social media for the latest updates:</p>
    <ul class="social-links">
        <li><a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i> Twitter</a></li>
        <li><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook"></i> Facebook</a></li>
        <li><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i> Instagram</a></li>
    </ul>

    <h2>Contact Form</h2>
    <p>If you prefer, you can also send us a message directly through the form below. We look forward to hearing from you!</p>

    <form method="POST" action="">
        <div class="field">
            <label for="password">Your name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="name" name="name" placeholder="John Doe" required>
            </div>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="Your email address" required>
            </div>
        </div>

        <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" placeholder="Your message here..." required></textarea>
        </div>

        <button type="submit">Send Message</button>
    </form>
</div>

<?php
include_once '../../includes/footer.php';
?>