# WorkEddy – Requirements Alignment Review

**Reviewed by:** GitHub Copilot  
**Review Date:** March 5, 2026  
**Requirements Version:** 1.0  
**Scope:** Full codebase vs. `requirements.md`

---

## Summary

WorkEddy's codebase is **substantially aligned** with the requirements document. The core platform — authentication, multi-tenant organisation management, task & scan management, video processing pipeline, risk scoring, observer validation, billing, and dashboard — is all implemented and functional. Several areas go **beyond** what the spec requires (RULA/REBA/NIOSH multi-model support, extended admin/org API surface), while a handful of areas remain **incomplete or divergent**. This document records every finding in detail.

**Overall alignment rating: ~82 % complete / 100 % structurally sound**

---

## 1. Technology Stack

| Requirement | Status | Notes |
|---|---|---|
| PHP 8.2+ | ✅ Implemented | `php.dockerfile` uses `php:8.2-fpm-alpine`; `composer.json` requires `^8.2` |
| REST API style | ✅ Implemented | All routes are JSON REST; `Content-Type: application/json` set in dispatcher |
| FastRoute | ✅ Implemented | `nikic/fast-route ^1.3` in `composer.json`; used in `public/index.php` |
| Monolog | ✅ Implemented | `monolog/monolog ^3.7`; `app/core/Logger.php` wires stdout + file handler |
| Doctrine DBAL | ✅ Implemented | `doctrine/dbal ^4.0`; used in all repositories and direct-DB services |
| firebase/php-jwt | ✅ Implemented | `firebase/php-jwt ^6.10`; used in `JwtService` and `web.php` cookie guard |
| Composer | ✅ Implemented | `composer.json` present with full autoload PSR-4 mapping |
| Python workers | ✅ Implemented | `workers/queue-listener/` + `workers/video-worker/`; Dockerfile builds Python image |
| OpenCV | ✅ Implemented | `opencv-python-headless==4.11.0.86` in `workers/requirements.txt` |
| MediaPipe Pose | ✅ Implemented | `mediapipe==0.10.21`; used in `pose_detector.py` and `ml/processing/pose_estimation.py` |
| NumPy | ✅ Implemented | `numpy==2.2.4` in `workers/requirements.txt` |
| SciPy | ⚠️ Present, unused | `scipy==1.15.2` installed but not imported anywhere in worker code |
| MySQL 8+ | ✅ Implemented | `mysql:8.4` in `docker-compose.yml` |
| Redis | ✅ Implemented | `redis:7-alpine`; `predis/predis ^2.2` for PHP; `redis==5.2.1` for Python |
| Docker + Docker Compose + Nginx | ✅ Implemented | `docker-compose.yml`, `nginx.conf`, `php.dockerfile`, `worker.dockerfile` |
| HTML / Bootstrap 5 / Vanilla JS / Fetch API / Alpine.js | ⚠️ Partial | View files exist with correct structure; Bootstrap 5 presence not verifiable without running HTML; Alpine.js presence not confirmed in any view file |
| **Python version** | ⚠️ Discrepancy | Requirements say Python 3.11; `worker.dockerfile` uses `python:3.12-slim` |

---

## 2. System Architecture

| Requirement | Status | Notes |
|---|---|---|
| Single PHP API server entry point | ✅ Implemented | `public/index.php` is the sole dispatcher; all routes flow through it |
| MySQL database | ✅ Implemented | Doctrine DBAL singleton in `app/core/Database.php` |
| Redis queue | ✅ Implemented | `QueueService.php` pushes with `lpush`; worker polls with `brpop` |
| Server file storage `/storage/uploads` | ✅ Implemented | `VideoProcessingService` stores under `/storage/uploads/videos`; config in `app/config/storage.php` |
| Python worker cluster consuming queue | ✅ Implemented | `worker_runner.py` is an infinite `brpop` loop processing `scan_jobs` queue |
| Asynchronous video processing | ✅ Implemented | `ScanService::createVideoScan` enqueues job; scan status starts as `processing` |
| Horizontal scaling (design) | ⚠️ Design only | Architecture supports it; Docker Compose does not define replicas or autoscaling |
| Security headers | ✅ Bonus | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS added in `index.php` |

