# API Documentation

This document describes the RESTful API endpoints provided by the UpcycleConnect backend service.

## Table of Contents

- [Overview](#overview)
- [Base URL](#base-url)
- [Authentication](#authentication)
- [Endpoints](#endpoints)
- [Data Models](#data-models)
- [Error Handling](#error-handling)
- [Examples](#examples)

## Overview

The UpcycleConnect API is a RESTful service built with Go that handles user management, authentication, and data operations. The API communicates with a MySQL database and provides JSON responses.

### Technology Stack

- **Language**: Go 1.25+
- **Database**: MySQL 8.0+
- **Authentication**: bcrypt password hashing
- **Data Format**: JSON

### API Features

- User registration and management
- Authentication and login
- OAuth user lookup
- Health monitoring
- Automatic endpoint documentation

## Base URL

```
http://localhost:9999
```

**Production**: Update base URL according to deployment configuration

## Authentication

Currently, the API does not require authentication tokens for requests. Authentication is handled through:

1. **Login Endpoint**: Validates credentials and returns user data
2. **Session Management**: Frontend maintains PHP sessions
3. **Password Hashing**: bcrypt with default cost (10)

### Future Enhancements

- JWT token-based authentication
- API key authentication
- Rate limiting per client

## Endpoints

### Health Check

Verifies API and database connectivity.

**Endpoint**: `GET /`

**Description**: Health check endpoint to verify service status

**Request**: No parameters required

**Response**:

```json
{
  "status": "OK",
  "database": "connected"
}
```

**Error Response** (503 Service Unavailable):

```json
{
  "error": "Service unavailable"
}
```

**Example**:

```bash
curl http://localhost:9999/
```

---

### Get All Users

Retrieves a list of all registered users.

**Endpoint**: `GET /users`

**Description**: Get all users from the database

**Request**: No parameters required

**Response**:

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "username": "john_doe",
    "email": "john@example.com",
    "created_at": "2026-02-01 10:30:00",
    "last_login": "2026-02-06 14:22:15",
    "oauth_provider": null,
    "oauth_id": null,
    "profile_picture": null
  },
  {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "username": "jane_smith_1234",
    "email": "jane@example.com",
    "created_at": "2026-02-05 09:15:30",
    "last_login": null,
    "oauth_provider": "google",
    "oauth_id": "1234567890",
    "profile_picture": "https://lh3.googleusercontent.com/..."
  }
]
```

**Notes**:

- Password hashes are never returned
- OAuth users have `oauth_provider` and `oauth_id` fields populated (e.g. "google", "twitter")
- `last_login` is null if user never logged in

**Example**:

```bash
curl http://localhost:9999/users
```

---

### Create User

Registers a new user account.

**Endpoint**: `POST /users`

**Description**: Create a new user account

**Request Body**:

```json
{
  "username": "new_user",
  "email": "user@example.com",
  "password": "SecurePassword123!",
  "oauth_provider": null,
  "oauth_id": null,
  "profile_picture": null
}
```

**Required Fields**:

- `username` (string, unique): 3-50 characters
- `email` (string, unique): Valid email format
- `password` (string): Minimum 6 characters (optional if oauth_provider is set)

**Optional Fields**:

- `oauth_provider` (string): "google" or "microsoft"
- `oauth_id` (string): Provider's user ID
- `profile_picture` (string): URL to profile image

**Response** (201 Created):

```json
{
  "id": "770e8400-e29b-41d4-a716-446655440002",
  "username": "new_user",
  "email": "user@example.com",
  "created_at": "2026-02-06 15:30:00"
}
```

**Error Response** (400 Bad Request):

```json
{
  "error": "Username already exists"
}
```

**Validation Rules**:

- Username must be unique
- Email must be unique and valid format
- Password required unless OAuth user
- Password is hashed with bcrypt before storage

**Example**:

```bash
curl -X POST http://localhost:9999/users \
  -H "Content-Type: application/json" \
  -d '{
    "username": "new_user",
    "email": "user@example.com",
    "password": "SecurePassword123!"
  }'
```

---

### User Login

Authenticates a user with username/email and password.

**Endpoint**: `POST /login`

**Description**: Authenticate user and return user data

**Request Body**:

```json
{
  "identifier": "john_doe",
  "password": "UserPassword123!"
}
```

**Parameters**:

- `identifier` (string, required): Username or email address
- `password` (string, required): User's password

**Response** (200 OK):

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "username": "john_doe",
  "email": "john@example.com",
  "created_at": "2026-02-01 10:30:00",
  "last_login": "2026-02-06 15:45:30"
}
```

**Error Response** (401 Unauthorized):

```json
{
  "error": "Invalid username/email or password"
}
```

**Process Flow**:

1. Lookup user by username or email
2. Compare provided password with stored hash using bcrypt
3. Update `last_login` timestamp
4. Return user data (excluding password)

**Security**:

- Passwords are never returned in response
- Generic error message prevents user enumeration
- Bcrypt comparison is constant-time

**Example**:

```bash
curl -X POST http://localhost:9999/login \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "john_doe",
    "password": "UserPassword123!"
  }'
```

---

### Get User by Email

Retrieves user information by email address (used for OAuth lookup).

**Endpoint**: `POST /users/email`

**Description**: Get user by email address for OAuth authentication

**Request Body**:

```json
{
  "email": "user@example.com"
}
```

**Parameters**:

- `email` (string, required): Email address to lookup

**Response** (200 OK):

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "username": "john_doe",
  "email": "john@example.com",
  "created_at": "2026-02-01 10:30:00",
  "oauth_provider": "google",
  "oauth_id": "1234567890",
  "profile_picture": "https://lh3.googleusercontent.com/..."
}
```

**Error Response** (404 Not Found):

````json
{
  "error": "User not found"

> **Error responses** always include an `error` field with a human‑readable
> message.  The HTTP status code still indicates the general outcome; clients
> should read the body when available to display a friendly phrase.  For
> example, a login failure returns 401 plus a descriptive string:
>
> ```json
> {
>   "error": "Username or password incorrect"
> }
> ```
}
````

**Use Case**:

- OAuth callback checks if user exists before creating new account
- Prevents duplicate accounts for same email
- Links OAuth provider to existing account

**Example**:

```bash
curl -X POST http://localhost:9999/users/email \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com"
  }'
```

## Data Models

### User Model

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

**Field Descriptions**:

| Field             | Type      | Required    | Description                                      |
| ----------------- | --------- | ----------- | ------------------------------------------------ |
| `id`              | UUID      | Auto        | Unique identifier (generated automatically)      |
| `username`        | string    | Yes         | Unique username (3-50 characters)                |
| `email`           | string    | Yes         | Unique email address                             |
| `password`        | string    | Conditional | Required for non-OAuth users, hashed with bcrypt |
| `created_at`      | timestamp | Auto        | Account creation timestamp                       |
| `last_login`      | timestamp | Auto        | Last successful login timestamp                  |
| `oauth_provider`  | string    | No          | OAuth provider: "google", "microsoft", or null   |
| `oauth_id`        | string    | No          | Provider-specific user ID                        |
| `profile_picture` | string    | No          | URL to profile picture                           |

### Endpoint Model

```go
type Endpoint struct {
    Method      string `json:"method"`
    Path        string `json:"path"`
    Description string `json:"description"`
}
```

## Error Handling

### HTTP Status Codes

| Code | Meaning               | Usage                                |
| ---- | --------------------- | ------------------------------------ |
| 200  | OK                    | Successful request                   |
| 201  | Created               | User successfully created            |
| 400  | Bad Request           | Invalid input, validation error      |
| 401  | Unauthorized          | Authentication failed                |
| 404  | Not Found             | Resource not found, invalid endpoint |
| 500  | Internal Server Error | Server error, database error         |
| 503  | Service Unavailable   | Database connection failed           |

### Error Response Format

All error responses follow this structure:

```json
{
  "error": "Human-readable error message"
}
```

### Common Errors

**Validation Errors**:

```json
{
  "error": "Username already exists"
}
```

**Authentication Errors**:

```json
{
  "error": "Invalid username/email or password"
}
```

**Not Found**:

```json
{
    "error": "Endpoint not found",
    "path": "/invalid/path",
    "available_endpoints": [...]
}
```

**Server Errors**:

```json
{
  "error": "Service unavailable"
}
```

## Examples

### User Registration Flow

```bash
# 1. Create new user
curl -X POST http://localhost:9999/users \
  -H "Content-Type: application/json" \
  -d '{
    "username": "alice",
    "email": "alice@example.com",
    "password": "MySecurePass123"
  }'

# Response:
# {
#   "id": "880e8400-e29b-41d4-a716-446655440003",
#   "username": "alice",
#   "email": "alice@example.com",
#   "created_at": "2026-02-06 16:00:00"
# }
```

### Login Flow

```bash
# 2. Login with credentials
curl -X POST http://localhost:9999/login \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "alice@example.com",
    "password": "MySecurePass123"
  }'

