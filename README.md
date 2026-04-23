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
│   └── FailedJobHandler.php     # Central failed job recovery
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

Make sure Docker Desktop is running first.

```bash
# 1. Clone the project
git clone <your-repo-url>
cd next_coupon

# 2. Set up environment
cp .env.docker .env

# 3. Start all containers
docker compose up -d

# 4. Generate app key
docker compose exec app php artisan key:generate

# 5. Run migrations
docker compose exec app php artisan migrate --force

# 6. Seed test coupons
docker compose exec app php artisan db:seed --force

# 7. Build frontend assets
docker compose exec app npm install
docker compose exec app npm run build
```

Open `http://localhost:8000` in your browser.

---

### Option B — XAMPP (local development)

Requirements: PHP 8.2, MySQL, Redis, Composer, Node.js

```bash
# 1. Install dependencies
composer install --ignore-platform-reqs
npm install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Update .env with your database and Redis settings
# DB_HOST=127.0.0.1, REDIS_CLIENT=predis, QUEUE_CONNECTION=redis

# 4. Run migrations and seed
php artisan migrate
php artisan db:seed

# 5. Start everything (needs 3 terminals)
php artisan serve           # terminal 1 — web server
npm run dev                 # terminal 2 — Tailwind CSS
php artisan queue:work redis --queue=high,default,low  # terminal 3 — queue
```

Open `http://localhost:8000` in your browser.

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

## Docker Services

```bash
# Start all services
docker compose up -d

# Check status
docker compose ps

# View logs
docker compose logs -f

# Run artisan commands
docker compose exec app php artisan <command>

# Access MySQL
docker compose exec mysql mysql -u coupon_user -psecret coupon_system

# Access Redis CLI
docker compose exec redis redis-cli

# Stop everything
docker compose down
```

---

## Environment Variables

Key variables to configure in `.env`:

```env
# Database
DB_HOST=mysql          # use 'mysql' for Docker, '127.0.0.1' for XAMPP
DB_DATABASE=coupon_system
DB_USERNAME=coupon_user
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis       # use 'redis' for Docker, '127.0.0.1' for XAMPP
REDIS_CLIENT=predis    # always use predis on Windows
QUEUE_CONNECTION=redis

# Horizon dashboard alerts (optional)
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

# Release stuck reservations manually
php artisan coupons:release-stuck

# Clear all caches
php artisan cache:clear && php artisan config:clear

# Check Horizon status
php artisan horizon:status
```

---

## Notes

- This system has no login/authentication — it uses a session-based guest ID instead. Add Laravel Sanctum or Breeze if you need user accounts.
- The `ext-pcntl` and `ext-posix` extensions required by Horizon are Linux-only. On Windows, use `php artisan queue:work` instead of `php artisan horizon`.
- Always use `php artisan horizon:terminate` (not `horizon:kill`) when deploying — it waits for in-flight jobs to finish before stopping.