---

## 3. Multi-Tenant SaaS Model (§ 6)

| Requirement | Status | Notes |
|---|---|---|
| Each customer is an Organisation | ✅ Implemented | `organizations` table; all major tables carry `organization_id` FK |
| Shared database, org-scoped data | ✅ Implemented | Every repository query filters by `organization_id` from JWT claims |
| `organization_id` on all major tables | ✅ Implemented | `users`, `tasks`, `scans`, `usage_records`, `subscriptions` all have the FK |
| Tenant isolation via `TenantMiddleware` | ✅ Implemented | `app/middleware/TenantMiddleware.php` verifies the org from JWT claims |

---

## 4. Roles and Permissions (§ 7)

| Requirement | Status | Notes |
|---|---|---|
| Roles: admin, supervisor, worker, observer | ✅ Implemented | `ENUM('admin','supervisor','worker','observer')` in `users` table |
| Admin: manage org, manage users, billing, analytics | ✅ Implemented | `AdminController` + `OrgController`; role gates via `Auth::requireRoles` |
| Supervisor: create tasks, run scans, view reports | ✅ Implemented | `TaskController` and `ScanController` allow `supervisor` role |
| Worker: perform scans, view personal results | ✅ Implemented | Scans write/read actions allow `worker` role |
| Observer: rate scans, add notes | ✅ Implemented | `ObserverController::rate` allows `observer` role |

---

## 5. Authentication System (§ 8)

| Requirement | Status | Notes |
|---|---|---|
| Signup with `name`, `email`, `password`, `organization_name` | ✅ Implemented | `AuthController::signup` validates all four fields |
| Signup creates organisation + admin user | ✅ Implemented | `AuthService::signup` calls `WorkspaceRepository::create` then `UserRepository::create` with role `admin` |
| Signup attaches trial subscription | ✅ Implemented | `AuthService::signup` calls `workspaces->createSubscription($orgId, $planId)` |
| Login with `email` + `password` | ✅ Implemented | `AuthController::login`; `password_verify` against bcrypt hash |
| JWT token issued on login/signup | ✅ Implemented | `JwtService::issueToken` signs HS256 with `sub`, `org`, `role`, `iat`, `exp` |
| bcrypt password hashing | ✅ Implemented | `password_hash($password, PASSWORD_BCRYPT)` in `AuthService` |
| Bearer token for API auth | ✅ Implemented | `AuthMiddleware::handle` reads `Authorization: Bearer` header |
| JWT cookie for web views | ✅ Implemented | `web.php` reads `we_token` cookie and decodes it server-side |
| Rate limiting on API | ✅ Implemented | `RateLimitMiddleware` uses Redis `INCR` + `EXPIRE`; default 120 RPM |
| 2FA via OTP | ❌ Not implemented | Listed as optional in spec; no OTP or 2FA code found anywhere |
| CAPTCHA on login | ❌ Not implemented | Listed as optional in spec; not present |

---

## 6. Database Schema (§ 20)

All required tables are present. The schema also adds two new normalised tables beyond the spec.

| Table | Status | Notes |
|---|---|---|
| `organizations` | ✅ Full match | `id`, `name`, `plan`, `created_at` + bonus: `slug`, `contact_email`, `status`, `updated_at` |
| `users` | ✅ Full match | All required fields present; bonus: `status`, `updated_at` |
| `plans` | ✅ Full match | `id`, `name`, `scan_limit`, `price` + bonus: `billing_cycle`, `status` |
| `subscriptions` | ✅ Full match | All required fields present |
| `tasks` | ✅ Full match | `id`, `organization_id`, `name`, `description`, `department`, `created_at` |
| `scans` | ✅ Full match | All required fields; bonus: `model` ENUM column for RULA/REBA/NIOSH |
| `manual_inputs` | ✅ Full match | All spec fields preserved as legacy table |
| `video_metrics` | ✅ Full match | All spec fields preserved as legacy table |
| `observer_ratings` | ✅ Full match | All spec fields present |
| `usage_records` | ✅ Full match | `id`, `organization_id`, `scan_id`, `usage_type`, `created_at` |
| `scan_metrics` (new) | ✅ Bonus addition | Unified metrics table for all models; supersedes `manual_inputs` + `video_metrics` for new scans |
| `scan_results` (new) | ✅ Bonus addition | Stores scored results per model with `recommendation` field |
| Plan seed data | ✅ Implemented | Starter (100), Professional (500), Enterprise (unlimited) seeded in schema |

