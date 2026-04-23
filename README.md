# Coupon Processing System

A Laravel-based coupon management system built with async job queues, Redis reservations, and real-time validation. Users can apply coupon codes at checkout and get instant feedback — no page reloads, no waiting.

---

## What It Does

When a user types in a coupon code and clicks Apply, a lot happens behind the scenes in under two seconds:

1. The API receives the request and immediately returns a 202 response
2. A validation job is dispatched to the queue
3. The job checks all the rules — expiry, usage limits, cart value, categories
4. If valid, the coupon slot is atomically reserved in Redis
5. The frontend polls for the result and updates the UI

This async approach means the server never blocks, and the system can handle many users applying coupons at the same time without race conditions.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Language | PHP 8.2 |
| Queue | Redis + Laravel Queue |
| Cache | Redis |
| Database | MySQL 8 |
| Frontend | Blade + Tailwind CSS v4 |
| Icons | Blade Heroicons |
| Web server | Nginx |
| Containers | Docker |

---

## Project Structure

```
app/
├── Http/Controllers/
│   ├── CouponController.php      # API endpoints for coupon flow
│   ├── CartController.php        # Session-based cart management
│   └── CheckoutController.php    # Checkout and order submission
│
├── Jobs/
│   ├── ValidateCouponJob.php     # Runs all rule checks (high queue)
│   ├── ConsumeCouponJob.php      # Writes permanent usage to DB (default queue)
│   ├── ReleaseCouponJob.php      # Frees Redis reservation (default queue)
│   ├── RecordCouponEventJob.php  # Audit event logger (low queue)
│   ├── UpdateCartJob.php         # Updates cart with discount (default queue)
│   └── CleanExpiredReservationsJob.php  # Scheduled cleanup (low queue)
│
├── Services/
│   ├── CouponRuleEngine.php      # Evaluates all 7 coupon rules
│   ├── CouponReservationService.php  # Atomic Redis reserve/release
│   └── FailedJobHandler.php      # Central failed job recovery
│
└── Models/
    ├── Coupon.php
    ├── CouponSetting.php         # Versioned rule configuration
    ├── CouponUsage.php           # Permanent consumption records
    └── CouponEvent.php           # Full lifecycle audit log

resources/views/
├── layouts/
│   ├── app.blade.php             # Master layout
│   └── navbar.blade.php
├── partials/
│   ├── coupon-box.blade.php      # Reusable coupon input component
│   └── toast.blade.php           # Global notification
├── home.blade.php                # Product listing
├── cart/index.blade.php          # Cart page
├── checkout/index.blade.php      # Checkout with coupon UI
└── order/success.blade.php       # Order confirmation

docker/
├── app/
│   ├── Dockerfile
│   └── php.ini
├── nginx/
│   └── default.conf
└── mysql/
    └── my.cnf
```

---

## How the Coupon Flow Works

```
User types coupon → POST /api/apply-coupon
                          ↓
                    Returns 202 immediately
                          ↓
                    ValidateCouponJob (high queue)
                          ↓
                    ┌─────────────────────────┐
                    │   7 Rule Checks:        │
                    │   • Expiry              │
                    │   • Global limit        │
                    │   • Per-user limit      │
                    │   • Min cart value      │
                    │   • First-time user     │
                    │   • Category match      │
                    │   • Time window         │
                    └─────────────────────────┘
                          ↓
                    Atomic Redis reservation (5 min TTL)
                          ↓
                    Frontend polls /api/coupon-status
                          ↓
                    Shows result to user
                          ↓
                    User places order
                          ↓
                    ConsumeCouponJob → writes to MySQL
                                     → releases Redis
```

---

## Queue Priority

Three separate queues run in parallel — higher priority queues are never blocked by lower ones.

| Queue | Jobs | Why |
|---|---|---|
| `high` | ValidateCouponJob | User is actively waiting |
| `default` | ConsumeCouponJob, ReleaseCouponJob, UpdateCartJob | Order processing |
| `low` | RecordCouponEventJob, CleanExpiredReservationsJob | Analytics, cleanup |

---

## Coupon Rules

Each coupon is configured through a versioned `CouponSetting` record. The rule engine always reads the latest active version at job execution time — so if you update a coupon's rules while jobs are queued, the new rules apply immediately.

| Rule | Description |
|---|---|
| Expiry | Coupon must not be past its expiry date |
| Global limit | Total redemptions across all users |
| Per-user limit | How many times one user can use it |
| Min cart value | Minimum order total required |
| First-time user | Only for users with no previous orders |
| Category | Cart must contain items from allowed categories |
| Time window | Coupon only active between specific dates/times |

