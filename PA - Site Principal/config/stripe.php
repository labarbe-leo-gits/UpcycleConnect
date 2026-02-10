<?php
return [
	'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
	'secret_key' => getenv('STRIPE_SECRET_KEY') ?: ''
];
?>