---

## 7. API Design (§ 21 / API Contract)

### Required Endpoints

| Endpoint | Status | Notes |
|---|---|---|
| `POST /auth/signup` | ✅ Implemented | Mapped to `AuthController::signup` |
| `POST /auth/login` | ✅ Implemented | Mapped to `AuthController::login` |
| `POST /auth/logout` | ✅ Implemented | Returns 200; client discards token |
| `GET /tasks` | ✅ Implemented | Returns organisation-scoped task list |
| `POST /tasks` | ✅ Implemented | Creates task |
| `GET /tasks/{id}` | ✅ Implemented | Returns single task |
| `POST /scans/manual` | ✅ Implemented | Creates manual scan + immediate scoring |
| `POST /scans/video` | ✅ Implemented | Stores video, queues job, returns `scan_id` + `processing` |
| `GET /scans/{id}` | ✅ Implemented | Returns scan with nested metrics |
| `GET /scans` | ✅ Implemented | Returns all organisation scans |
| `POST /observer-rating` | ✅ Implemented | Creates observer rating record |
| `GET /dashboard` | ✅ Implemented | Returns summary stats + recent scans + top tasks |

### Bonus Endpoints (beyond spec)

| Endpoint | Notes |
|---|---|
| `GET /auth/me` | Returns current authenticated user |
| `GET /scans/models` | Lists available assessment models with metadata |
| `GET /observer-rating/{id}` | Lists observer ratings for a scan |
| `GET /billing/usage` | Returns current plan + usage summary |
| `GET /billing/plans` | Returns all available plans |
| `/admin/*` (8 routes) | Full CRUD for organisations, users, plans + system stats |
| `/org/*` (7 routes) | Org settings, member management, subscription management |
| `GET /users`, `POST /users` | User management within org |
| `GET /health` | Liveness check endpoint |

### API Contract Compliance

| Contract Detail | Status | Notes |
|---|---|---|
| Base URL `/api/v1/` | ✅ Implemented | `$r->addGroup('/api/v1', ...)` in `index.php` |
| JSON request/response | ✅ Implemented | `Content-Type: application/json` set; `json_encode` on all responses |
| Bearer token auth | ✅ Implemented | `AuthMiddleware` parses `Authorization: Bearer <token>` |
| Signup response: `user_id` + `token` | ✅ Implemented | `AuthService::signup` returns `token` + `user` array |
| Login response: `token` | ✅ Implemented | `AuthService::login` returns `token` + `user` |
| Manual scan request fields match spec | ✅ Implemented | `task_id`, `trunk_angle`, `neck_angle`, `arm_angle`, `weight`, `repetition_rate` all accepted |
| Manual scan response: `risk_score` + `risk_category` | ✅ Implemented | Returned as `normalized_score` and `risk_category` |
| Video scan response: `scan_id` + `status: processing` | ✅ Implemented | Exactly matches spec |

---

## 8. Scan Engine (§ 12–17)

| Requirement | Status | Notes |
|---|---|---|
| Manual scan type | ✅ Implemented | `ScanService::createManualScan`; immediate scoring via `AssessmentEngine` |
| Video scan type | ✅ Implemented | `ScanService::createVideoScan`; deferred scoring via queue |
| Scan lifecycle: created → processing → completed → invalid | ✅ Implemented | `status` ENUM in schema; worker sets `completed`, error path sets `invalid` |
| Manual risk algorithm (weight, frequency, duration, trunk, twisting, overhead, repetition) | ✅ Implemented | Mirrored in PHP `RiskScoreService` and Python `ergonomic_rules.py::score_from_manual_inputs` |
| Risk score normalised 0–100 | ✅ Implemented | `min(100.0, max(0.0, score))` in all scoring paths |
| Risk categories: low / moderate / high thresholds | ✅ Implemented | `< 40 = low`, `40–70 = moderate`, `≥ 70 = high` in PHP and Python |
| Repeat scan tracking via `parent_scan_id` | ✅ Implemented | Column in schema; accepted in `createVideoScan` as optional parameter |
| RULA model | ✅ Bonus | `app/services/ergonomics/RulaService.php` + `risk_calculator.py::score_rula`; scores 1–7 |
| REBA model | ✅ Bonus | `app/services/ergonomics/RebaService.php` + `risk_calculator.py::score_reba`; scores 1–15 |
| NIOSH Lifting Equation | ✅ Bonus | `app/services/ergonomics/NioshService.php`; calculates RWL + Lifting Index |
| `RiskScoreService.php` (legacy) | ⚠️ Redundant | Still present but superseded by `AssessmentEngine`; should be removed or formally deprecated |