---

## Getting Started

### Option A — Docker (recommended)

> **Requirements:** Docker Desktop installed and running on your machine.

#### Important notes before you start

- Stop XAMPP's MySQL before running Docker — both use port 3306 and will conflict. Docker maps MySQL to port 3307 on your host to reduce this, but stopping XAMPP MySQL is safer.
- The `app` container does not have Node.js. Run `npm` commands on your local machine, not inside Docker.
- Always set `APP_ENV=production` when using Docker — otherwise Laravel tries to connect to a Vite dev server that isn't running inside the container.

#### Step by step

```bash
# 1. Clone the project
git clone <your-repo-url>
cd next_coupon

# 2. Copy the Docker environment file
cp .env.docker .env

# 3. Set APP_ENV to production — critical for assets to load correctly
# Open .env and set:
# APP_ENV=production
# APP_DEBUG=false

# 4. Build frontend assets on your LOCAL machine (not inside Docker)
npm install
npm run build

# 5. Delete the Vite hot file if it exists — it will break asset loading
# Windows PowerShell:
if (Test-Path public/hot) { Remove-Item public/hot }

# 6. Add vendor volume to avoid Composer conflicts (see docker-compose.yml note below)
# Make sure your .dockerignore contains: vendor/

# 7. Build and start all containers
docker compose build --no-cache
docker compose up -d

# 8. Check all containers are running
docker compose ps

# 9. Generate app key
docker compose exec app php artisan key:generate

# 10. Run migrations
docker compose exec app php artisan migrate --force

# 11. Seed test coupons
docker compose exec app php artisan db:seed --force

# 12. Clear and cache config
docker compose exec app php artisan config:clear
docker compose exec app php artisan config:cache
```

Open `http://localhost:8000` in your browser.

---

#### Docker troubleshooting

**MySQL container shows Error or Unhealthy**

This usually means a port conflict with XAMPP or a stale data volume.

```bash
# Stop XAMPP MySQL first, then:
docker compose down -v   # -v removes volumes — clears stale MySQL data
docker compose up -d
```

**Assets not loading — ERR_ADDRESS_INVALID on port 5173**

Laravel is trying to connect to the Vite dev server. Fix:

1. Set `APP_ENV=production` in `.env`
2. Delete `public/hot` if it exists
3. Run `npm run build` on your local machine
4. Clear config: `docker compose exec app php artisan config:clear`
5. Hard refresh: `Ctrl + Shift + R`

**Composer install fails — "uncommitted changes in vendor/"**

Your local `vendor/` folder is conflicting with Docker's install. Fix:

Add `vendor/` to `.dockerignore`, then add a named volume for vendor in `docker-compose.yml`:

```yaml
# Under the app service volumes:
volumes:
  - .:/var/www
  - vendor_data:/var/www/vendor   # add this

# Under the volumes section at the bottom:
volumes:
  coupon_mysql_data:
  coupon_redis_data:
  vendor_data:                    # add this
```

Then rebuild:

```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```

**npm not found inside container**

The `app` container is PHP-only. Always run npm on your local machine:

```bash
# Run this on your machine, not inside Docker
npm install
npm run build
```

---

#### Docker services

| Container | Purpose | Port |
|---|---|---|
| `coupon_app` | PHP-FPM — runs Laravel | internal |
| `coupon_nginx` | Web server | 8000 |
| `coupon_mysql` | MySQL database | 3307 (host) |
| `coupon_redis` | Redis | 6379 |
| `coupon_worker_high` | Processes `high` queue | — |
| `coupon_worker_default` | Processes `default` queue | — |
| `coupon_worker_low` | Processes `low` queue | — |
| `coupon_scheduler` | Runs `schedule:run` every minute | — |

---

#### Docker daily commands

```bash
# Start all containers
docker compose up -d

# Stop all containers
docker compose down

# View all logs live
docker compose logs -f

# View Laravel log only
docker compose exec app tail -f storage/logs/laravel.log

# Run any artisan command
docker compose exec app php artisan <command>

# Access MySQL shell
docker compose exec mysql mysql -u coupon_user -psecret coupon_system

# Access Redis CLI
docker compose exec redis redis-cli

# Restart a single container
docker compose restart worker_high

# Rebuild from scratch (clears everything)
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

---

### Option B — XAMPP (local development)

Requirements: PHP 8.2, MySQL, Redis, Composer, Node.js

```bash
# 1. Install PHP dependencies
composer install --ignore-platform-reqs

# 2. Install Node dependencies and build assets
npm install
npm run build

# 3. Set up environment
cp .env.example .env
php artisan key:generate

