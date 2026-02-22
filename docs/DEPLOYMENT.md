# Deployment Guide

This document provides instructions for deploying the UpcycleConnect application to production environments.

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Server Requirements](#server-requirements)
- [Deployment Steps](#deployment-steps)
- [Configuration](#configuration)
- [Security Hardening](#security-hardening)
- [Monitoring](#monitoring)
- [Maintenance](#maintenance)
- [Troubleshooting](#troubleshooting)

## Overview

UpcycleConnect consists of three main components that must be deployed:

1. **PHP Frontend** - Web interface served by Apache
2. **Go API** - Backend service running as a daemon
3. **MySQL Database** - Data persistence layer

### Deployment Architecture

```
                ┌─────────────────┐
                │   Load Balancer │
                │  (Optional SSL) │
                └────────┬────────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
    ┌────┴─────┐                  ┌──────┴────┐
    │  Apache  │                  │   Go API  │
    │  (PHP)   │─────────────────▶│  (Port    │
    └────┬─────┘       REST        │   9999)   │
         │                         └──────┬────┘
         │                                │
         │         ┌──────────────────────┘
         │         │
     ┌───┴─────────┴───┐
     │   MySQL Server  │
     └─────────────────┘
```

## Prerequisites

### Required Software

- **Operating System**: Linux (Ubuntu 20.04+ recommended) or Windows Server
- **Web Server**: Apache 2.4+ with mod_rewrite
- **PHP**: 7.4 or higher with extensions:
  - pdo_mysql
  - mbstring
  - json
  - curl
- **Go**: 1.25+ for API server
- **MySQL**: 8.0+
- **Composer**: Latest version

### Domain and SSL

- Registered domain name
- SSL certificate (Let's Encrypt recommended)
- DNS configured to point to server

### Access Requirements

- SSH access to server
- Root or sudo privileges
- Firewall configuration access

## Server Requirements

### Minimum Specifications

**Development/Staging**:

- CPU: 2 cores
- RAM: 2 GB
- Storage: 20 GB SSD
- Bandwidth: 1 TB/month

**Production**:

- CPU: 4 cores
- RAM: 4 GB
- Storage: 50 GB SSD
- Bandwidth: 5 TB/month

### Recommended Specifications

**Production (High Traffic)**:

- CPU: 8 cores
- RAM: 16 GB
- Storage: 100 GB SSD (or larger based on data growth)
- Bandwidth: Unlimited
- Load balancer for scaling

## Deployment Steps

### 1. Server Setup

#### Ubuntu/Debian Linux

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install PHP and extensions
sudo apt install php php-mysql php-mbstring php-curl php-json -y

# Install MySQL
sudo apt install mysql-server -y

# Install Go
wget https://go.dev/dl/go1.25.2.linux-amd64.tar.gz
sudo tar -C /usr/local -xzf go1.25.2.linux-amd64.tar.gz
echo 'export PATH=$PATH:/usr/local/go/bin' >> ~/.bashrc
source ~/.bashrc

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl restart apache2
```

#### Windows Server

1. Install XAMPP or configure IIS with PHP
2. Download and install Go from https://go.dev/dl/
3. Download and install MySQL Server
4. Download and install Composer

### 2. Clone Repository

```bash
# Navigate to web root
cd /var/www/html  # Linux
# or
cd C:\inetpub\wwwroot  # Windows

# Clone repository
git clone https://github.com/labarbe-leo-gits/UpcycleConnect.git
cd UpcycleConnect
```

### 3. Database Setup

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database
mysql -u root -p
```

```sql
CREATE DATABASE upcycle;
CREATE USER 'upcycle_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON upcycle.* TO 'upcycle_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Import schema
mysql -u upcycle_user -p upcycle < db_schema.sql
```

### 4. Frontend Configuration

```bash
cd "PA - Site Principal"

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.example .env

# Edit .env file
nano .env  # or use your preferred editor
```

Configure `.env`:

```env
# API Configuration
API_PORT=9999
API_HOST=localhost

# reCAPTCHA Keys (get from Google)
RECAPTCHA_SITE_KEY=your_production_site_key
RECAPTCHA_SECRET_KEY=your_production_secret_key

# Google OAuth (get from Google Cloud Console)
GOOGLE_CLIENT_ID=your_production_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_production_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/pages/public/oauth-callback-google.php

# Facebook OAuth (get from Developer Portal)
MICROSOFT_CLIENT_ID=your_production_client_id
MICROSOFT_CLIENT_SECRET=your_production_client_secret
MICROSOFT_TENANT_ID=your_tenant_id
MICROSOFT_REDIRECT_URI=https://yourdomain.com/pages/public/oauth-callback-microsoft.php
```

Update `config/db.php`:

```php
<?php
$host = 'localhost';
$dbname = 'upcycle';
$username = 'upcycle_user';
$password = 'strong_password_here';
?>
```

### 5. Backend API Setup

```bash
cd "PA - API"

# Download Go dependencies
go mod download

# Build binary
go build -o upcycle-api .

# Test the binary
./upcycle-api
```

### 6. Apache Configuration

Create virtual host configuration:

```bash
sudo nano /etc/apache2/sites-available/upcycleconnect.conf
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/UpcycleConnect/PA - Site Principal

    <Directory /var/www/html/UpcycleConnect/PA - Site Principal>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/upcycle_error.log
    CustomLog ${APACHE_LOG_DIR}/upcycle_access.log combined

    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/UpcycleConnect/PA - Site Principal

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    <Directory /var/www/html/UpcycleConnect/PA - Site Principal>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/upcycle_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/upcycle_ssl_access.log combined
</VirtualHost>
```

Enable site:

```bash
sudo a2ensite upcycleconnect.conf
sudo systemctl reload apache2
```

### 7. SSL Certificate

Using Let's Encrypt:

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y

# Obtain certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (already configured by certbot)
sudo certbot renew --dry-run
```

### 8. API Service Configuration

Create systemd service (Linux):

```bash
sudo nano /etc/systemd/system/upcycle-api.service
```

```ini
[Unit]
Description=UpcycleConnect API Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/UpcycleConnect/PA - API
ExecStart=/var/www/html/UpcycleConnect/PA - API/upcycle-api
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Enable and start service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable upcycle-api
sudo systemctl start upcycle-api
sudo systemctl status upcycle-api
```

### 9. Firewall Configuration

```bash
# Allow HTTP and HTTPS
sudo ufw allow 'Apache Full'

# Allow SSH (if not already allowed)
sudo ufw allow OpenSSH

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

### 10. File Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/html/UpcycleConnect

# Set file permissions
sudo find /var/www/html/UpcycleConnect -type f -exec chmod 644 {} \;
sudo find /var/www/html/UpcycleConnect -type d -exec chmod 755 {} \;

# Make API binary executable
sudo chmod +x /var/www/html/UpcycleConnect/PA\ -\ API/upcycle-api
```

## Configuration

### PHP Configuration

Edit `php.ini`:

```bash
sudo nano /etc/php/7.4/apache2/php.ini
```

Recommended settings:

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 300
session.cookie_httponly = On
session.cookie_secure = On
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
```

Restart Apache:

```bash
sudo systemctl restart apache2
```

### MySQL Configuration

Edit MySQL configuration:

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Recommended settings:

```ini
[mysqld]
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 0
query_cache_type = OFF
```

Restart MySQL:

```bash
sudo systemctl restart mysql
```

### Go API Configuration

The API reads configuration from `.env` file. Ensure it's properly configured with production values.

## Security Hardening

### 1. File Security

```bash
# Restrict .env access
chmod 600 "PA - Site Principal/.env"

# Prevent directory listing
echo "Options -Indexes" > /var/www/html/UpcycleConnect/.htaccess

# Protect sensitive files
cat >> /var/www/html/UpcycleConnect/.htaccess << 'EOF'
<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>
EOF
```

### 2. Database Security

```sql
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove remote root access
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Apply changes
FLUSH PRIVILEGES;
```

### 3. Apache Security Headers

Add to virtual host configuration:

```apache
<IfModule mod_headers.c>
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>
```

### 4. Rate Limiting

Install and configure mod_evasive:

```bash
sudo apt install libapache2-mod-evasive -y
sudo nano /etc/apache2/mods-available/evasive.conf
```

```apache
<IfModule mod_evasive20.c>
    DOSHashTableSize 3097
    DOSPageCount 5
    DOSSiteCount 50
    DOSPageInterval 1
    DOSSiteInterval 1
    DOSBlockingPeriod 60
    DOSEmailNotify admin@yourdomain.com
</IfModule>
```

### 5. Fail2Ban

Install and configure:

```bash
sudo apt install fail2ban -y
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local
```

Enable Apache protection:

```ini
[apache-auth]
enabled = true

[apache-badbots]
enabled = true
```

Restart Fail2Ban:

```bash
sudo systemctl restart fail2ban
```

## Monitoring

### 1. Application Logs

**Apache Logs**:

```bash
tail -f /var/log/apache2/upcycle_error.log
tail -f /var/log/apache2/upcycle_access.log
```

**API Logs**:

```bash
sudo journalctl -u upcycle-api -f
```

**MySQL Logs**:

```bash
sudo tail -f /var/log/mysql/error.log
```

### 2. System Monitoring

Install monitoring tools:

```bash
# Install htop for process monitoring
sudo apt install htop -y

# Install iotop for disk I/O monitoring
sudo apt install iotop -y

# Install nethogs for network monitoring
sudo apt install nethogs -y
```

### 3. Uptime Monitoring

Consider using external monitoring services:

- UptimeRobot
- Pingdom
- StatusCake

### 4. Performance Monitoring

**New Relic** (Application Performance Monitoring):

```bash
# Install New Relic PHP agent
wget -O - https://download.newrelic.com/548C16BF.gpg | sudo apt-key add -
echo "deb http://apt.newrelic.com/debian/ newrelic non-free" | sudo tee /etc/apt/sources.list.d/newrelic.list
sudo apt update
sudo apt install newrelic-php5 -y
```

## Maintenance

### Regular Tasks

#### Daily

- Check error logs for issues
- Monitor disk space usage
- Verify API is responsive

#### Weekly

- Review access logs for suspicious activity
- Check database size and growth
- Verify backups are completing

#### Monthly

- Update system packages
- Review and update dependencies
- Performance tuning based on usage patterns

### Backup Strategy

#### Automated Database Backups

```bash
# Create backup script
sudo nano /usr/local/bin/backup-upcycle.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/backups/upcycle"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u upcycle_user -p'strong_password_here' upcycle | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Application backup (optional)
tar -czf "$BACKUP_DIR/app_$DATE.tar.gz" -C /var/www/html UpcycleConnect

# Remove backups older than 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-upcycle.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
```

Add line:

```
0 2 * * * /usr/local/bin/backup-upcycle.sh >> /var/log/backup-upcycle.log 2>&1
```

### Update Procedure

```bash
# Navigate to application directory
cd /var/www/html/UpcycleConnect

# Pull latest code
git pull origin main

# Update PHP dependencies
cd "PA - Site Principal"
composer install --no-dev --optimize-autoloader

# Rebuild API
cd "../PA - API"
go build -o upcycle-api .

# Restart services
sudo systemctl restart apache2
sudo systemctl restart upcycle-api

# Run database migrations if any
mysql -u upcycle_user -p upcycle < migrations/latest.sql
```

## Troubleshooting

### API Not Starting

**Check logs**:

```bash
sudo journalctl -u upcycle-api -n 50
```

**Common issues**:

- Port 9999 already in use
- Database connection failure
- Missing .env file

### 500 Internal Server Error

**Check Apache error log**:

```bash
sudo tail -f /var/log/apache2/upcycle_error.log
```

**Common causes**:

- PHP syntax errors
- Missing PHP extensions
- Incorrect file permissions

### Database Connection Errors

**Verify MySQL is running**:

```bash
sudo systemctl status mysql
```

**Test connection**:

```bash
mysql -u upcycle_user -p -h localhost upcycle
```

### SSL Issues

**Verify certificate**:

```bash
sudo certbot certificates
```

**Renew certificate**:

```bash
sudo certbot renew
```

## Scaling Considerations

### Horizontal Scaling

- Deploy API on multiple servers with load balancer
- Use Redis for session storage (shared sessions)
- Implement database read replicas

### Vertical Scaling

- Increase server resources (CPU, RAM)
- Optimize database indexes
- Enable PHP OpCache

### CDN Integration

- Serve static assets via CDN (Cloudflare, AWS CloudFront)
- Reduce server load
- Improve global performance

## Further Reading

- [Apache Documentation](https://httpd.apache.org/docs/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Go Deployment Best Practices](https://golang.org/doc/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
