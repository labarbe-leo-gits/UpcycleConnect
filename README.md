# UpcycleConnect

Connecting people, artisans and businesses to give new life to used materials — a full-stack platform for discovering, listing and managing upcycling services (customer portal, worker portal, back-office and a REST API)

## Quick summary
- Frontend: PHP (site pages, public/customer/worker portals)
- API: Go RESTful service (business logic, DB access)
- Database: MySQL (schema provided)
- Containerization: Docker Compose files for dev & prod (frontend not containerized by default)

---

## Full feature list (complete — extracted from the codebase)

This section enumerates the platform features implemented in the repository. Where applicable I include the API routes that implement them so the README is both descriptive and actionable.

Authentication & account
- Local account creation and credential-based login
  - `POST /users` — create user
  - `POST /login` — login (returns JWT)
- OAuth2 social login (Google / Microsoft / Facebook integration)
  - Frontend OAuth handlers and API endpoint `POST /oauth/login` — exchange OAuth identity for JWT
- Password reset flow (email verification codes)
  - `POST /forgot-password` — send/verify/reset using code
  - Email templates for reset/notifications
- Two-factor authentication (TOTP)
  - `POST /users/{id}/2fa/setup` — generate TOTP secret & QR
  - `POST /users/{id}/2fa/enable` — enable 2FA
  - `POST /users/{id}/2fa/disable` — disable 2FA
  - `POST /2fa/verify` — verify OTP during authentication
- JWT authentication middleware & internal API key support
  - Middleware: JWT validation, `X-Internal-Key` and role-based middleware

User management & profile
- Retrieve users and profiles
  - `GET /users` — list users (pagination, filters)
  - `GET /users/{id}` — user by UUID
  - `GET /profile/{username}` — public profile by username
- User profile updates and personal data export
  - `PATCH /users/{id}` — update profile
  - `GET /users/{id}/personal-data` — export personal data (GDPR-style export)
- Password change & account deletion
  - `PATCH /users/{id}/password` — change password
  - `DELETE /users/{id}/delete` — delete account (with password + 2FA verification)
- Badges & reviews
  - `POST /users/{id}/badges` — award badge
  - `GET /users/{id}/reviews` / `POST /users/{id}/reviews` / `PATCH` / `DELETE` — reviews management
- Friends / social connections
  - `GET /friends`, `POST /friends`, `PUT /friends/{id}/accept`, `DELETE /friends/{id}`, `GET /friends/status/{id}`

Profile pictures & media
- Upload and manage profile pictures
  - `POST /users/{id}/profile-picture` — upload profile picture (file upload)
  - `GET /users/{id}/profile-picture` — get current profile picture
  - Profile picture history: listing, restore, delete
    - `GET /users/{id}/profile-picture/history`
    - `PATCH /users/{id}/profile-picture/history/{historyID}/restore`
    - `DELETE /users/{id}/profile-picture/history/{historyID}`
    - `DELETE /users/{id}/profile-picture/history` — delete all history

Listings / Marketplace (Annonces)
- Create, read, update, delete listings ("annonces")
  - `GET /annonces` — list
  - `GET /annonces/{id}` — get a listing
  - `POST /annonces` — create
  - `PATCH /annonces/{id}` — update
  - `DELETE /annonces/{id}` — delete (admin)
  - Admin status workflow
    - `PATCH /annonces/{id}/status` — set pending/approved/rejected
  - Images for annonces
    - `GET /annonces/{id}/images`
    - `POST /annonces/{id}/images` — upload images
  - View counters
    - `PATCH /annonces/{id}/views` — increment view count
  - `GET /annonces/{id}/images` — list images

Products & services
- Service catalog and CRUD
  - `GET /products/services` — list services
  - `POST /products/services` — create service
  - `GET /products/services/{id}` — get details
  - `PATCH /products/services/{id}` — update
  - `DELETE /products/services/{id}` — delete
- Affected employees for services
  - `GET /products/services/{id}/affected-employees`
  - `POST /products/services/{id}/affected-employees`
  - `DELETE /products/services/{id}/affected-employees/{aeID}`

Orders, payments & payouts
- Orders
  - `GET /orders` — list orders
  - `POST /orders` — create order
  - `GET /orders/{id}` — get order details
- Payment requests and status changes
  - `GET /payment-requests` — list
  - `POST /payment-requests` — create
  - `PATCH /payment-requests/{id}/status` — update status
