# WorkEddy Role Responsibilities

This document describes **effective permissions** in the current system, based on route and controller role gates in the codebase.

---

## 1) Roles in the system

### `super_admin` (platform-level)
- Platform-wide authority across all organizations.
- Can access system administration pages and API.
- Also acts as an override in role checks (`Auth::requireRoles`) when an endpoint allows any role set.

### `admin` (organization-level owner)
- Full org operational control (users, billing actions, org settings, scans, analytics).
- Can perform all day-to-day tenant operations.
- Cannot access global platform admin endpoints unless role is `super_admin`.

### `supervisor` (organization manager)
- Team/task operational role.
- Can create tasks, run scans, review analytics, manage some org-level views.
- Cannot perform admin-only tenant actions (for example invite/remove members, billing charges) unless explicitly allowed.

### `worker` (execution role)
- Performs scans and submits worker-oriented data.
- Can view dashboard/task/scan data and personal coaching.
- Cannot access organization management or observer rating workflows.

### `observer` (assessment/review role)
- Reviews completed scans and submits observer ratings.
- Can access observer workflows, dashboard, tasks, results, and auditor copilot persona.
- Cannot create manual/video scans.

### Internal system actor: Worker service (`X-Worker-Token`)
- Not a human JWT role.
- Used by internal worker endpoints only (`/api/v1/internal/...`).
- Authenticated by shared token via `InternalRequestAuth::requireWorkerToken()`.

---

## 2) Global access model

- **Authentication source:** JWT claims (`org`, `sub`, `role`).
- **Tenant isolation:** org scoping derived from claim (`Auth::orgId()`).
- **Role enforcement:** explicit `Auth::requireRoles(...)` in controllers.
- **Platform override:** `super_admin` passes role checks in `Auth::requireRoles(...)`.

---

## 3) Web page responsibilities (server-rendered routes)

## Public/guest
- `/` public.
- `/login`, `/register`, `/forgot-password` guest only.

## Shared authenticated pages
- `admin`, `supervisor`, `worker`, `observer`:
  - `/dashboard`
  - `/tasks`
  - `/tasks/{id}`
  - `/scans/{id}`
  - `/leading-indicators/check-in`
- Any authenticated user:
  - `/profile`

## Scan creation and comparison pages
- `admin`, `supervisor`, `worker`:
  - `/scans/new-manual`
  - `/scans/new-video`
  - `/scans/compare`
  - `/scans/{id}/compare`
- `observer` is intentionally excluded from scan-creation pages.

## Observer pages
- `admin`, `observer`:
  - `/observer-rating`
  - `/scans/{id}/observe`

## Copilot page
- `admin`, `supervisor`, `observer`:
  - `/copilot`

## Organization pages
- `admin`, `supervisor`:
  - `/org/users`
  - `/org/settings`
  - `/org/billing`

## Platform admin pages
- `super_admin` only:
  - `/admin/dashboard`
  - `/admin/organizations`
  - `/admin/users`
  - `/admin/plans`
  - `/admin/settings`

## Live capture page (feature-flagged)
- Only when live feature is enabled.
- `admin`, `supervisor`, `worker`:
  - `/scans/live-capture`

---

## 4) API responsibilities by domain

## Auth/Profile
- Auth login/signup/refresh routes are open as designed.
- Authenticated profile read/update available to any logged-in user.

## Tasks
- List/show tasks: `admin`, `supervisor`, `worker`, `observer`.
- Create tasks: `admin`, `supervisor`.

## Scans
- List/show/compare scans: `admin`, `supervisor`, `worker`, `observer`.
- Create manual/video scan: `admin`, `supervisor`, `worker`.
- `observer` cannot create scans.

## Observer ratings
- Submit observer rating: `observer`, `admin`.
- List ratings by scan: `admin`, `supervisor`, `observer`.

## Dashboard
- Dashboard API (`/dashboard`): `admin`, `supervisor`, `worker`, `observer`.
- Payload is role-aware:
  - org mode for `admin`/`supervisor`
  - worker mode for `worker`
  - observer mode for `observer`