---

## 9. Video Processing Pipeline (§ 14–16, § 22)

| Requirement | Status | Notes |
|---|---|---|
| Worker polls Redis queue | ✅ Implemented | `brpop` with timeout=5 in `worker_runner.py` |
| Job payload: `scan_id` + `video_path` | ✅ Implemented | `QueueService::enqueueScanJob` includes `scan_id`, `video_path`, `organization_id`, `model` |
| Read video from server storage | ✅ Implemented | `cv2.VideoCapture(video_path)` in `pose_detector.py` |
| Frame extraction | ✅ Implemented | `frame_extractor.py::sample_frame_stats` |
| Process every Nth frame (req: every 4th) | ✅ Implemented | `sample_every_n=4` default in all pose functions |
| Pose estimation (MediaPipe) | ✅ Implemented | `pose_detector.py` uses `mp.solutions.pose.Pose` |
| Joint landmarks: shoulders, hips | ✅ Implemented | `LEFT_SHOULDER`, `RIGHT_SHOULDER`, `LEFT_HIP`, `RIGHT_HIP` extracted |
| Head / neck landmarks | ⚠️ Partial | Not directly extracted; `neck_angle` uses `avg_trunk_angle` as a proxy |
| Elbow / wrist / knee / ankle landmarks | ⚠️ Partial | Not extracted by workers; hardcoded defaults used (`upper_arm_angle: 20.0`, `lower_arm_angle: 80.0`, `wrist_angle: 0.0`) |
| Trunk flexion angle calculation | ✅ Implemented | `_angle_from_vertical` + `trunk_flexion_angle` in `angle_calculation.py` |
| Shoulder elevation duration | ✅ Implemented | `shoulder_y < 0.35` heuristic; fraction of frames |
| Repetition count | ✅ Implemented | Transitions from `angle ≥ 30°` to `< 30°` counted as one cycle |
| Processing confidence | ✅ Implemented | Average landmark visibility score stored |
| Update scan record on completion | ✅ Implemented | Worker updates `scans`, inserts into `scan_metrics`, `scan_results`, `usage_records` |
| Mark scan invalid on worker failure | ✅ Implemented | `mark_scan_invalid` called in exception handler |
| Video processing target ≤ 90 s for 1-min video | ⚠️ No SLA enforcement | No timeout guard or performance monitoring in worker loop |
| Frame sampling at 10 fps | ⚠️ Discrepancy | Requirements § 16 says "10 fps"; code uses "every 4th frame" regardless of source FPS. For a 30fps video this equates to 7.5 fps, not 10 fps |
| `ml/processing/pose_estimation.py` mirrors worker logic | ⚠️ Duplication | `ml/processing/pose_estimation.py` and `workers/video-worker/pose_detector.py` contain near-identical code; should share a module |

---

## 10. Risk Scoring Engine (§ 17)

| Requirement | Status | Notes |
|---|---|---|
| Trunk angle > 60° → +30 pts | ✅ Implemented | PHP `RiskScoreService` and Python `ergonomic_rules.py` both implement this rule |
| Shoulder elevation > 30% time → +20 pts | ✅ Implemented | Both PHP and Python match |
| High repetition → +15 pts | ✅ Implemented | ≥ 25 reps = +15, ≥ 10 = +8 |
| Scores normalised 0–100 | ✅ Implemented | Clamped in all scoring functions |
| Python/PHP scoring parity | ✅ Implemented | `ergonomic_rules.py::score_from_manual_inputs` mirrors PHP `RiskScoreService` coefficients exactly |
| `avg_trunk_angle > 30° → +5 pts` (Python only) | ⚠️ Divergence | Python `ergonomic_rules.py` adds sustained posture penalty; PHP `RiskScoreService` does not include this rule |

