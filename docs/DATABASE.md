# Database Schema Documentation

This document describes the database structure for the UpcycleConnect application.

## Table of Contents

- [Overview](#overview)
- [Database Configuration](#database-configuration)
- [Schema](#schema)
- [Tables](#tables)
- [Indexes](#indexes)
- [Relationships](#relationships)
- [Data Types](#data-types)
- [Migrations](#migrations)

## Overview

UpcycleConnect uses MySQL as its primary database engine. The schema is designed to support user authentication, OAuth integration, and future expansion for items and orders.

### Database Information

- **Database Name**: `upcycle`
- **Engine**: MySQL 8.0+
- **Character Set**: utf8mb4 (default)
- **Collation**: utf8mb4_unicode_ci (default)

## Database Configuration

### Connection Settings

**Development**:

```
Host: localhost
Port: 3306
Database: upcycle
User: root
Password: (configured locally)
```

**Production**: Configure via environment variables or configuration file.

### PHP Configuration

File: `config/db.php`

```php
<?php
$host = 'localhost';
$dbname = 'upcycle';
$username = 'root';
$password = '';

function getDB() {
    global $host, $dbname, $username, $password;
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}
?>
```

### Go Configuration

File: `PA - API/db/db.go`

```go
func NewDB() *sql.DB {
    dsn := "root:@tcp(localhost:3306)/upcycle?parseTime=true"
    db, err := sql.Open("mysql", dsn)
    if err != nil {
        log.Fatal("Database connection failed:", err)
    }
    return db
}
```

## Schema

### Complete Schema Definition

File: `db_schema.sql`

```sql
CREATE DATABASE IF NOT EXISTS upcycle;
USE upcycle;

CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    oauth_provider VARCHAR(20) NULL,
    oauth_id VARCHAR(255) NULL,
    profile_picture VARCHAR(500) NULL,
    UNIQUE INDEX idx_oauth (oauth_provider, oauth_id)
);
```

### Initialization

To create the database and tables:

```bash
mysql -u root -p < db_schema.sql
```

Or via XAMPP phpMyAdmin:

1. Open phpMyAdmin
2. Click "Import" tab
3. Choose `db_schema.sql` file
4. Click "Go"

## Tables

### users

Stores user account information including authentication credentials and OAuth data.

#### Structure

| Column            | Type         | Null | Default           | Description                           |
| ----------------- | ------------ | ---- | ----------------- | ------------------------------------- |
| `id`              | CHAR(36)     | NO   | UUID()            | Primary key, auto-generated UUID      |
| `username`        | VARCHAR(255) | NO   | -                 | Unique username for login             |
| `email`           | VARCHAR(255) | NO   | -                 | Unique email address                  |
| `password_hash`   | VARCHAR(255) | NO   | -                 | Bcrypt hashed password                |
| `created_at`      | TIMESTAMP    | NO   | CURRENT_TIMESTAMP | Account creation timestamp            |
| `last_login`      | TIMESTAMP    | YES  | NULL              | Last successful login timestamp       |
| `oauth_provider`  | VARCHAR(20)  | YES  | NULL              | OAuth provider: "google", "microsoft" |
| `oauth_id`        | VARCHAR(255) | YES  | NULL              | Provider-specific user identifier     |
| `profile_picture` | VARCHAR(500) | YES  | NULL              | URL to profile picture                |

#### Constraints

**Primary Key**: `id`

**Unique Constraints**:

- `username` - Prevents duplicate usernames
- `email` - Prevents duplicate email addresses
- `idx_oauth (oauth_provider, oauth_id)` - Prevents duplicate OAuth accounts

#### Default Values

- `id`: Auto-generated UUID (MySQL 8.0+)
- `created_at`: Current timestamp on insert
- `last_login`: NULL until first login
- OAuth fields: NULL for non-OAuth users

#### Sample Data

```sql
INSERT INTO users (
    id,
    username,
    email,
    password_hash,
    oauth_provider,
    oauth_id,
    profile_picture
) VALUES (
    UUID(),
    'john_doe',
    'john@example.com',
    '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
    NULL,
    NULL,
    NULL
);
```

#### OAuth User Example

```sql
INSERT INTO users (
    id,
    username,
    email,
    password_hash,
    oauth_provider,
    oauth_id,
    profile_picture
) VALUES (
    UUID(),
    'jane_smith_1234',
    'jane@gmail.com',
    '$2y$10$randomhashedpassword',
    'google',
    '1234567890',
    'https://lh3.googleusercontent.com/...'
);
```

## Indexes

### Primary Index

```sql
PRIMARY KEY (id)
```

**Purpose**: Unique identifier for each user

**Type**: Clustered index

**Performance**: O(log n) lookup by ID

### Unique Indexes

#### username

```sql
UNIQUE INDEX (username)
```

**Purpose**: Enforce unique usernames, fast login lookup

**Queries Optimized**:

- `WHERE username = ?`
- User registration validation

#### email

```sql
UNIQUE INDEX (email)
```

**Purpose**: Enforce unique emails, support email-based login

**Queries Optimized**:

- `WHERE email = ?`
- OAuth user lookup

#### idx_oauth

```sql
UNIQUE INDEX idx_oauth (oauth_provider, oauth_id)
```

**Purpose**: Prevent duplicate OAuth accounts

**Queries Optimized**:

- OAuth user lookup by provider and ID
- Prevents same Google/Microsoft account registering twice

## Relationships

### Current Relationships

The current schema has a single table with no foreign key relationships.

### Future Relationships

Planned tables and relationships:

```
users (1) ───── (N) items
  │
  └───── (N) orders
          │
          └───── (N) order_items
```

#### items Table (Planned)

```sql
CREATE TABLE items (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10, 2),
    status VARCHAR(20) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### orders Table (Planned)

```sql
CREATE TABLE orders (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    customer_id CHAR(36) NOT NULL,
    worker_id CHAR(36),
    status VARCHAR(20) DEFAULT 'pending',
    total DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (worker_id) REFERENCES users(id)
);
```

## Data Types

### UUID (CHAR(36))

**Format**: `550e8400-e29b-41d4-a716-446655440000`

**Storage**: 36 characters (32 hex + 4 hyphens)

**Generation**: MySQL `UUID()` function

**Advantages**:

- Globally unique
- No auto-increment collision
- Supports distributed systems

**Disadvantages**:

- Larger than INT (36 bytes vs 4 bytes)
- Slower index lookups than INT

### VARCHAR vs TEXT

**VARCHAR(255)**: Used for usernames, emails, short strings

**VARCHAR(500)**: Used for URLs (profile pictures)

**TEXT**: Reserved for long-form content (future use)

### TIMESTAMP

**Storage**: 4 bytes

**Range**: 1970-01-01 to 2038-01-19

**Timezone**: Stored as UTC, converted to session timezone

**NULL Support**: Used for `last_login` (unknown until first login)

### Password Hash

**Type**: VARCHAR(255)

**Format**: Bcrypt hash

**Example**: `$2y$10$abcdefghijklmnopqrstuvwxyz...`

**Length**: Typically 60 characters, VARCHAR(255) allows for future algorithms

## Migrations

### Version History

#### Version 1.0 (Initial Schema)

```sql
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL
);
```

#### Version 1.1 (OAuth Support)

```sql
ALTER TABLE users
ADD COLUMN oauth_provider VARCHAR(20) NULL,
ADD COLUMN oauth_id VARCHAR(255) NULL,
ADD COLUMN profile_picture VARCHAR(500) NULL,
ADD UNIQUE INDEX idx_oauth (oauth_provider, oauth_id);
```

### Running Migrations

#### Fresh Installation

```bash
mysql -u root -p < db_schema.sql
```

#### Upgrading Existing Database

Apply migrations in order:

```bash
# Add OAuth fields to existing users table
mysql -u root -p upcycle < migrations/001_add_oauth_fields.sql
```

### Migration Best Practices

1. **Backup First**: Always backup database before migrations
2. **Test Locally**: Test migrations on development database
3. **Version Control**: Keep all migration scripts in version control
4. **Rollback Plan**: Create DOWN migration for each UP migration
5. **Data Migration**: Handle existing data carefully

### Rollback Migrations

To remove OAuth fields:

```sql
ALTER TABLE users
DROP INDEX idx_oauth,
DROP COLUMN oauth_provider,
DROP COLUMN oauth_id,
DROP COLUMN profile_picture;
```

## Query Examples

### User Authentication

```sql
-- Login by username or email
SELECT id, username, email, password_hash, oauth_provider
FROM users
WHERE username = ? OR email = ?;
```

### OAuth User Lookup

```sql
-- Find user by email (OAuth)
SELECT id, username, email, oauth_provider, oauth_id, profile_picture
FROM users
WHERE email = ?;

-- Find user by OAuth provider and ID
SELECT id, username, email
FROM users
WHERE oauth_provider = ? AND oauth_id = ?;
```

### Create User

```sql
INSERT INTO users (id, username, email, password_hash)
VALUES (UUID(), ?, ?, ?);
```

### Update Last Login

```sql
UPDATE users
SET last_login = CURRENT_TIMESTAMP
WHERE id = ?;
```

### List All Users

```sql
SELECT id, username, email, created_at, last_login, oauth_provider
FROM users
ORDER BY created_at DESC;
```

## Performance Optimization

### Current Optimizations

1. **Indexed Columns**: username, email, (oauth_provider, oauth_id)
2. **UUID Primary Key**: Fast lookups
3. **Appropriate Data Types**: Minimal storage overhead

### Recommended Optimizations

1. **Add Index on `last_login`** (if filtering by activity):

   ```sql
   CREATE INDEX idx_last_login ON users(last_login);
   ```

2. **Partitioning** (for very large datasets):

   ```sql
   -- Partition by creation year
   PARTITION BY RANGE (YEAR(created_at)) (
       PARTITION p2024 VALUES LESS THAN (2025),
       PARTITION p2025 VALUES LESS THAN (2026),
       PARTITION p_future VALUES LESS THAN MAXVALUE
   );
   ```

3. **Query Cache**: Enable MySQL query cache for repeated queries

### Monitoring

Monitor these metrics:

- Table size: `SELECT COUNT(*) FROM users;`
- Index usage: `SHOW INDEX FROM users;`
- Slow queries: Enable slow query log
- Lock contention: Monitor `SHOW ENGINE INNODB STATUS;`

## Backup and Restore

### Backup Database

```bash
# Full backup
mysqldump -u root -p upcycle > backup_$(date +%Y%m%d).sql

# Structure only
mysqldump -u root -p --no-data upcycle > schema_backup.sql

# Data only
mysqldump -u root -p --no-create-info upcycle > data_backup.sql
```

### Restore Database

```bash
mysql -u root -p upcycle < backup_20260206.sql
```

### Automated Backups

Create cron job (Linux) or Task Scheduler (Windows):

```bash
# Daily backup at 2 AM
0 2 * * * mysqldump -u root -pYOUR_PASSWORD upcycle > /backups/upcycle_$(date +\%Y\%m\%d).sql
```

## Security Considerations

### Password Storage

- Never store plain-text passwords
- Use bcrypt with cost factor 10 or higher
- Passwords hashed in Go API before storage

### SQL Injection Prevention

- Always use prepared statements
- Never concatenate user input into queries
- Validate and sanitize all inputs

### Access Control

- Use least-privilege database users
- Separate read/write credentials
- Restrict remote database access

### Data Encryption

- Enable TLS for database connections in production
- Consider field-level encryption for sensitive data
- Encrypt backups

## Troubleshooting

### Common Issues

**Error: Unknown column 'oauth_provider'**

**Solution**: Run migration to add OAuth fields

**Error: Duplicate entry for key 'username'**

**Solution**: Username already exists, choose different username

**Error: Incorrect string value**

**Solution**: Ensure utf8mb4 character set for emoji support

## Further Reading

- [API Documentation](API.md)
- [Authentication Guide](AUTHENTICATION.md)
- [OAuth Setup](OAUTH_SETUP.md)
- [MySQL Documentation](https://dev.mysql.com/doc/)