- Payouts & banking
  - `GET /payouts`, `POST /payouts`
  - `GET /users/{id}/payouts`
  - `POST /banking-details` — create banking details
  - `GET /banking-details`, `GET /banking-details/{id}`, `GET /users/{id}/banking-details`
- Subscriptions and billing
  - Internal webhook-like endpoints:
    - `POST /internal/subscription/activate`
    - `POST /internal/subscription/revoke`
    - `POST /internal/subscription/invoice`
  - Subscription tiers endpoints:
    - `GET /subscription-tiers`, `GET /subscription-tier`, `POST/PUT/DELETE /subscription-tier`
  - Commission management
    - `GET /commission-settings`, `PUT /commission-settings`
    - Commission transactions CRUD endpoints

Refunds & disputes
- Refund requests
  - `GET /refund-requests`, `GET /refund-requests/{id}`, `POST /refund-requests`
  - `PATCH /refund-requests/{id}/status`

Deposits & files
- Deposit requests and attached files
  - `GET /deposits`, `GET /deposits/{id}`, `POST /deposits`, `PATCH /deposits/{id}`, `PATCH /deposits/{id}/status`
  - File attachments:
    - `GET /deposits/{id}/files`
    - `POST /deposits/{id}/files`
    - `DELETE /deposits/{id}/files/{fileId}`

Containers & items (conteneurs)
- `GET /conteneurs`, `POST /conteneurs`, `GET /conteneurs/{id}`, `PATCH /conteneurs/{id}`, `DELETE /conteneurs/{id}`
- `GET /conteneurs/{id}/items` — list accepted items inside a conteneur

Projects, steps & materials (content creation)
- Projects (how-to / upcycling projects)
  - `GET /projects`, `GET /projects/{id}`, `POST /projects`, `PATCH /projects/{id}`, `DELETE /projects/{id}`
  - Project steps & images:
    - `GET /projects/{id}/steps`, `POST /projects/{id}/steps`, `PATCH /projects/{id}/steps/{sID}`, `DELETE /projects/{id}/steps/{sID}`
    - `POST /projects/{id}/steps/{sID}/images`, `GET /projects/{id}/steps/{sID}/images`
    - Materials for steps: `POST /projects/{id}/steps/{sID}/materials`, `GET /projects/{id}/steps/{sID}/materials`, `DELETE /projects/{id}/steps/{sID}/materials/{fID}`
  - Likes and comments:
    - `GET /projects/{id}/likes`, `POST /projects/{id}/likes`, `DELETE /projects/{id}/likes`
    - `GET /projects/{id}/comments`, `POST /projects/{id}/comments`, `PATCH /projects/{id}/comments/{cID}`, `DELETE /projects/{id}/comments/{cID}`

Forums, discussions & messaging
- Forums & posts
  - `GET /forums`, `GET /forums/{id}`, `POST /forums`, `DELETE /forums/{id}`, `PATCH /forums/{id}`
  - `GET /forums/{id}/posts`, `POST /forums/{id}/posts`, `PATCH /forums/{id}/posts/{pID}`, `DELETE /forums/{id}/posts/{pID}`
- User discussions & chat
  - `GET /users/{id}/discussions`, `POST /users/{id}/discussions`
  - Messages endpoints:
    - `GET /global/messages`, `GET /discussions/{id}/messages`, `GET /groups/{id}/messages`
    - WebSockets: `GET /ws` — WebSocket connection for real-time messaging
  - Group conversations:
    - `POST /groups`, `POST /groups/{id}/members`, `GET /groups`, `GET /groups/{id}`, `DELETE`/`PATCH` endpoints (some reserved/todo)

Notifications & newsletters
- Notifications
  - `GET /notifications`, `POST /notifications`
  - `GET /users/{id}/notifications`, `PATCH /notifications/{id}/read`, `DELETE /notifications/{id}`, `PATCH /users/{id}/notifications/read` — mark read
- Notification campaigns (admin)
  - `GET /notification-campaigns`, `GET /notification-campaigns/{id}`
  - `POST /notification-campaigns`, `PATCH /notification-campaigns/{id}`, `DELETE /notification-campaigns/{id}`
  - `POST /notification-campaigns/{id}/send`
- Newsletters (admin)
  - `GET /newsletters`, `GET /newsletters/{id}`, `POST /newsletters`, `PATCH /newsletters/{id}`, `PATCH /newsletter/{id}/status`, `DELETE /newsletters/{id}`, `POST /newsletters/{id}/send`
  - `GET /newsletter-subscribers`

