# Authentication System Guide

This document describes how to use the authentication system in UpcycleConnect. The system provides session-based authentication with support for traditional username/password login and OAuth 2.0 providers.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Authentication Functions](#authentication-functions)
- [Usage Examples](#usage-examples)
- [Session Management](#session-management)
- [Security Considerations](#security-considerations)

## Overview

The authentication system is built around a centralized helper module located at `/includes/auth.php`. It provides four main functions for managing user authentication state and enforcing access control.

### Architecture

```
User Request
    ↓
auth.php → Check Session
    ↓
Protected Page / Redirect to Login
```

## Quick Start

To use authentication in any PHP file:

```php
<?php
require_once '../../includes/auth.php';
requireLogin(); // Redirect if not authenticated

// Protected content here
?>
```

## Authentication Functions

### 1. requireLogin()

Forces authentication for a page. Redirects unauthenticated users to the login page.

**Signature:**

```php
function requireLogin(): void
```

**Behavior:**

- Checks if user is logged in via session
- If not authenticated, stores current page name in session
- Redirects to login page
- After successful login, redirects user back to original page

**Example:**

```php
<?php
require_once '../../includes/auth.php';
requireLogin(); // Must be called before any output

$user = getLoggedInUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($user['username']) ?></h1>
</body>
</html>
```

### 2. isLoggedIn()

Checks authentication status without redirecting.

**Signature:**

```php
function isLoggedIn(): bool
```

**Returns:**

- `true` if user is authenticated
- `false` if user is not authenticated

**Example:**

```php
<?php
require_once '../../includes/auth.php';

if (isLoggedIn()) {
    $user = getLoggedInUser();
    echo "Logged in as: " . htmlspecialchars($user['username']);
} else {
    echo '<a href="/pages/public/login.php">Please login</a>';
}
?>
```

### 3. getLoggedInUser()

Retrieves current user information from session.

**Signature:**

```php
function getLoggedInUser(): ?array
```

**Returns:**

- User data array if authenticated
- `null` if not authenticated

**User Data Structure:**

```php
[
    'id' => 'uuid-string',
    'username' => 'username',
    'email' => 'user@example.com',
    'oauth_provider' => 'google' | 'microsoft' | null
]
```

**Example:**

```php
<?php
require_once '../../includes/auth.php';

$user = getLoggedInUser();

if ($user) {
    echo "User ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Email: " . $user['email'] . "\n";

    if ($user['oauth_provider']) {
        echo "Authenticated via: " . $user['oauth_provider'];
    } else {
        echo "Authenticated via: Password";
    }
}
?>
```

### 4. logout()

Terminates user session and redirects to login page.

**Signature:**

```php
function logout(): void
```

**Behavior:**

- Destroys all session data
- Redirects to login page
- Does not return

**Example:**

```php
<?php
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout(); // Logs out and redirects
}
?>
```

## Usage Examples

### Example 1: Simple Protected Page

```php
<?php
require_once '../../includes/auth.php';
requireLogin();

$user = getLoggedInUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
</head>
<body>
    <h1>Profile: <?= htmlspecialchars($user['username']) ?></h1>
    <p>Email: <?= htmlspecialchars($user['email']) ?></p>

    <form method="POST" action="logout.php">
        <button type="submit">Logout</button>
    </form>
</body>
</html>
```

### Example 2: Conditional Content Display

```php
<?php
require_once '../../includes/auth.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Homepage</title>
</head>
<body>
    <header>
        <nav>
            <?php if (isLoggedIn()): ?>
                <?php $user = getLoggedInUser(); ?>
                <a href="/pages/customers/index.php">Dashboard</a>
                <span>Hello, <?= htmlspecialchars($user['username']) ?></span>
                <form method="POST" action="/pages/customers/logout.php" style="display:inline;">
                    <button type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="/pages/public/login.php">Login</a>
                <a href="/pages/public/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <!-- Page content -->
    </main>
</body>
</html>
```

### Example 3: Ajax Authentication Check

```php
<?php
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user = getLoggedInUser();
echo json_encode([
    'authenticated' => true,
    'user' => [
        'username' => $user['username'],
        'email' => $user['email']
    ]
]);
?>
```

### Example 4: Logout Handler

```php
<?php
// logout.php
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout();
}

// If accessed via GET, redirect to login
header('Location: ../public/login.php');
exit;
?>
```

## Session Management

### Session Variables

When a user successfully authenticates, the following session variables are set:

| Variable                        | Type           | Description                                                       |
| ------------------------------- | -------------- | ----------------------------------------------------------------- |
| `$_SESSION['user_id']`          | string         | User's UUID                                                       |
| `$_SESSION['username']`         | string         | Username                                                          |
| `$_SESSION['email']`            | string         | Email address                                                     |
| `$_SESSION['oauth_provider']`   | string or null | OAuth provider ('google', 'microsoft') or null for password login |
| `$_SESSION['page_after_login']` | string         | Stored page name for post-login redirect                          |

### Session Lifecycle

1. **Login**: Session created with user data
2. **Navigation**: Session persists across page loads
3. **Timeout**: Session expires after PHP's `session.gc_maxlifetime` (default: 1440 seconds / 24 minutes)
4. **Logout**: Session explicitly destroyed

### Session Security

The authentication system implements several security measures:

- Session IDs are regenerated on login
- Session data is validated on each request
- HTTPS recommended for production
- Session cookies have HttpOnly flag
- CSRF protection via tokens

## Security Considerations

### Best Practices

1. **Always use HTTPS in production**
   - Prevents session hijacking
   - Protects credentials in transit

2. **Validate user input**
   - Sanitize all user data
   - Use prepared statements for database queries

3. **Implement rate limiting**
   - Prevent brute force attacks
   - Limit login attempts

4. **Set secure session configuration**

   ```php
   ini_set('session.cookie_httponly', 1);
   ini_set('session.cookie_secure', 1); // HTTPS only
   ini_set('session.use_strict_mode', 1);
   ```

5. **Use password hashing**
   - Passwords are hashed with bcrypt in the API
   - Never store plain-text passwords

### OAuth Security

- OAuth tokens are exchanged server-side
- State parameter prevents CSRF
- Redirect URIs are whitelisted
- User info is fetched from trusted providers

### Common Vulnerabilities to Avoid

- **Session Fixation**: Session ID regenerated on login
- **XSS**: Always use `htmlspecialchars()` when outputting user data
- **CSRF**: Use POST for state-changing operations
- **SQL Injection**: API uses prepared statements

## File Reference

### Created Files

- `/includes/auth.php` - Authentication helper functions
- `/pages/customers/logout.php` - Logout handler
- `/pages/customers/test.php` - Example protected page
- `/pages/public/login.php` - Login page with OAuth
- `/pages/public/oauth-callback-google.php` - Google OAuth callback

### Dependencies

- PHP Session extension
- MySQL database
- Go API running on configured port

## Troubleshooting

### Common Issues

**Issue**: "Headers already sent" error when calling `requireLogin()`

**Solution**: Ensure `requireLogin()` is called before any output (HTML, whitespace, etc.)

```php
<?php
require_once '../../includes/auth.php';
requireLogin(); // Must be before any HTML
?>
<!DOCTYPE html>
```

**Issue**: Session not persisting across pages

**Solution**: Verify session is started and session cookie path is correct

**Issue**: Redirect loop after login

**Solution**: Check that login page doesn't call `requireLogin()`

**Issue**: User data not available after login

**Solution**: Verify API returns all required fields (id, username, email)

## Migration from Other Systems

If migrating from a different authentication system:

1. Update all protected pages to use `requireLogin()`
2. Replace manual session checks with `isLoggedIn()`
3. Replace user data retrieval with `getLoggedInUser()`
4. Update logout links to POST to logout handler
5. Configure OAuth providers as needed

## Further Reading

- [OAuth Setup Guide](OAUTH_SETUP.md)
- [API Documentation](API.md)
- [Database Schema](DATABASE.md)