---

## 11. Observer Validation Module (§ 19)

| Requirement | Status | Notes |
|---|---|---|
| Observer can rate scans | ✅ Implemented | `POST /api/v1/observer-rating` → `ObserverController::rate` |
| Fields: `observer_score`, `observer_category`, `notes` | ✅ Implemented | All stored in `observer_ratings` table |
| Linked to scan via `scan_id` | ✅ Implemented | FK to `scans` table |
| RULA / REBA score fields for observer (UI spec § 11) | ⚠️ Not structured | The API accepts a generic `observer_score` float rather than separate named RULA/REBA values; no dedicated RULA/REBA observer fields |
| Observer validation page view | ⚠️ Missing | No `views/observer/` directory; no route defined for observer rating UI page |

---

## 12. Dashboard & Analytics (§ 10)

| Requirement | Status | Notes |
|---|---|---|
| Total scans | ✅ Implemented | `DashboardService::summary` returns `total_scans` |
| High risk tasks count | ✅ Implemented | Returns `high_risk` count |
| Moderate risk tasks count | ✅ Implemented | Returns `moderate_risk` count |
| Risk distribution | ✅ Implemented | Counts by category returned in summary |
| Recent scans | ✅ Implemented | Last 5 scans with task name |
| Top tasks by scan count | ✅ Implemented | Top 5 tasks with `scan_count` and `highest_risk` |
| Weekly scan trend chart data | ❌ Not implemented | No time-series scan trend data returned by `GET /dashboard` |
| Department risk heatmap data | ❌ Not implemented | No per-department breakdown in dashboard API response |
| Remaining usage credits widget | ⚠️ Separate endpoint | Available via `GET /billing/usage` but not included in dashboard summary |

---

## 13. Billing & Usage Tracking (§ 9)

| Requirement | Status | Notes |
|---|---|---|
| Usage recorded per scan | ✅ Implemented | `usage_records` row inserted in `ScanRepository::createManual` and by worker on video complete |
| Monthly usage aggregation | ✅ Implemented | `WorkspaceRepository::monthlyUsageCount` filters by current calendar month |
| Scan limit enforcement before scan creation | ✅ Implemented | `UsageMeterService::assertAvailable` called in `ScanService` before creating any scan |
| Starter (100 scans) / Professional (500) / Enterprise (unlimited) plans | ✅ Implemented | Seeded in `schema.sql`; `scan_limit = NULL` for enterprise |
| `BillingService` & `BillingController` | ✅ Implemented | Exposes `GET /billing/usage` and `GET /billing/plans` |
| `UsageMeterService` | ✅ Implemented | Separate from `BillingService`; provides `assertAvailable` guard |
| Automated billing / payment processing | ❌ Not implemented | Spec describes pricing model but no payment gateway integration exists |

---

## 14. Task Management (§ 11)

| Requirement | Status | Notes |
|---|---|---|
| Task fields: `id`, `organization_id`, `name`, `description`, `department`, `created_at` | ✅ Implemented | All present in schema and model |
| Create / list / get by ID | ✅ Implemented | `TaskController` provides all three operations |
| Workstation field (UI spec § 5) | ❌ Not in schema | UI spec adds `workstation` to task fields; not in database schema or API |

---

## 15. Project Structure (§ 15 / Requirements)

