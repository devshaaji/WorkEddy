# WorkEddy

<<<<<<< codex/break-down-requirements-and-start-project-setup-43uxpf
Production-oriented WorkEddy implementation aligned with `requirements.md`, including Milestones 1-4.

## Delivered milestones

### Milestone 1
- Authentication, organizations, roles

### Milestone 2
- Task management, manual scan engine, dashboard analytics

### Milestone 3
- Video scan upload endpoint + async queue worker processing

### Milestone 4
- Observer validation workflows (`POST /observer-rating`, `GET /observer-rating/{scan_id}`)
- Usage-based billing snapshots and plan catalog (`GET /billing/usage`, `GET /billing/plans`)
- Plan + subscription model persisted in DB (`plans`, `subscriptions`)
- Scan limit enforcement before manual/video scan creation
- Expanded analytics including department risk heatmap and observer alignment summaries

## API endpoints
=======
Production-oriented implementation of WorkEddy aligned to `requirements.md` stack and architecture.

## Stack alignment from requirements

- **Backend language/runtime:** PHP 8.2+
- **Routing:** FastRoute
- **Logging:** Monolog
- **DB layer:** Doctrine DBAL
- **Auth/JWT:** firebase/php-jwt
- **Queue:** Redis
- **Database:** MySQL 8+
- **Frontend:** Bootstrap 5 + Vanilla JS (+ Alpine.js included)
- **Infra:** Docker Compose + Nginx + API + Worker

## Delivered modules

- Authentication and organization onboarding
- User role management (admin/supervisor/worker/observer)
- Task management
- Manual scan engine with normalized risk scoring
- Dashboard analytics
- Observer rating API
- Usage tracking for manual scans

## API
>>>>>>> main

- `POST /auth/signup`
- `POST /auth/login`
- `GET /auth/me`
- `GET /users`, `POST /users`
- `GET /tasks`, `POST /tasks`, `GET /tasks/{id}`
<<<<<<< codex/break-down-requirements-and-start-project-setup-43uxpf
- `POST /scans/manual`
- `POST /scans/video` (multipart form with `task_id` and `video`)
- `GET /scans`, `GET /scans/{id}`
- `GET /dashboard`
- `POST /observer-rating`, `GET /observer-rating/{scan_id}`
- `GET /billing/usage`, `GET /billing/plans`
=======
- `POST /scans/manual`, `GET /scans`, `GET /scans/{id}`
- `GET /dashboard`
- `POST /observer-rating`
>>>>>>> main
- `GET /health`

## Setup

```bash
docker compose up --build
```

```bash
docker compose exec api composer install
docker compose exec api php scripts/migrate.php
```

## Notes

<<<<<<< codex/break-down-requirements-and-start-project-setup-43uxpf
- `signup` automatically creates the organization's active starter subscription.
- Billing checks are enforced on scan creation (manual + video).
- Usage records drive monthly scan usage aggregation.
=======
- All business data endpoints are organization-scoped using JWT claims.
- Role checks are enforced per endpoint.
- Worker service is active for upcoming video pipeline expansion.
>>>>>>> main
