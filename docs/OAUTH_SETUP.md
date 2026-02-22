# OAuth 2.0 Setup Guide

This document provides step-by-step instructions for configuring OAuth 2.0 authentication with Google and Microsoft identity providers.

## Table of Contents

- [Overview](#overview)
- [Database Setup](#database-setup)
- [Google OAuth Setup](#google-oauth-setup)
- [Facebook OAuth Setup](#facebook-oauth-setup)
- [Backend Configuration](#backend-configuration)
- [Frontend Integration](#frontend-integration)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Overview

UpcycleConnect supports OAuth 2.0 authentication with external identity providers, allowing users to sign in using their existing Google or Microsoft accounts.

### OAuth Flow

```
User                Browser              Application           OAuth Provider
  |                    |                      |                        |
  |--- Click Login --->|                      |                        |
  |                    |--- Redirect -------> |                        |
  |                    |                      |--- Auth Request -----> |
  |                    |                      |                        |
  |                    | <------------------- Consent Screen           |
  |<--- Authorize -----|                      |                        |
  |                    |--- Grant Code -----> |                        |
  |                    |                      |--- Exchange Code ----> |
  |                    |                      | <--- Access Token ---- |
  |                    |                      |--- Get User Info ----> |
  |                    |                      | <--- User Data -------- |
  |                    | <--- Session Set --- |                        |
  |                    |                      |                        |
```

### Benefits

- No password management for users
- Trusted authentication providers
- Automatic profile picture integration
- Reduced registration friction
- Enhanced security

## Database Setup

OAuth support requires additional database fields to store provider information.

### Schema Updates

The following fields have been added to the `users` table:

```sql
ALTER TABLE users
ADD COLUMN oauth_provider VARCHAR(20) NULL,
ADD COLUMN oauth_id VARCHAR(255) NULL,
ADD COLUMN profile_picture VARCHAR(500) NULL,
ADD UNIQUE INDEX idx_oauth (oauth_provider, oauth_id);
```

### Field Descriptions

| Field             | Type         | Description                                   |
| ----------------- | ------------ | --------------------------------------------- |
| `oauth_provider`  | VARCHAR(20)  | Provider name: "google", "microsoft", or NULL |
| `oauth_id`        | VARCHAR(255) | Provider-specific user identifier             |
| `profile_picture` | VARCHAR(500) | URL to user's profile picture                 |

### Constraints

- Unique constraint on `(oauth_provider, oauth_id)` prevents duplicate OAuth accounts
- Fields are nullable to support non-OAuth users
- Profile picture URLs are validated by providers

## Google OAuth Setup

### Step 1: Create Google Cloud Project

1. Navigate to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing project
3. Name your project (e.g., "UpcycleConnect")

### Step 2: Enable Google+ API

1. In the Cloud Console, navigate to **APIs & Services** > **Library**
2. Search for "Google+ API"
3. Click **Enable**

### Step 3: Create OAuth 2.0 Credentials

1. Navigate to **APIs & Services** > **Credentials**
2. Click **Create Credentials** > **OAuth client ID**
3. If prompted, configure the OAuth consent screen:
   - Choose **External** user type
   - Fill in application name: "UpcycleConnect"
   - Add support email
   - Add authorized domain (if applicable)
   - Add scopes: `email`, `profile`

### Step 4: Configure OAuth Client

**Application Type**: Web application

**Name**: UpcycleConnect Web Client

**Authorized JavaScript Origins**:

```
http://localhost
http://127.0.0.1
```

**Authorized Redirect URIs**:

```
http://localhost/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-google.php
```

**Important**: The redirect URI must be URL-encoded. Spaces become `%20`.

### Step 5: Copy Credentials

After creation, you will receive:

- **Client ID**: `xxx.apps.googleusercontent.com`
- **Client Secret**: `GOCSPX-xxx`

Save these credentials securely.

### Step 6: Configure Environment Variables

Add to `.env` file in `PA - Site Principal/`:

```env
GOOGLE_CLIENT_ID=your_client_id_here.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-your_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-google.php
```

### Security Notes

- Never commit credentials to version control
- Use environment variables for all sensitive data
- Rotate secrets periodically
- Restrict redirect URIs to trusted domains

## Facebook OAuth Setup

Facebook OAuth remains free to use for basic sign‑in and profile access, so it’s a solid no‑cost alternative to Microsoft’s paid Azure requirement.

### Step 1: Create a Facebook App

1. Go to the [Facebook for Developers](https://developers.facebook.com) site and log in with a Facebook account.
2. In the top menu choose **My Apps** › **Create App** and pick **Consumer** or **Business** depending on whether you also plan to use other Facebook APIs. (Consumer is fine for simple sign‑in.)
3. Give it a name like "UpcycleConnect" and enter a contact email.
4. After the app is created, navigate to **Settings** › **Basic** and note the **App ID** and **App Secret** (you’ll use them as client ID/secret).
5. Under **Products** on the left, click **Facebook Login** › **Settings** and add the Valid OAuth Redirect URI:

   ```
   http://localhost/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-facebook.php
   ```

   (Use your production domain when deploying.)

6. You can leave other options at their defaults; the Facebook login product is free and does not require a paid tier.

**Name**: UpcycleConnect

**Authorization settings**:

- Platform: **Web**
- Callback (redirect) URI as shown above.
- Scopes: `email`, `public_profile` (these are set in code).

### Step 2: Obtain API Keys and Secrets

1. After creating the app, open the **Keys and tokens** tab.
2. Generate the following credentials:
   - **Client ID** (sometimes called API Key)
   - **Client Secret** (sometimes called API Secret)
   - **Bearer Token** (optional, used by some libraries)

> Note: Facebook provides an **App ID** (client ID) and **App Secret** which function equivalently.

### Step 3: Configure Environment Variables

Add the following entries to your `.env` file (in `PA - Site Principal/`):

```env
FACEBOOK_CLIENT_ID=your_facebook_app_id_here
FACEBOOK_CLIENT_SECRET=your_facebook_app_secret_here
FACEBOOK_REDIRECT_URI=http://localhost/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-facebook.php
```

For production, replace `localhost` with your actual domain and ensure the redirect URI matches exactly.

### Step 4: Update Application Code

You will need to create the new OAuth endpoints (see examples later). The flow is virtually identical to the Google implementation:

- `oauth-facebook.php` – build the authorization URL and redirect the user.
- `oauth-callback-facebook.php` – exchange the authorization code for an access token, fetch the user's profile, and sign them in or register them via the existing API.

The API backend already stores `oauth_provider`, `oauth_id`, and `profile_picture` generically, so no code changes are necessary on the Go side beyond handling the new provider string ("facebook").

### Optional: Keep Microsoft Support

If you still want to support Microsoft alongside Facebook you can keep the old configuration and simply add a second set of credentials and handlers. Otherwise, feel free to delete the Microsoft-specific files and environment variables.

## Backend Configuration

### API Model Updates

The User model in `PA - API/models/user.go` includes OAuth fields:

```go
type User struct {
    ID             uuid.UUID `json:"id"`
    Username       string    `json:"username"`
    Email          string    `json:"email"`
    Password       string    `json:"password,omitempty"`
    CreatedAt      string    `json:"created_at,omitempty"`
    LastLogin      string    `json:"last_login,omitempty"`
    OAuthProvider  string    `json:"oauth_provider,omitempty"`
    OAuthID        string    `json:"oauth_id,omitempty"`
    ProfilePicture string    `json:"profile_picture,omitempty"`
}
```

### Validation Updates

Password validation is skipped for OAuth users in `PA - API/app/user.go`:

```go
func ValidateUserDto(user models.User) error {
    // Skip password validation for OAuth users
    if user.OAuthProvider == "" && len(user.Password) < 6 {
        return errors.New("password must be at least 6 characters")
    }
    // Other validations...
}
```

### Repository Updates

Database operations in `PA - API/db/userRepository.go` handle OAuth fields:

```go
func CreateUserInDB(user models.User) error {
    _, err := Db.Exec(
        "INSERT INTO users (id, username, email, password_hash, oauth_provider, oauth_id, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)",
        user.ID.String(),
        user.Username,
        user.Email,
        user.Password,
        user.OAuthProvider,
        user.OAuthID,
        user.ProfilePicture,
    )
    return err
}
```

### Email Lookup Endpoint

New endpoint for OAuth user lookup:

```go
// api.go
registerRoute("POST", "/users/email", "Get user by email", app.GetUserByEmail)

// app/user.go
func GetUserByEmail(w http.ResponseWriter, r *http.Request) {
    // Lookup user by email for OAuth authentication
}
```

## Frontend Integration

### Configuration File

Create `config/oauth-google.php`:

```php
<?php
return [
    'client_id' => getenv('GOOGLE_CLIENT_ID'),
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
    'scopes' => ['email', 'profile']
];
?>
```

### OAuth Initiation

File: `pages/public/oauth-google.php`

```php
<?php
session_start();
require_once '../../vendor/autoload.php';

$config = require_once '../../config/oauth-google.php';

$client = new Google_Client();
$client->setClientId($config['client_id']);
$client->setClientSecret($config['client_secret']);
$client->setRedirectUri($config['redirect_uri']);
$client->setScopes($config['scopes']);

// Generate state token for CSRF protection
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$client->setState($_SESSION['oauth_state']);

// Redirect to Google
$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
?>
```

### OAuth Callback Handler

File: `pages/public/oauth-callback-google.php`

```php
<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../config/db.php';

$config = require_once '../../config/oauth-google.php';

// Validate state parameter (CSRF protection)
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid state parameter');
}

$client = new Google_Client();
$client->setClientId($config['client_id']);
$client->setClientSecret($config['client_secret']);
$client->setRedirectUri($config['redirect_uri']);

// Exchange authorization code for access token
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$client->setAccessToken($token);

// Get user info from Google
$oauth = new Google_Service_Oauth2($client);
$userInfo = $oauth->userinfo->get();

$email = $userInfo->getEmail();
$googleId = $userInfo->getId();
$picture = $userInfo->getPicture();

// Check if user exists
$data = json_encode(['email' => $email]);
$response = askAPI('users/email', 'POST', $data);
$user = json_decode($response, true);

if (!isset($user['id'])) {
    // Create new user
    $username = explode('@', $email)[0] . '_' . substr($googleId, -4);
    $randomPassword = bin2hex(random_bytes(16));

    $newUserData = json_encode([
        'username' => $username,
        'email' => $email,
        'password' => $randomPassword,
        'oauth_provider' => 'google',
        'oauth_id' => $googleId,
        'profile_picture' => $picture
    ]);

    $createResponse = askAPI('users', 'POST', $newUserData);
    $user = json_decode($createResponse, true);
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['oauth_provider'] = 'google';

// Redirect to dashboard
header('Location: ../customers/test');
exit;
?>
```

### Login Page Integration

Update `pages/public/login.php` with OAuth buttons:

```html
<button type="button" class="social-btn google-btn" onclick="loginWithGoogle()">
  <i class="fa-brands fa-google"></i>
  <span>Google</span>
</button>

<script>
  function loginWithGoogle() {
    window.location.href = "oauth-google.php";
  }
</script>
```

## Testing

### Test Google OAuth

1. Start API server: `cd "PA - API" && go run .`
2. Start Apache server via XAMPP
3. Navigate to login page
4. Click "Google" login button
5. Verify redirect to Google consent screen
6. Grant permissions
7. Verify redirect back to application
8. Confirm user is logged in

### Test Microsoft OAuth

Follow same steps, clicking "Microsoft" button instead.

### Verify Database

Check that OAuth fields are populated:

```sql
SELECT username, email, oauth_provider, oauth_id, profile_picture
FROM users
WHERE oauth_provider IS NOT NULL;
```

### Test Account Linking

1. Create account with email via regular registration
2. Attempt OAuth login with same email
3. Verify account is linked (not duplicated)

## Troubleshooting

### Common Issues

**Error: "invalid_client"**

**Cause**: Incorrect client ID or secret

**Solution**: Verify credentials in `.env` match OAuth provider console

---

**Error: "redirect_uri_mismatch"**

**Cause**: Redirect URI doesn't match configured URI

**Solution**:

- Ensure URL-encoding is correct (spaces = `%20`)
- Match exact URL including protocol and path
- No trailing slashes unless configured

---

**Error: "Invalid state parameter"**

**Cause**: CSRF token mismatch

**Solution**:

- Ensure sessions are working
- Clear browser cookies
- Check session configuration

---

**Error: "User not created"**

**Cause**: API validation error or database constraint

**Solution**:

- Check API logs for specific error
- Verify username uniqueness
- Check database constraints

---

**Error: Headers already sent**

**Cause**: Output before redirect

**Solution**: Ensure no whitespace before `<?php` tags

---

**Error: "Consent screen not configured"**

**Cause**: OAuth consent screen not set up in provider console

**Solution**: Complete consent screen configuration in provider console

### Debugging Tips

1. **Enable Error Display**:

   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

2. **Check API Logs**:
   Monitor Go API console for errors

3. **Inspect Tokens**:

   ```php
   var_dump($token); // After token exchange
   ```

4. **Verify State**:
   ```php
   echo "Session state: " . $_SESSION['oauth_state'];
   echo "GET state: " . $_GET['state'];
   ```

## Security Best Practices

### Credential Management

- Store secrets in `.env` file
- Never commit `.env` to version control
- Add `.env` to `.gitignore`
- Use different credentials for development and production
- Rotate secrets periodically

### State Parameter

- Always validate state parameter
- Generate cryptographically secure random state
- Store state in session
- Compare on callback

### HTTPS in Production

- Always use HTTPS in production
- Update redirect URIs to HTTPS
- Enable secure cookie flags
- Implement HSTS headers

### Token Storage

- Never store access tokens long-term
- Exchange tokens server-side only
- Use secure session storage
- Set appropriate session timeouts

## Production Deployment

### Redirect URI Updates

Update OAuth provider configurations with production URLs:

```
https://yourdomain.com/pages/public/oauth-callback-google.php
https://yourdomain.com/pages/public/oauth-callback-microsoft.php
```

### Environment Variables

Update `.env` for production:

```env
GOOGLE_CLIENT_ID=production_client_id
GOOGLE_CLIENT_SECRET=production_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/pages/public/oauth-callback-google.php

MICROSOFT_CLIENT_ID=production_client_id
MICROSOFT_CLIENT_SECRET=production_client_secret
MICROSOFT_REDIRECT_URI=https://yourdomain.com/pages/public/oauth-callback-microsoft.php
```

### Domain Verification

- Verify domain ownership in provider consoles
- Add domain to authorized origins
- Configure CORS if needed

## Further Reading

- [Authentication Guide](AUTHENTICATION.md)
- [API Documentation](API.md)
- [Database Schema](DATABASE.md)
- [Google OAuth Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Microsoft Identity Platform](https://docs.microsoft.com/en-us/azure/active-directory/develop/)
