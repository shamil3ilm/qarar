# Admin Impersonation & Signup Source Tracking

**Date:** 2026-05-02  
**Status:** Approved  
**Scope:** Two independent features — admin impersonation with audit trail, and user signup attribution

---

## Feature 1: Admin Impersonation

### Overview

Admins can temporarily act as another user to troubleshoot issues. Every impersonation session is audited: who impersonated whom, why, when, and what state-changing actions were taken.

### Authorization

- **Super-admins** (`is_super_admin = true`) can always impersonate.
- **Non-super-admins** require the `impersonate_users` permission (added to the existing RBAC permission set).
- **Super-admins cannot be impersonated** by anyone. The endpoint rejects requests targeting a user with `is_super_admin = true`.

### Token Mechanism

1. Admin calls `POST /api/v1/auth/impersonate/{user}` with a required `reason` string.
2. Server validates permission, then issues a new short-lived JWT (1 hour TTL) containing:
   - All standard claims scoped to the **target user** (user_id, organization_id, roles, permissions)
   - Extra claims: `impersonated_by_id`, `impersonation_session_id` (UUID v4), `is_impersonating: true`
3. The admin's own JWT remains valid and unaffected.
4. All API calls made with the impersonation token flow through existing middleware unchanged — the system behaves as if it is the target user.
5. Session ends when:
   - Admin calls `POST /api/v1/auth/impersonate/end`
   - The 1-hour token expires naturally

### Middleware: `TrackImpersonation`

Applied globally after `auth:api`. On every request:

1. Reads `impersonated_by_id` and `impersonation_session_id` from JWT claims.
2. If present, stores them in the request context (`$request->attributes`).
3. After the response, any `ActivityLog` entries created during that request are stamped with `impersonated_by_id` and `impersonation_session_id`.

### Activity Logging

**Two columns added to `activity_logs` table:**

| Column | Type | Description |
|--------|------|-------------|
| `impersonated_by_id` | `bigint unsigned nullable` (FK → users) | The real actor (admin). Null on normal requests. |
| `impersonation_session_id` | `char(36) nullable` | UUID grouping all actions in one session. |

**Actions logged during impersonation** (state-changing only):

| Logged | Skipped |
|--------|---------|
| `created`, `updated`, `deleted`, `restored` | `viewed` |
| `approved`, `rejected`, `submitted` | `login`, `logout` |
| `exported`, `emailed`, `printed`, `archived` | |

The `TrackImpersonation` middleware checks the action type before stamping. Read-only actions (`viewed`) are not stamped and are effectively invisible in the impersonation audit report.

**Session lifecycle entries** (always logged regardless of action type):
- `impersonation_started` — logged when the impersonation token is issued, includes `reason` in `metadata`
- `impersonation_ended` — logged when `POST /auth/impersonate/end` is called explicitly. Token expiry does not generate this entry — the absence of an `impersonation_ended` row means the session expired naturally rather than being closed by the admin.

### API Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `/api/v1/auth/impersonate/{user}` | Start impersonation session |
| `POST` | `/api/v1/auth/impersonate/end` | End impersonation session |
| `GET` | `/api/v1/admin/impersonation-sessions` | List all sessions (super-admin only) |
| `GET` | `/api/v1/admin/impersonation-sessions/{session_id}` | Session detail with action log |

### Request / Response

**Start impersonation:**
```json
POST /api/v1/auth/impersonate/{user}
{
  "reason": "Investigating invoice #1234 display issue reported by user"
}

Response 200:
{
  "token": "<impersonation_jwt>",
  "expires_at": "2026-05-02T15:00:00Z",
  "impersonation_session_id": "uuid-v4",
  "target_user": { "id": 42, "name": "Ahmed Al-Rashid", "email": "ahmed@acme.com" }
}
```

**End impersonation:**
```json
POST /api/v1/auth/impersonate/end
Authorization: Bearer <impersonation_jwt>

Response 200:
{ "message": "Impersonation session ended." }
```

### Validation Rules

- `reason`: required, string, min:10, max:500
- Target user must exist and belong to the same organization (or super-admin spanning orgs)
- Target user must not be a super-admin (`is_super_admin = false`)
- Impersonating admin must have `impersonate_users` permission or `is_super_admin = true`
- An admin cannot impersonate while already in an impersonation session

---

## Feature 2: Signup Source Tracking

### Overview

Capture attribution data at the moment a user registers. Data is write-once — set at registration, never modified. All fields are nullable to remain backwards-compatible with existing registration flows.

### Fields Added to `users` Table

