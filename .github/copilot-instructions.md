# Copilot instructions for WorkEddy

## Architecture you must preserve
- Runtime entrypoint is `public/index.php` with FastRoute dispatch for both HTML and API.
- API routes are mounted under `/api/v1` in `routes/api.php`; web routes are in `routes/web.php`.
- Dependency wiring is explicit in `app/core/Container.php` (no auto-wiring/reflection).
- Layering is `Controller -> Service -> Repository`; keep DB access inside repositories.
- Multi-tenant scope comes from JWT claims (`org`, `sub`, `role`) via `Auth::orgId()` / `Auth::userId()`.
- Role gates are explicit per endpoint using `Auth::requireRoles(...)` in controllers.

## Critical data flows
- Manual scan flow: `ScanController::createManual()` -> `ScanService::createManualScan()` -> `AssessmentEngine` scoring -> repository persist.
- Video scan flow: upload via `VideoProcessingService::storeUploadedFile()` to `/storage/uploads/videos/...`, create processing scan, enqueue `scan_jobs`.
- Worker flow is pull-based: Python worker calls internal API (`/api/v1/internal/worker/*`) with `X-Worker-Token`; worker does metrics extraction only.
- PHP is the scoring authority for video completion: `ScanService::completeVideoScanFromWorker()` always runs `AssessmentEngine` before saving results.
- Queue backend is switchable (`QUEUE_DRIVER=redis|db`) through `app/config/queue.php` and `Container::queue()`.
- Scan list caching uses versioned keys in `ScanService`; call existing invalidation paths when changing scan write operations.

## Coding conventions specific to this repo
- Controller actions typically return `never` and end with `Response::json()`, `Response::created()`, or `Response::error()`.
- Protected API routes usually call `$c->auth()` directly in route closures (see `routes/api.php`) instead of global middleware stacks.
- Use `Validator::requireFields()` for required payload fields and throw domain exceptions in services.
- Keep worker auth checks in `InternalRequestAuth::requireWorkerToken()` for all internal worker endpoints.
- Preserve storage path contract (`/storage/...`) because API and worker share Docker volume `storage_data`.
- If you change scan model/input rules, update `AssessmentEngine` compatibility paths and video worker assumptions together.

## Developer workflows (use these exact commands)
- Start full stack: `docker compose up --build`
- Seed demo/base data: `docker compose run --rm --profile ops seed`
- Migrations: `php scripts/migrate.php migrate|rollback|status` (or `composer migrate`)
- Seeders: `php scripts/seed.php run [filter]` (or `composer seed`)
- PHP tests: `composer test:php`
- Ergonomic fixture tests: `composer test:postures` (supports `--model=rula|reba`)
- Python worker tests: `composer test:py`
- Full test suite: `composer test`

## Integration boundaries to respect
- Do not make Python worker write directly to DB/Redis; keep control-plane interactions via internal API only.
- Keep `WORKER_API_TOKEN` validation on both sides (`app/helpers/InternalRequestAuth.php` and `workers/video-worker/worker.py`).
- Keep queue payload shape stable (`scan_id`, `organization_id`, `video_path`, `model`) unless updating producer and consumer in one change.
- Preserve Redis shared usage (queue, cache, rate limiting) through `RedisConnectionFactory`.