# 4. Update .env:
# DB_HOST=127.0.0.1
# REDIS_HOST=127.0.0.1
# REDIS_CLIENT=predis
# QUEUE_CONNECTION=redis

# 5. Install predis (required on Windows — no phpredis extension)
composer require predis/predis --ignore-platform-reqs

# 6. Run migrations and seed
php artisan migrate
php artisan db:seed

# 7. Start Redis (download from github.com/microsoftarchive/redis/releases)
redis-server

# 8. Start everything — needs 3 separate terminals
php artisan serve                                              # terminal 1
npm run dev                                                    # terminal 2
php artisan queue:work redis --queue=high,default,low          # terminal 3
```

Open `http://localhost:8000` in your browser.

#### XAMPP notes

- Horizon (`php artisan horizon`) does not work on Windows — use `queue:work` instead.
- Always use `REDIS_CLIENT=predis` on Windows. The `phpredis` extension is Linux-only.
- If Redis shows "connection refused", make sure `redis-server` is running. Download from the Microsoft archive on GitHub.

---

## Test Coupon Codes

These are seeded automatically when you run `db:seed`.

| Code | Type | Value | Notes |
|---|---|---|---|
| `SAVE10` | Percentage | 10% off | No restrictions |
| `FIRST50` | Fixed | RM 50 off | Min cart RM 100 |
| `FREESHIP` | Free shipping | — | One use per user |
| `NEWUSER` | Percentage | 15% off | First-time users only |
| `CATEGORY1` | Percentage | 20% off | Footwear category only |
| `EXPIRED` | Percentage | 5% off | Already expired — tests invalid path |
| `MAXED` | Percentage | 25% off | Usage limit reached — tests limit path |

---

## API Endpoints

All coupon-related API endpoints live under `/api`. No authentication required — a guest session ID is used instead.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/ping` | Health check |
| `POST` | `/api/apply-coupon` | Dispatch validation job, returns 202 |
| `GET` | `/api/coupon-status` | Poll job result |
| `POST` | `/api/checkout/complete` | Consume coupon after successful order |
| `POST` | `/api/checkout/fail` | Release reservation on failed checkout |

A full Postman collection is included at `CouponSystem.postman_collection.json`.

---

## Environment Variables

Key variables to configure in `.env`:

```env
# App
APP_ENV=production     # use 'production' for Docker, 'local' for XAMPP dev
APP_DEBUG=false        # set false for Docker

# Database
DB_HOST=mysql          # use 'mysql' for Docker, '127.0.0.1' for XAMPP
DB_DATABASE=coupon_system
DB_USERNAME=coupon_user
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis       # use 'redis' for Docker, '127.0.0.1' for XAMPP
REDIS_CLIENT=predis    # always predis — works on both Windows and Docker
QUEUE_CONNECTION=redis

# Horizon (optional)
HORIZON_MAIL_TO=
HORIZON_SLACK_WEBHOOK=
```

---

## Monitoring

The Horizon dashboard shows real-time queue stats, job throughput, and failed jobs.

```
http://localhost:8000/horizon
```

In local development, the dashboard is open to everyone. In production, restrict access by setting `HORIZON_ALLOWED_IPS` in `.env`.

---

## Failure Recovery

The system handles failures at every layer:

- **Job retries** — each job retries automatically with backoff (3–5 attempts)
- **Idempotency** — retried jobs never cause duplicate DB writes or double reservations
- **Ghost reservations** — `CleanExpiredReservationsJob` runs every minute and releases any stuck Redis reservations
- **Permanent failures** — `FailedJobHandler` logs critical failures and releases ghost reservations automatically
- **Manual retry** — `php artisan queue:retry all` retries all failed jobs

---

## Common Commands

```bash
# Check queue health
php artisan queue:monitor redis:high,redis:default,redis:low

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Clear all caches
php artisan cache:clear && php artisan config:clear

# Check Horizon status
php artisan horizon:status
```

For Docker, prefix all artisan commands with `docker compose exec app`.

---

## Notes

- No login/authentication — uses a session-based guest ID. Add Laravel Sanctum or Breeze if you need user accounts.
- `ext-pcntl` and `ext-posix` are Linux-only. On Windows/XAMPP, use `queue:work` instead of `horizon`.
- Always run `npm run build` on your local machine before starting Docker — the `app` container has no Node.js.
- Delete `public/hot` whenever switching from `npm run dev` to `npm run build` — the hot file tells Laravel to use the Vite dev server.
- Always use `php artisan horizon:terminate` (not `horizon:kill`) when deploying — it waits for in-flight jobs to finish.
