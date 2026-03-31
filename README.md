# UpcycleConnect

A full-stack web application for connecting upcycling enthusiasts with service providers. Built with PHP frontend, Go backend API, and MySQL database.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Getting Started](#getting-started)
- [Project Structure](#project-structure)
- [Documentation](#documentation)
- [License](#license)

## Overview

UpcycleConnect is a platform that facilitates connections between customers seeking upcycling services and workers providing those services. The application features OAuth authentication, session management, and a RESTful API backend.

## Architecture

The application follows a separation of concerns architecture:

- **Frontend**: PHP-based web interface with session-based authentication
- **Backend**: Go RESTful API handling business logic and data operations
- **Database**: MySQL for persistent data storage

```
┌─────────────────┐     ┌──────────────┐     ┌──────────────┐
│   PHP Frontend  │────▶│   Go API     │────▶│   MySQL DB   │
│  (Site Principal)│     │  (Port 9999) │     │   (upcycle)  │
└─────────────────┘     └──────────────┘     └──────────────┘
```

## Features

### Current Features

- User registration and authentication
- OAuth 2.0 integration (Google, Microsoft)
- Session-based authentication with protected routes
- Customer portal with profile management
- Responsive UI with custom CSS
- RESTful API with health checks
- URL rewriting for clean URLs
- reCAPTCHA v3 integration for security

### In Development

- Password reset functionality
- Item listing and management
- Order processing system
- Worker portal
- Back office administration

## Technology Stack

### Frontend

- **Language**: PHP 7.4+
- **Dependencies**:
  - PHPMailer 7.0
  - Google API Client 2.19
- **Security**: reCAPTCHA v3, CSRF protection
- **Styling**: Custom CSS with Font Awesome icons

### Backend API

- **Language**: Go 1.25+
- **Framework**: Standard library with custom routing
- **Dependencies**:
  - go-sql-driver/mysql v1.9.3
  - google/uuid v1.6.0
  - golang.org/x/crypto v0.47.0
  - joho/godotenv v1.5.1

### Database

- **Engine**: MySQL
- **Schema**: User management with OAuth support

### Development Tools

- **Server**: Apache (XAMPP)
- **Version Control**: Git

## Getting Started

Detailed instructions for installing and running the project are available in the [Setup Guide](docs/SETUP.md). The brief overview below covers the most common steps; developers should consult the guide for additional context and troubleshooting tips.

### Prerequisites

- PHP 7.4 or higher
- Go 1.25 or higher
- MySQL 8.0 or higher
- Apache server (XAMPP recommended for Windows)
- Composer (PHP dependency manager)

### Installation Summary

1. **Clone the repository**

   ```bash
   git clone https://github.com/labarbe-leo-gits/UpcycleConnect.git
   cd UpcycleConnect
   ```

2. **Database Setup**

   ```bash
   mysql -u root -p < db_schema.sql
   ```

3. **Frontend Setup**

   ```bash
   cd "PA - Site Principal"
   composer install
   cp .env.example .env         # create local configuration
   # Edit `.env` and fill in API keys, database credentials, and other
   # environment-specific values. See docs/SETUP.md for details.
   ```

4. **Backend API Setup**

   ```bash
   cd "PA - API"
   go mod download
   cp .env.example .env         # database credentials, JWT secret, etc.
   ```

5. **Start services**
   - Ensure MySQL and Apache are running (start via XAMPP on Windows or
     using your operating system service manager).
   - In a shell, launch the API:

     ```bash
     cd "PA - API"
     go run .
     ```

     The API will listen on the port defined in the environment (default
     `9999`).

   - Navigate to the frontend at
     `http://localhost/PA/PA - Site Principal/pages/public/index.php`.

6. **Configuration**

   Create or update the `.env` file(s) as described above. Both the
   frontend and the API use environment variables; the examples shipped
   in their respective directories (`PA - Site Principal/.env.example` and
   `PA - API/.env.example`) list the variables that must be populated.

For complete step‑by‑step instructions, environment variable details, and
common troubleshooting advice, see [docs/SETUP.md](docs/SETUP.md).

```env
API_PORT=9999
API_HOST=localhost
RECAPTCHA_SITE_KEY=your_recaptcha_site_key
RECAPTCHA_SECRET_KEY=your_recaptcha_secret_key
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=your_redirect_uri
```

## Project Structure

```
PA/
├── docs/                          # Documentation
│   ├── API.md                     # API documentation
│   ├── AUTHENTICATION.md          # Authentication guide
│   ├── DATABASE.md                # Database schema
│   ├── DEPLOYMENT.md              # Deployment guide
│   ├── SETUP.md                   # Local development setup
│   └── OAUTH_SETUP.md             # OAuth configuration
│
├── PA - API/                      # Go Backend API
│   ├── app/                       # Application handlers
│   │   └── user.go                # User-related endpoints
│   ├── db/                        # Database layer
│   │   ├── db.go                  # Database connection
│   │   └── userRepository.go      # User data operations
│   ├── models/                    # Data models
│   │   ├── endpoint.go            # API endpoint model
│   │   └── user.go                # User model
│   ├── api.go                     # Main API entry point
│   └── go.mod                     # Go dependencies
│
├── PA - Site Principal/           # PHP Frontend
│   ├── assets/                    # Static assets
│   │   ├── css/                   # Stylesheets
│   │   ├── img/                   # Images
│   │   ├── js/                    # JavaScript files
│   │   └── json/                  # JSON data files
│   ├── config/                    # Configuration files
│   │   ├── db.php                 # Database configuration
│   │   └── oauth-google.php       # OAuth configuration
│   ├── includes/                  # Reusable components
│   │   ├── auth.php               # Authentication helpers
│   │   ├── header.php             # Public header
│   │   ├── footer.php             # Footer
│   │   └── customers-header.php   # Customer portal header
│   ├── pages/                     # Application pages
│   │   ├── public/                # Public pages
│   │   │   ├── index.php          # Homepage
│   │   │   ├── login.php          # Login page
│   │   │   ├── register.php       # Registration
│   │   │   └── oauth-*.php        # OAuth handlers
│   │   ├── customers/             # Customer portal
│   │   │   ├── index.php          # Dashboard
│   │   │   ├── test.php           # Profile page
│   │   │   └── .htaccess          # URL rewriting
│   │   └── workers/               # Worker portal
│   ├── vendor/                    # Composer dependencies
│   └── .env                       # Environment configuration
│
├── PA - BO/                       # Back Office (Admin)
│
├── db_schema.sql                  # Database schema
└── README.md                      # This file
```

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- [Setup Guide](docs/SETUP.md) - Local development and environment configuration
- [API Documentation](docs/API.md) - API endpoints and usage
- [Authentication Guide](docs/AUTHENTICATION.md) - How to use authentication system
- [OAuth Setup](docs/OAUTH_SETUP.md) - Configure Google/Facebook OAuth
- [Database Schema](docs/DATABASE.md) - Database structure and relationships
- [Deployment Guide](docs/DEPLOYMENT.md) - Production deployment instructions

## Development

For a complete walkthrough of the development environment, including
acceptable values for the environment variables and troubleshooting tips,
refer to the [Setup Guide](docs/SETUP.md).

### Running the Application Locally

1. Ensure the `.env` files in `PA - BO` and `PA - API` are created and valid.
2. Start the MySQL server (e.g. via XAMPP control panel or system service).
3. Start the Go API:
   ```bash
   cd "PA - API" && go run .
   ```
4. Start or restart Apache (XAMPP or system service).
5. Open a browser and navigate to the configured frontend URL.

## Docker (Dev + Prod)

### Docker images et Dockerfiles existants

- `PA - API/Dockerfile`: build multi-étapes Go + distroless
- `PA - BO/Dockerfile`: build multi-étapes Composer + Apache
- `docker-compose.dev.yml` : mode développement (hot-reload via volumes
  (PHP), rebuild API à chaque `docker compose ... --build`)
- `docker-compose.prod.yml` : mode production (restart=always, pas de code
  monté en volume pour le backoffice, utilisation de la DB sans ports exposés
  localement sauf API/BO)

### Lancer en mode développement

```bash
cd c:\xampp\htdocs\PA
docker compose -f docker-compose.dev.yml up --build -d
```

Vérifier les logs :

```bash
docker compose -f docker-compose.dev.yml logs -f api
docker compose -f docker-compose.dev.yml logs -f bo
```

### Lancer en mode production

```bash
cd c:\xampp\htdocs\PA
docker compose -f docker-compose.prod.yml up --build -d
```

### Stop / restart

```bash
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.dev.yml down
```

### Push vers un registre privé

```bash
docker login registry.example.com

docker tag upcycleconnect-api:latest registry.example.com/upcycleconnect-api:1.0.0
docker push registry.example.com/upcycleconnect-api:1.0.0

docker tag upcycleconnect-bo:latest registry.example.com/upcycleconnect-bo:1.0.0
docker push registry.example.com/upcycleconnect-bo:1.0.0
```

---

(Notes : `PA - Site Principal` n’est pas dockerisé selon la consigne.)

### API Endpoints

The API is available at `http://localhost:9999` with the following endpoints:

- `GET /` - Health check
- `GET /users` - Get all users
- `POST /users` - Create new user
- `POST /users/email` - Get user by email (OAuth lookup)
- `POST /login` - User authentication

For detailed API documentation, see [docs/API.md](docs/API.md).

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is proprietary software. All rights reserved.

## Support

For issues and questions:

- Create an issue in the GitHub repository
- Contact the development team

## Acknowledgments

- PHPMailer for email functionality
- Google API Client for OAuth integration
- Font Awesome for icons
- Go MySQL Driver for database connectivity
