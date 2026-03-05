# WorkEddy Requirements Breakdown

## 1) Infrastructure

### Runtime and Services
- **API Service:** PHP 8.2+ REST API (modular plain PHP).
- **Worker Service:** Python video processing workers.
- **Data Layer:** MySQL 8+ with tenant-scoped tables.
- **Queue:** Redis for asynchronous video scan jobs.
- **Reverse Proxy:** Nginx (container-ready).
- **Storage:** Server filesystem (`/storage/uploads/...`) for videos, frames, processed artifacts, reports.

### Deployment and Operations
- Docker / Docker Compose for local orchestration.
- Horizontally scalable API replicas.
- Independently scalable worker replicas.
- Centralized logging recommendation (API, worker, processing, security logs).
- Privacy-first handling for video files (non-public paths + authenticated retrieval).

## 2) Design (System + Domain)

### Core Product Design
- Multi-tenant SaaS with shared DB and strict `organization_id` scoping.
- Two independent risk pipelines:
  1. Manual ergonomic input
  2. Video posture analysis
- Unified normalized risk score (`0-100`) across both pipelines.

### Domain Modules
1. Authentication
2. Organization Management
3. User Management
4. Task Management
5. Scan Engine
6. Video Processing Pipeline
7. Risk Scoring Engine
8. Observer Rating Module
9. Dashboard & Analytics
10. Billing & Usage Tracking

### Data Design Highlights
- Entities: organizations, users, plans, subscriptions, tasks, scans, manual_inputs, video_metrics, observer_ratings, usage_records.
- Scan lifecycle: Created -> Processing (video) -> Completed or Invalid.
- Repeat scans supported through `parent_scan_id`.

## 3) UI / UX

### Client Surfaces
- Web app using Bootstrap 5 + Vanilla JS + Fetch + Alpine.js.
- Mobile browser compatibility.

### MVP Screens
- Login / Signup
- Dashboard (scan totals, risk categories, trends)
- Task listing and task creation
- Manual scan form
- Video scan upload/status tracking
- Scan detail view
- Observer rating form

### UX Requirements
- Clear risk category presentation (low/moderate/high).
- Async video processing status with feedback.
- Role-aware navigation (Admin, Supervisor, Worker, Observer).

## 4) Others (Business, Security, Process)

### Security and Access
- JWT token auth.
- bcrypt password hashing.
- Optional OTP-based 2FA.
- Role-based permissions.

### Billing and Usage
- Usage-based pricing via `usage_records`.
- Monthly aggregation per organization.
- Scan limits by plan (Starter/Professional/Enterprise).

### Testing and Delivery
- Unit tests: scoring + validation.
- Integration tests: manual/video workflows.
- Load tests: queue and concurrent scans.
- Branch strategy: `main`, `staging`, `develop`; PR reviews required.

## 5) Initial Project Setup Scope

This repository now starts with:
- Baseline monorepo structure: `/api`, `/workers`, `/frontend`, `/docs`, `/storage`.
- Initial API health endpoint scaffold.
- Initial worker queue-processing scaffold.
- Frontend Bootstrap starter page.
- Compose-based local infrastructure bootstrap.

Next suggested implementation steps:
1. Implement Auth + Organization onboarding.
2. Add tenant middleware and RBAC policies.
3. Build Task and Manual Scan APIs.
4. Add Redis job producer + worker consumer contract.
5. Add dashboard aggregation queries.


## Milestone 3 Delivery Notes
- Added video upload endpoint and async queueing (`POST /scans/video`).
- Added worker-side sampled-frame processing (every 4th frame) and metric persistence in `video_metrics`.
- Added status transition path for scans (`processing` -> `completed`/`invalid`) with final risk scoring update.


## Milestone 4 Delivery Notes
- Added observer rating creation and retrieval workflows for validation studies.
- Added pricing data model (`plans`, `subscriptions`) with seeded Starter/Professional/Enterprise plans.
- Added billing usage APIs for monthly usage and remaining scan limits.
- Added plan limit enforcement in scan creation flows.
- Expanded dashboard analytics with department risk heatmap and observer alignment categories.