## Control actions
- List/show: `admin`, `supervisor`, `worker`, `observer`.
- Create/update/verify: `admin`, `supervisor`.
- Worker-specific data boundary: workers can only view actions assigned to themselves.

## Leading indicators + coaching
- Submit and mine leading indicators: `admin`, `supervisor`, `worker`.
- Org summary of leading indicators: `admin`, `supervisor`.
- Worker coaching endpoint: `admin`, `supervisor`, `worker`.

## Copilot personas
- Personas `supervisor`, `safety_manager`, `engineer`: `admin`, `supervisor`.
- Persona `auditor`: `admin`, `supervisor`, `observer`.

## Notifications
- List/unread/mark read/read-all: any authenticated user.
- Broadcast send: `super_admin` only.

## Billing and subscription
- Billing usage + charge invoice: `admin` only.
- Billing plans + invoice list: `admin`, `supervisor`.
- Org subscription read: `admin`, `supervisor`.
- Change plan: `admin` only.

## Organization management
- Org settings read: any authenticated user (used by UI/theme bootstrap).
- Org settings update: `admin`.
- Members list: `admin`, `supervisor`.
- Invite/update role/remove member: `admin`.

## Workspace users endpoint
- List/create users (`/users`): `admin`.

## Platform administration (`/admin/*`)
- All endpoints in this namespace: `super_admin` only.

## Live session API (feature-flagged)
- Exposed only when live feature is enabled.
- Start/stop/ingest frame batch: `admin`, `supervisor`, `observer`.
- List/show/frames/stream: authenticated users in tenant scope.

---

## 5) Responsibility matrix (human roles)

| Capability | super_admin | admin | supervisor | worker | observer |
|---|---:|---:|---:|---:|---:|
| View dashboard | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create tasks | ✅ | ✅ | ✅ | ❌ | ❌ |
| Create manual/video scans | ✅ | ✅ | ✅ | ✅ | ❌ |
| View scans/results | ✅ | ✅ | ✅ | ✅ | ✅ |
| Compare scans | ✅ | ✅ | ✅ | ✅ | ❌ |
| Submit observer ratings | ✅ | ✅ | ❌ | ❌ | ✅ |
| Submit leading indicators | ✅ | ✅ | ✅ | ✅ | ❌ |
| View leading indicator org summary | ✅ | ✅ | ✅ | ❌ | ❌ |
| View worker coaching | ✅ | ✅ | ✅ | ✅ | ❌ |
| Manage org members | ✅ | ✅ | list-only | ❌ | ❌ |
| Update org settings | ✅ | ✅ | ❌ | ❌ | ❌ |
| Billing charge actions | ✅ | ✅ | ❌ | ❌ | ❌ |
| System admin (`/admin/*`) | ✅ | ❌ | ❌ | ❌ | ❌ |

Notes:
- `super_admin` can pass all `Auth::requireRoles(...)` checks due platform override behavior.
- Additional resource-level checks still apply (for example worker-assigned control-action visibility).

---

## 6) Explicit non-human/internal responsibilities

## Video worker + live worker
- Poll internal queues and post completion/failure through internal API.
- No direct DB writes from Python workers.
- Must provide valid `X-Worker-Token`.

## API scoring authority
- Final ergonomic scoring remains on PHP service layer (authoritative scoring path).

---

## 7) Source-of-truth references

- `routes/web.php` (page routing + role-scoped views)
- `routes/api.php` (API surface)
- `app/helpers/Auth.php` (role enforcement + override semantics)
- `app/helpers/InternalRequestAuth.php` (worker token auth)
- Controllers with role gates under `app/controllers/`

---

## 8) Practical interpretation for operations

- If a user should **run scans**, they must be `worker`, `supervisor`, or `admin`.
- If a user should **rate scans only**, use `observer`.
- If a user should **manage tenant settings/users/billing**, use `admin`.
- Reserve `super_admin` for platform operations only.