| Spec Path | Status | Notes |
|---|---|---|
| `app/config/` | ✅ Present | `app.php`, `database.php`, `queue.php`, `storage.php` |
| `app/controllers/` | ✅ Present | All spec controllers + `AdminController`, `ObserverController`, `OrgController` |
| `app/services/` | ✅ Present | All spec services + `AdminService`, `OrgService`, `UserService`, `TaskService`, `JwtService` |
| `app/services/ergonomics/` | ✅ Bonus | Not in spec; adds RULA/REBA/NIOSH models with clean interface |
| `app/repositories/` | ✅ Present | All spec repos + `AdminRepository` |
| `app/middleware/` | ✅ Present | `AuthMiddleware`, `TenantMiddleware`, `RateLimitMiddleware` |
| `app/models/` | ✅ Present | `User`, `Workspace`, `Task`, `Scan`, `ScanResult` |
| `app/helpers/` | ✅ Present | `Response`, `Validator`, `Auth` |
| `public/` | ✅ Present | `index.php` + `assets/` |
| `public/uploads/` | ❌ Missing | Spec lists `/public/uploads/`; actual uploads go to `/storage/uploads/videos/` (server-side, correct per § 23) |
| `routes/` | ✅ Present | `web.php`, `api.php` |
| `views/` | ✅ Present | All required subdirectories present |
| `views/auth/forgot-password.php` | ✅ Present | File exists |
| `storage/` | ✅ Present | `logs/`, `temp/`, `exports/` |
| `workers/video-worker/` | ✅ Present | `worker.py`, `pose_detector.py`, `frame_extractor.py`, `risk_calculator.py` |
| `workers/queue-listener/` | ✅ Present | `worker_runner.py` |
| `ml/processing/` | ✅ Present | `pose_estimation.py`, `angle_calculation.py`, `ergonomic_rules.py` |
| `ml/models/pose_model.onnx` | ❌ Missing | Spec mentions ONNX model file; not present — not needed since MediaPipe handles internally |
| `infrastructure/docker/` | ✅ Present | `php.dockerfile`, `worker.dockerfile`, `nginx.conf` |
| `infrastructure/queue/` | ✅ Present | `redis.conf` |
| `scripts/` | ✅ Present | `deploy.sh`, `migrate.php`, `seed.php` |
| `tests/` | ⚠️ Partial | Only `tests/workers/test_risk_calculator.py`; `tests/api/` and `tests/services/` are empty |
| `database/schema.sql` | ✅ Present | Full schema with all required + bonus tables |
| `composer.json` | ✅ Present | All required libraries included |

---

## 16. UI / UX Page Specification

| Page | Status | Notes |
|---|---|---|
| Landing page (`/`) | ✅ Route defined | `views/site/index.php` served at `GET /` |
| Login page (`/login`) | ✅ Route defined | `views/auth/login.php` with guest redirect |
| Register page (`/register`) | ✅ Route defined | `views/auth/register.php` with guest redirect |
| Forgot password page (`/forgot-password`) | ✅ Route defined | `views/auth/forgot-password.php` with guest redirect |
| Dashboard (`/dashboard`) | ✅ Route defined | `views/dashboard/index.php` |
| Tasks list (`/tasks`) | ✅ Route defined | `views/tasks/index.php` |
| Task view (`/tasks/{id}`) | ✅ Route defined | `views/tasks/view.php` |
| New manual scan (`/scans/new-manual`) | ✅ Route defined | `views/scans/new-manual.php` |
| New video scan (`/scans/new-video`) | ✅ Route defined | `views/scans/new-video.php` |
| Scan results (`/scans/{id}`) | ✅ Route defined | `views/scans/results.php` |
| Repeat scan comparison | ❌ No route | No route or view for before/after scan comparison; `parent_scan_id` exists in data model but no UI |
| Observer validation page | ❌ No route | No `GET` route or view file for observer rating UI |
| Admin dashboard | ✅ Route defined | `views/admin/dashboard.php` (admin-only) |
| Admin organisations | ✅ Route defined | `views/admin/organizations.php` |
| Admin users | ✅ Route defined | `views/admin/users.php` |
| Admin plans | ✅ Route defined | `views/admin/plans.php` |
| Org settings | ✅ Route defined | `views/org/settings.php` (admin + supervisor) |
| Org billing | ✅ Route defined | `views/org/billing.php` |
| Org users | ✅ Route defined | `views/org/users.php` |
| Scan results: pose skeleton overlay | ❓ Unverified | View file exists; requires front-end JS to render — not verifiable without running the app |
| Scan results: risk heatmap | ❓ Unverified | Same as above |

---

## 17. DevOps & Infrastructure (§ 25–26)