# Response:
# {
#   "id": "880e8400-e29b-41d4-a716-446655440003",
#   "username": "alice",
#   "email": "alice@example.com",
#   "last_login": "2026-02-06 16:05:00"
# }
```

### OAuth User Creation

```bash
# 1. Check if user exists by email
curl -X POST http://localhost:9999/users/email \
  -H "Content-Type: application/json" \
  -d '{
    "email": "bob@gmail.com"
  }'

# Response: 404 Not Found (user doesn't exist)

# 2. Create OAuth user
curl -X POST http://localhost:9999/users \
  -H "Content-Type: application/json" \
  -d '{
    "username": "bob_1234",
    "email": "bob@gmail.com",
    "password": "random_generated_password",
    "oauth_provider": "google",
    "oauth_id": "1234567890",
    "profile_picture": "https://lh3.googleusercontent.com/..."
  }'
```

### Health Check

```bash
# Check API status
curl http://localhost:9999/

# Response:
# {
#   "status": "OK",
#   "database": "connected"
# }
```

## Rate Limiting

Currently, no rate limiting is implemented. Consider implementing:

- Per-IP rate limits
- Per-user rate limits
- Exponential backoff for failed login attempts

## CORS Configuration

CORS is not currently configured. For production deployment with separate frontend domain:

```go
w.Header().Set("Access-Control-Allow-Origin", "https://yourdomain.com")
w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
```

## Testing

### Using cURL

All examples in this document use cURL for testing. Ensure the API is running on port 9999.

### Using Postman

Import this collection:

1. Create new collection "UpcycleConnect API"
2. Set base URL: `http://localhost:9999`
3. Add requests for each endpoint
4. Save example responses

### Automated Testing

Future implementation will include:

- Unit tests for handlers
- Integration tests with test database
- Mock database for isolated testing

## Deployment Considerations

For production deployment:

1. **Environment Variables**: Configure via .env file
2. **HTTPS**: Use TLS certificates
3. **Database Connection Pool**: Configure max connections
4. **Logging**: Implement structured logging
5. **Monitoring**: Add metrics and health checks
6. **Rate Limiting**: Implement request throttling
7. **CORS**: Configure allowed origins

## Further Reading

- [Authentication Guide](AUTHENTICATION.md)
- [OAuth Setup](OAUTH_SETUP.md)
- [Database Schema](DATABASE.md)
- [Deployment Guide](DEPLOYMENT.md)
