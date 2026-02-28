# Setup Guide

This guide walks through the installation and configuration steps required to get
UpcycleConnect running on a development machine. It covers local database
initialisation, environment variable configuration, and how to start the web
server and API.

> All documentation files are located under `docs/`; do not modify the README
> except for high-level summaries.

## Prerequisites

Before proceeding make sure the following software is installed:

- PHP 7.4 or later with the `pdo_mysql`, `mbstring`, `json` and `curl`
  extensions.
- Go 1.25 or later.
- MySQL 8.0 or later (a running instance; XAMPP is recommended on Windows).
- Apache 2.4+ (the XAMPP Control Panel can start/stop Apache and MySQL on
  Windows).
- Composer (PHP dependency manager).
- Git for version control.

## Repository Checkout

```bash
git clone https://github.com/labarbe-leo-gits/UpcycleConnect.git
cd UpcycleConnect
```

## Database

1. Start your MySQL server (e.g. via XAMPP or `sudo service mysql start`).
2. Create the schema and a user:

   ```sql
   mysql -u root -p < db_schema.sql
   ```

   Optionally create a dedicated database user and grant privileges.

3. Verify that the `upcycle` database exists and the tables are present.

## Environment Configuration

Both the PHP frontend and the Go backend rely on environment variables. Template
files are provided in each respective directory; you must create a working
`.env` file based on the example and supply your own values before running the
application.

### Frontend (.env)

```text
# file: PA - Site Principal/.env.example
API_PORT=9999
API_HOST=localhost
RECAPTCHA_SITE_KEY=YOUR_RECAPTCHA_SITE_KEY_HERE
RECAPTCHA_SECRET_KEY=YOUR_RECAPTCHA_SECRET_KEY_HERE
GOOGLE_CLIENT_ID=YOUR_GOOGLE_CLIENT_ID_HERE
GOOGLE_CLIENT_SECRET=YOUR_GOOGLE_CLIENT_SECRET_HERE
GOOGLE_REDIRECT_URI=http://127.0.0.1/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-google
STRIPE_PUBLISHABLE_KEY=YOUR_STRIPE_PUBLISHABLE_KEY_HERE
STRIPE_SECRET_KEY=YOUR_STRIPE_SECRET_KEY_HERE
APP_API_KEY=YOUR_APP_API_KEY_HERE
FACEBOOK_CLIENT_ID=your_facebook_app_id_here
FACEBOOK_CLIENT_SECRET=your_facebook_app_secret_here
FACEBOOK_REDIRECT_URI=http://127.0.0.1/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-facebook
```

Copy the example to create a real file:

```bash
cd "PA - Site Principal"
cp .env.example .env
```

Edit `.env` and provide valid keys, database credentials and any other values
required by your deployment (for local development most defaults can remain).

### API (.env)

```text
# file: PA - API/.env.example
DB_USER=YOUR_DB_USER_HERE
DB_PASSWORD=YOUR_DB_PASSWORD_HERE
DB_HOST=YOUR_DB_HOST_HERE
DB_NAME=YOUR_DB_NAME_HERE
DB_PORT= YOUR_DB_PORT
JWT_SECRET_KEY=YOUR_JWT_SECRET_KEY_HERE
```

Copy and edit the file similarly:

```bash
cd "PA - API"
cp .env.example .env
```

The API uses these values to open a connection to the database; the JWT secret
is used for future token support and may be any random string.

> **Important:** do not commit `.env` files to version control. Keep them
> private and out of the repository.

## Install Dependencies

Install PHP packages for the frontend and Go modules for the API:

```bash
cd "PA - Site Principal"
composer install

cd "../PA - API"
go mod download
```

## Starting Services

1. Ensure the MySQL service is running. On Windows with XAMPP open the Control
   Panel and start Apache and MySQL. On Linux use the service manager
   (`sudo systemctl start apache2 mysql`).

2. Launch the Go API in a terminal window:

   ```bash
   cd "PA - API"
   go run .
   ```

   The API listens on the host/port specified by `API_HOST`/`API_PORT` in the
   frontend `.env` file (default `localhost:9999`).

3. Point your browser to the frontend:

   ```text
   http://localhost/PA/PA - Site Principal/pages/public/index.php
   ```

   (Adjust the path according to your web server configuration.)

## Common Issues

- **.env file not found:** ensure the file exists in the correct directory and
  that the application has read permissions.
- **Database connection errors:** verify the credentials in the API `.env`
  match a valid MySQL user and that the database server is running.
- **API listening on wrong port:** confirm the `API_PORT` value and check for
  other processes using that port.
- **Frontend shows blank page:** check Apache error logs and browser developer
  console for PHP errors.

Once the services are running and the environment is configured, you can begin
developing features, running the API directly or building a binary for
production. Refer to the other documentation files in `docs/` for detailed
instructions on particular subsystems (authentication, deployment, etc.).