Search, categories & taxonomy
- Categories CRUD
  - `GET /categories`, `POST /categories`, `GET /categories/{id}`, `PATCH /categories/{id}`, `DELETE /categories/{id}`

Favorites & user content curation
- Favorites (user saved items)
  - `GET /users/{id}/favorites`, `POST /users/{id}/favorites`, `DELETE /users/{id}/favorites/{fID}`

Ratings, tips & reactions
- Tips (knowledge snippets)
  - `GET /tips`, `GET /tips/{id}`, `POST /tips`, `PATCH /tips/{id}`, `DELETE /tips/{id}`
  - Comments & reactions on tips:
    - `GET /tips/{id}/comments`, `POST /tips/{id}/comments`, `PATCH /tips/{id}/comments/{cID}`, `DELETE /tips/{id}/comments/{cID}`
    - Reactions: `GET /tips/{id}/reactions`, `POST /tips/{id}/reactions`, `DELETE /tips/{id}/reactions`
- Polls & voting
  - `POST /polls`, `GET /polls/{id}`, options and votes endpoints (CRUD)

Planning & scheduling
- Planner endpoints for users
  - `GET /users/{id}/planning`, `POST /users/{id}/planning`, `PATCH /users/{id}/planning/{pID}`, `DELETE /users/{id}/planning/{pID}`
  - `GET /planning` — admin listing

Admin & back-office features
- Admin role checks and role-based endpoints (RoleMiddleware)
- Many admin-only routes protected by `RoleMiddleware(3)` (role 3 = admin)
  - Pending registrations listing / delete (`/pending-registrations`)
  - Dashboard metrics: `GET /dashboard-metrics`
  - Notification campaigns, newsletter management
  - Commission settings, revenue reports, partnership campaigns, training session management, etc.
- Internal admin endpoints protected by `X-Internal-Key`:
  - `POST /internal/subscription/*`, `POST /internal/promotion/complete`, `POST /internal/sql` — safe read-only SQL for analytics

Reporting & analytics
- Revenue reports
  - `GET /revenue-reports`, `GET /revenue-report`, `POST /revenue-report`, `DELETE /revenue-report`, `GET /current-month-revenue`, `GET /revenue-breakdown`
- Dashboard metrics (`GET /dashboard-metrics`)
- `POST /internal/sql` — read-only SQL analytics for internal admin key

Security, moderation & content safety
- Content moderation endpoint (bad-word list + Gemini AI integration)
  - `POST /moderate` — moderate text
- Rate-limiting support (per-IP limiter implemented, can be toggled)
- Ban management
  - `POST /ban`, `DELETE /ban/{id}`, `GET /bans/{id}`, `GET /users/{id}/bans`
- 2FA support and verification endpoints (see 2FA section above)
- reCAPTCHA v3 integration on the frontend

AI / LLM usage & quotas
- Per-user LLM usage tracking & admin quota controls
  - `GET /users/{id}/llm` — usage & quota
  - `PATCH /users/{id}/llm` — update usage and optionally set quota (admin-only to set quota)

Miscellaneous platform features
- Upcycling score calculation from weight and material
  - `GET /upcycling-score` — compute score
- Favorites uniqueness (DB unique index enforced)
- Profile & personal data export aggregation (contracts, invoices, projects, orders, deposits, favorites, notifications, refunds, payouts, bans, subscription, LLM usage)
- File storage for uploads (images, deposit files, profile pictures) — stored in `files/uploads/*`
- Translations & public string extraction utilities (`extract_public_strings.js`, `extract_pages_public_strings.js`)
- Docker Compose developer & production stacks (service definitions & images)
- Swagger/OpenAPI generation endpoints:
  - `/swagger/*` UI and `/swagger/doc.json`, `/openapi.json` (generated by API from registered endpoints)

Frontend & UI
- PHP pages and structure:
  - Public pages (home, login, register), customer portal, worker portal, back-office pages
  - Reusable includes: `includes/header.php`, `includes/footer.php`, `includes/auth.php`
  - Static assets: `assets/css`, `assets/js`, `assets/img`
- URL rewriting via `.htaccess` for clean URLs
- JS-enhanced features: featured offers loader, easter-egg, etc.
- reCAPTCHA v3 usage on relevant forms
- Built-in email templates for important flows (password reset, account deleted, etc.)