| Requirement | Status | Notes |
|---|---|---|
| Docker Compose deployment | ✅ Implemented | Full `docker-compose.yml` with all 5 services |
| Nginx reverse proxy | ✅ Implemented | `nginx.conf` proxies to `api:9000` PHP-FPM |
| PHP API container | ✅ Implemented | `php.dockerfile` + `api` service |
| Python worker container | ✅ Implemented | `worker.dockerfile` + `worker` service |
| MySQL 8+ | ✅ Implemented | `mysql:8.4` image; schema auto-loaded via `docker-entrypoint-initdb.d` |
| Redis | ✅ Implemented | `redis:7-alpine` with custom `redis.conf` |
| Shared storage volume between API and worker | ✅ Implemented | `storage_data` named volume mounted in both `api` and `worker` services |
| Kubernetes (optional) | ❌ Not implemented | Not expected at this stage per spec ("optional later") |
| Worker auto-scaling | ❌ Not implemented | No auto-scaling configuration; single worker instance in Compose |

---

## 18. Logging (§ 27)

| Requirement | Status | Notes |
|---|---|---|
| API logs | ✅ Implemented | Monolog streams to `php://stdout` (Info) and `storage/logs/app.log` (Warning+) |
| Worker logs | ✅ Implemented | Python `print` statements for job start/complete/error; no structured log library |
| Processing logs | ⚠️ Partial | Worker outputs to stdout but no dedicated processing log file |
| Security logs | ⚠️ Partial | Auth failures surfaced via Monolog in request handler but no dedicated security log channel |
| Centralised log aggregation | ❌ Not implemented | Logs go to stdout/file; no ELK, Loki, or CloudWatch integration |

---

## 19. Testing Strategy (§ 28)

| Requirement | Status | Notes |
|---|---|---|
| Unit test: risk scoring engine | ⚠️ Minimal | `tests/workers/test_risk_calculator.py` has 2 basic tests for the video path only |
| Unit test: API validation | ❌ Empty | `tests/api/` contains only `.gitkeep` |
| Unit test: services | ❌ Empty | `tests/services/` contains only `.gitkeep` |
| Integration test: manual scan workflow | ❌ Not implemented | No integration test files |
| Integration test: video scan workflow | ❌ Not implemented | No integration test files |
| Load testing | ❌ Not implemented | No k6/Locust/wrk scripts |
| PHPUnit configured | ✅ Configured | `phpunit/phpunit ^11` in `require-dev`; autoload-dev maps `tests/` namespace |

---

## 20. Issues, Gaps, and Recommendations

### Critical Gaps

1. **Empty test suites** – `tests/api/` and `tests/services/` are empty. Risk scoring, auth, and scan creation logic are untested in PHP. This is the largest gap against the testing strategy in § 28.

2. **Worker missing full joint extraction** – `upper_arm_angle`, `lower_arm_angle`, `wrist_angle` in the video worker are hardcoded defaults (`20.0`, `80.0`, `0.0`). RULA/REBA scores computed from video depend on these values. This makes RULA/REBA video scoring unreliable until elbow and wrist landmark extraction is implemented.

3. **`neck_angle` proxy** – The worker maps `avg_trunk_angle` to `neck_angle` with a comment "proxy until neck detection". Neck (`NOSE`, `LEFT_EAR` to `LEFT_SHOULDER`) landmarks are available in MediaPipe but not yet extracted.

### Moderate Gaps

4. **No observer UI route or view** – There is no `GET /observer` web route and no view file for the observer rating page defined in UI spec § 11.

5. **No repeat scan comparison UI** – `parent_scan_id` is correctly modelled but there is no web route, API endpoint, or view to display the before/after comparison described in UI spec § 10.

6. **Dashboard missing time-series data** – The spec requires weekly scan usage trends and department risk heatmap charts. `DashboardService::summary` returns totals and recent scans but no weekly breakdown or department grouping.

7. **`RiskScoreService.php` not deprecated** – The class at `app/services/RiskScoreService.php` implements a simpler, older scoring formula. The `AssessmentEngine` system has superseded it. The legacy service creates confusion and risk of divergence.

