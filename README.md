# WorkEddy

Production-oriented implementation of WorkEddy aligned to `requirements.md` stack and architecture.

## Stack alignment from requirements

- **Backend language/runtime:** PHP 8.2+
- **Routing:** FastRoute
- **Logging:** Monolog
- **DB layer:** Doctrine DBAL
- **Auth/JWT:** firebase/php-jwt
- **Queue:** Configurable (`QUEUE_DRIVER=redis|db`)
- **Database:** MySQL 8+
- **Frontend:** Bootstrap 5 + Vanilla JS (+ Alpine.js included)
- **Infra:** Docker Compose + Nginx + API + Worker
- **Storage:** Server filesystem under `/storage/uploads/...`

## Delivered modules

- Authentication and organization onboarding
- User role management (admin/supervisor/worker/observer)
- Task management
- Manual scan engine with normalized risk scoring
- Video scan ingestion + asynchronous queue processing
- MediaPipe-based posture metrics extraction in worker pipeline
- Dashboard analytics
- Observer rating API
- Usage tracking for manual/video scans
- Prescriptive control action lifecycle (recommendation -> action -> verification)
- Worker coaching endpoint with multilingual tips (EN/ES/ZH/AR)
- Scoped ergonomics copilot personas (supervisor, safety manager, engineer, auditor)
- Live scan is deferred to V2 and disabled by default in the current release

## API

Base URL: `/api/v1` (legacy unprefixed routes are also accepted).

- `POST /auth/signup`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`
- `GET /users`, `POST /users`
- `GET /tasks`, `POST /tasks`, `GET /tasks/{id}`
- `POST /scans/manual`, `GET /scans`, `GET /scans/{id}`
- `GET /dashboard`
- `POST /observer-rating`
- `GET /control-actions`, `POST /control-actions/from-control`, `PUT /control-actions/{id}`, `POST /control-actions/{id}/verify`
- `GET /worker/coaching`
- `POST /copilot/{persona}`
- `GET /health`
- Internal worker endpoints: `POST /internal/worker/jobs/next`, `POST /internal/worker/scans/complete`, `POST /internal/worker/scans/fail` (token-authenticated)

## Setup

```bash
docker compose up --build
```

```bash
# one-off seed command (explicit)
docker compose run --rm --profile ops seed
```

Notes:
- The API container now retries startup tasks and runs migrations automatically before `php-fpm` starts.
- If `vendor/` is empty in the mounted volume, the API container runs `composer install` automatically.
- The `seed` service reuses the same app image as `api` (`workeddy-app`) to avoid building a duplicate PHP image.
- `video-retention` service runs `scripts/cleanup-videos.php` on a loop (`VIDEO_CLEANUP_INTERVAL_SECONDS`) to enforce retention continuously.
- `live-worker` is behind the optional `live` Docker Compose profile for the future V2 live-scan release.

## Database Commands

- `php scripts/migrate.php migrate` - apply pending migrations
- `php scripts/migrate.php rollback [batches]` - roll back latest migration batch(es)
- `php scripts/migrate.php status` - list applied/pending migrations
- `php scripts/seed.php run [filter]` - run seeders (use `demo` filter for demo seeders)
- `composer migrate` - alias for migration
- `composer seed` - alias for explicit seeding

## Notes

- All business data endpoints are organization-scoped using JWT claims.
- Role checks are enforced per endpoint.
- Detailed role responsibilities and permissions matrix: [docs/ROLE_RESPONSIBILITIES.md](docs/ROLE_RESPONSIBILITIES.md)
- Worker service asks PHP for jobs via internal API (no direct Redis/DB queue access).
- Worker extracts pose metrics only and reports results via internal API (no direct DB writes).
- PHP `AssessmentEngine` is the single scoring authority for both manual and video scans.
- Queue backend is selected by `QUEUE_DRIVER` (`redis` or `db`).
