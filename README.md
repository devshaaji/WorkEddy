# WorkEddy

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

- `POST /auth/signup`
- `POST /auth/login`
- `GET /auth/me`
- `GET /users`, `POST /users`
- `GET /tasks`, `POST /tasks`, `GET /tasks/{id}`
- `POST /scans/manual`
- `POST /scans/video` (multipart form with `task_id` and `video`)
- `GET /scans`, `GET /scans/{id}`
- `GET /dashboard`
- `POST /observer-rating`, `GET /observer-rating/{scan_id}`
- `GET /billing/usage`, `GET /billing/plans`
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

- `signup` automatically creates the organization's active starter subscription.
- Billing checks are enforced on scan creation (manual + video).
- Usage records drive monthly scan usage aggregation.