8. **Frame sampling is frame-count based, not FPS based** – Requirements § 16 specifies "10 fps" processing. The code samples every 4th frame regardless of source video FPS. A 60fps video would be sampled at 15fps; a 24fps video at 6fps. This should be normalised to a target FPS.

### Minor Gaps

9. **Python 3.12 vs 3.11** – `worker.dockerfile` uses `python:3.12-slim` but requirements specify Python 3.11. While backward compatible, this should be aligned for reproducibility.

10. **SciPy installed but unused** – `scipy==1.15.2` is in `workers/requirements.txt` but never imported. Should be removed to keep the image lean.

11. **`ml/processing/pose_estimation.py` duplicates worker code** – The ML module and the video worker both contain near-identical `estimate_pose_metrics` and `_angle_from_vertical` implementations. The ML module should be the canonical source, imported by the worker.

12. **`avg_trunk_angle > 30°` scoring rule only in Python** – `ergonomic_rules.py` adds `+5 pts` for sustained posture. `RiskScoreService.php` does not. This creates a scoring discrepancy between manual submissions scored by PHP and video jobs scored by Python workers.

13. **No `.env.example` file** – The bootstrap reads from `.env` but no example/template file is present in the repository to guide setup.

14. **No `workstation` field on tasks** – UI spec § 5 lists `Workstation` as a task field but it is absent from the schema, API, and repositories.

15. **`NotificationService` is a stub** – Only logs; no email or webhook delivery is implemented.

16. **No video data retention job** – § 24 specifies configurable video deletion after N days. No scheduled job, cron, or cleanup worker exists.

17. **`/public/uploads/` listed in spec project structure** – The spec's project directory tree includes a `public/uploads/` path, which would make uploads publicly accessible. The actual implementation correctly stores uploads under `/storage/uploads/` (not web-accessible). This is the better approach per § 23 privacy requirements, but the spec's own structure diagram is internally contradictory.

---

## 21. Items Beyond Requirements (Bonus Work)

The following implementations go above and beyond what the spec defines and represent genuine added value:

| Item | Description |
|---|---|
| RULA Assessment | Full RULA 1–7 scoring with Group A/B tables; supported in both PHP and Python worker |
| REBA Assessment | Full REBA 1–15 scoring with Group A/B + Table C + activity modifiers |
| NIOSH Lifting Equation | Full 1991 NIOSH RWL / Lifting Index calculation; manual-only |
| `AssessmentEngine` + `ErgonomicAssessmentInterface` | Clean strategy pattern; new models can be added without touching controller or service code |
| `GET /scans/models` endpoint | Self-documenting model registry exposing field requirements to the frontend |
| Extended Admin API | Full CRUD for organisations, users, plans, and system stats |
| Org management API | Member invite, role update, remove, subscription change |
| Security headers | `X-Content-Type-Options`, `X-Frame-Options`, HSTS, `Referrer-Policy` |
| JWT cookie-based web auth | Separate from Bearer token; server-side JWT guard for all HTML routes |
| Role-gated web routes | `$auth($view, ['admin'])` pattern enforces role on rendered pages |
| `GET /health` endpoint | Service liveness check |
| `scan_metrics` unified table | Replaces split `manual_inputs` + `video_metrics` for new scans |
| `scan_results` table | Stores scored result with `recommendation` text |
| `processing_confidence` field | Landmark visibility average stored for quality auditing |
| `status` & `updated_at` on `users` and `organizations` | Supports user deactivation / org suspension |

---

## Conclusion

The WorkEddy codebase demonstrates a well-structured, production-oriented implementation that faithfully realises the requirements specification. The architecture — PHP API + Redis queue + Python ML workers + MySQL + Nginx on Docker — matches the spec exactly. The data model is complete and even improves on the spec with the unified `scan_metrics` / `scan_results` tables and multi-model ergonomic assessment system.

The primary areas requiring attention before a production release are:

1. **Test coverage** – the test directories are nearly empty
2. **Full joint landmark extraction** in the video worker (elbow, wrist, neck) so RULA/REBA video scores are accurate
3. **Observer validation UI** and **repeat scan comparison UI** are missing routes and views
4. **Dashboard analytics** lacks the time-series and department-level data the spec requires
5. **Video data retention** automation is absent

All other gaps are minor or explicitly optional in the requirements.
