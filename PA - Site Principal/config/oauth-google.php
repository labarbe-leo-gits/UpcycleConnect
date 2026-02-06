<?php
return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: 'your-client-id-here',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: 'your-client-secret-here',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-google.php',
    'scopes' => ['email', 'profile']
];
?>