Developer & deployment
- Go module with dependencies in `PA - API/go.mod`
- PHP composer manifests for frontend and BO projects
- DB schema file: `db_schema.sql`
- Docker Compose:
  - `docker-compose.dev.yml` — development config with volume mounts & rebuild guidance
  - `docker-compose.prod.yml` — production config
- Env file examples in `PA - API/.env.example` and `PA - Site Principal/.env.example`
- `ensureFavoritesTable` helper at API startup to automatically create missing favorites table (safety for dev)

---

## How to run it

### Prerequisites
- **Docker** and **Docker Compose** installed ([download here](https://www.docker.com/products/docker-desktop))
- **Git** installed
- For production: Go 1.25+ and PHP 7.4+ (if running without Docker)

### Quick Start (Development with Docker)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/labarbe-leo-gits/UpcycleConnect.git
   cd UpcycleConnect
   ```

2. **Set up environment files:**
   
   Create `.env` files from the examples provided:
   
   ```bash
   cp "PA - API/.env.example" "PA - API/.env"
   cp "PA - Site Principal/.env.example" "PA - Site Principal/.env"
   cp "PA - BO/.env.example" "PA - BO/.env"  # Back-office
   cp "PA - Automated Processes/.env.example" "PA - Automated Processes/.env"
   ```

   Update the `.env` files with your configuration (API keys, OAuth credentials, etc.). For development, many values have reasonable defaults in the docker-compose setup.

3. **Start all services:**
   ```bash
   docker-compose -f docker-compose.dev.yml up
   ```

   This will spin up:
   - **MySQL Database** (port 3306)
   - **Go API** (port 9999)
   - **Frontend** (port 8081)
   - **Back-office** (port 8080)
   - **GLPI** (port 8082, for IT asset management)
   - **Automated Processes** (background jobs/cron)

4. **Access the application:**
   - Frontend: http://localhost:8081
   - Back-office: http://localhost:8080
   - API: http://localhost:9999
   - API Docs: http://localhost:9999/swagger/
   - GLPI: http://localhost:8082

5. **Stop the services:**
   ```bash
   docker-compose -f docker-compose.dev.yml down
   ```

### Manual Setup (Without Docker)

#### API Setup (Go)
```bash
cd "PA - API"
cp .env.example .env
# Edit .env with your database credentials
go mod download
go run .
```

#### Frontend Setup (PHP)
```bash
cd "PA - Site Principal"
cp .env.example .env
# Install dependencies
composer install
# For development, use PHP built-in server or configure your web server
php -S localhost:8081
```

#### Back-office Setup (PHP)
```bash
cd "PA - BO"
cp .env.example .env
composer install
php -S localhost:8080
```

#### Database Setup
```bash
mysql -u root -p < db_schema.sql
```

### Configuration

Key environment variables to configure:

**API (`.env` in `PA - API/`):**
- `JWT_SECRET_KEY` — Secret for JWT token signing
- `DB_*` — Database connection details
- `BADWORDS_FILE` — Path to bad words JSON file

**Frontend (`.env` in `PA - Site Principal/`):**
- `API_HOST` / `API_PORT` — API server address
- `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` — Google reCAPTCHA v3
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` — Google OAuth
- `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET` — Facebook OAuth
- `STRIPE_PUBLISHABLE_KEY` / `STRIPE_SECRET_KEY` — Stripe payment keys
- `GEMINI_API_KEY` — Google Gemini API for content moderation

**Database:**
- Default dev credentials (from `docker-compose.dev.yml`):
  - User: `app`
  - Password: `secret`
  - Database: `upcycle`

### Troubleshooting

- **Database connection fails:** Ensure MySQL is running and credentials match your `.env`
- **API not responding:** Check if port 9999 is available; verify API container logs: `docker-compose -f docker-compose.dev.yml logs api`
- **Frontend cannot reach API:** Verify `API_HOST` and `API_PORT` in frontend `.env` match your setup
- **Permission issues:** Ensure the `files/` directory is writable by the API and frontend containers

### Database Schema

The database schema is automatically initialized on first run from `db_schema.sql`. To manually reset:
```bash
mysql -u app -p upcycle < db_schema.sql
```

### Additional Resources

- API documentation: Visit `/swagger/` or `/swagger/doc.json` when API is running
- Feature list: See "Full feature list" section above for all implemented endpoints