| Column | Type | Example Values |
|--------|------|----------------|
| `registration_source` | `varchar(30) nullable` | `web`, `mobile_ios`, `mobile_android`, `api`, `invitation` |
| `utm_source` | `varchar(100) nullable` | `google`, `linkedin`, `email` |
| `utm_medium` | `varchar(100) nullable` | `cpc`, `organic`, `referral` |
| `utm_campaign` | `varchar(150) nullable` | `gcc-launch-q2` |
| `utm_term` | `varchar(150) nullable` | `erp software uae` |
| `utm_content` | `varchar(150) nullable` | `banner-a` |
| `referral_code` | `varchar(50) nullable` | `PARTNER-XYZ`, `RESELLER-001` |
| `registration_device_type` | `varchar(20) nullable` | `web`, `mobile` |
| `registration_ip` | `varchar(45) nullable` | `203.0.113.42` (supports IPv6) |
| `invited_by_user_id` | `bigint unsigned nullable` (FK → users) | User who sent the invitation |

### Registration Flow

The frontend passes these fields in the `POST /api/v1/auth/register` payload. The `AuthController@register` method stores them alongside the standard user fields. Fields not sent default to `null`.

**Extended register request (all new fields optional):**
```json
{
  "name": "Ahmed Al-Rashid",
  "email": "ahmed@acme.com",
  "password": "...",
  "registration_source": "web",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "gcc-launch-q2",
  "utm_term": "erp software",
  "utm_content": "banner-a",
  "referral_code": "PARTNER-XYZ",
  "registration_device_type": "web"
}
```

`registration_ip` is captured server-side from `$request->ip()` — never trusted from the payload.  
`invited_by_user_id` is passed directly in the payload as a user ID (validated via `exists:users,id`). It is only meaningful when `registration_source = invitation`. External partner/affiliate flows use `referral_code` instead and leave `invited_by_user_id` null.

### Validation Rules

All new fields are `nullable`. When present:
- `registration_source`: `in:web,mobile_ios,mobile_android,api,invitation`
- `registration_device_type`: `in:web,mobile`
- `utm_*`: `string|max:150`
- `referral_code`: `string|max:50`

---

## Database Migrations

### Migration 1: `add_impersonation_columns_to_activity_logs_table`
```
activity_logs
  + impersonated_by_id       bigint unsigned nullable (FK users.id, nullOnDelete)
  + impersonation_session_id char(36) nullable
  + index(impersonation_session_id)
  + index(impersonated_by_id)
```

### Migration 2: `add_signup_tracking_to_users_table`
```
users
  + registration_source        varchar(30) nullable
  + utm_source                 varchar(100) nullable
  + utm_medium                 varchar(100) nullable
  + utm_campaign               varchar(150) nullable
  + utm_term                   varchar(150) nullable
  + utm_content                varchar(150) nullable
  + referral_code              varchar(50) nullable
  + registration_device_type   varchar(20) nullable
  + registration_ip            varchar(45) nullable
  + invited_by_user_id         bigint unsigned nullable (FK users.id, nullOnDelete)
```

---

## Files Affected

| Layer | File | Change |
|-------|------|--------|
| Migration | `database/migrations/..._add_impersonation_columns_to_activity_logs_table.php` | New |
| Migration | `database/migrations/..._add_signup_tracking_to_users_table.php` | New |
| Model | `app/Models/Core/ActivityLog.php` | Add `impersonated_by_id`, `impersonation_session_id` to fillable + `impersonatedBy` relation |
| Model | `app/Models/User.php` | Add signup fields to fillable + `invitedBy` relation |
| Middleware | `app/Http/Middleware/TrackImpersonation.php` | New |
| Service | `app/Services/Auth/ImpersonationService.php` | New — start/end session, generate token |
| Controller | `app/Http/Controllers/Api/V1/Auth/ImpersonationController.php` | New — start, end endpoints |
| Controller | `app/Http/Controllers/Api/V1/Admin/ImpersonationAuditController.php` | New — list/detail endpoints |
| Controller | `app/Http/Controllers/Api/V1/Auth/AuthController.php` | Modify `register()` to store signup fields |
| Routes | `routes/api/v1/auth.php` | Add impersonate start/end routes |
| Routes | `routes/api/v1/admin.php` | Add audit list/detail routes |
| Bootstrap | `bootstrap/app.php` | Register `TrackImpersonation` middleware alias |

---

## Testing

- **Impersonation:** super-admin can impersonate, permission-holder can impersonate, non-super-admin without permission is rejected, super-admin target is rejected, critical actions are stamped, viewed actions are not stamped, session ends on token expiry and on explicit end call
- **Signup tracking:** UTM fields stored on register, `registration_ip` captured server-side, `invited_by_user_id` resolved from referral code, all fields nullable (existing register flow unaffected)
