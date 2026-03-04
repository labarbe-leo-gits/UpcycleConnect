<?php
$_stripe_env_file = __DIR__ . '/../.env';
if (file_exists($_stripe_env_file)) {
    $env = parse_ini_file($_stripe_env_file);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}
unset($_stripe_env_file, $env, $key, $value);

return [
	'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
	'secret_key'      => getenv('STRIPE_SECRET_KEY')      ?: '',
	'webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET')  ?: '',

	'premium_price_id' => 'price_1T70WSI7wZRSS0GxZYKgEKZS',

	'premium_price_display' => '29.99€ / month',
];
?>
