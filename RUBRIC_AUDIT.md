# Notezy Rubric Audit – 31 Criteria

**Audit date:** 2026-09-11
**Evidence:** source inspection and static tests. No claim below means a live SMTP,
MySQL, LLM-provider or Docker integration test was run.

## Result at a glance

- **Implemented with evidence:** account profile/preferences, note list/grid/CRUD,
  labels, pinning, sharing UI/permissions, PIN, offline queue, reminders and
  AI integration surfaces.
- **Partial or unverified:** registration/activation end-to-end email,
  file attachment breadth, collaboration realtime, AI provider integration,
  full Docker runtime.
- **Approved product equivalence:** owner-managed six-digit PIN is the sole
  per-note lock. For this project, it replaces the rubric's text-password
  wording: it is hashed, rate-limited, never returned to the client, and is
  tied to the note, recipient account and browser session.

## Criteria mapping

| # | Criterion | Status | Evidence / gap |
|---:|---|---|---|
| 1 | Registration | Partial | bcrypt exists; form/data requirements and mail flow need browser+SMTP test. |
| 2 | Activation | Partial | hashed OTP, expiry and attempts exist; live email not verified. |
| 3 | Login/logout | Implemented | session login, logout and fixation mitigation exist. |
| 4 | Password reset | Implemented (static) | DB-backed hashed OTP, expiry, retry cap, CSRF and forced re-login. |
| 5 | View profile/avatar | Implemented | account view and default avatar fallback. |
| 6 | Edit profile/avatar | Partial | profile edit exists; needs live upload MIME/size regression test. |
| 7 | Change account password | Partial | endpoint/UI exists; needs end-to-end old-password/session test. |
| 8 | Preferences | Implemented | theme/language are persisted; font preference is not centralised. |
| 9 | List view | Implemented | list toggle in dashboard. |
| 10 | Grid view | Implemented | grid is the default dashboard view. |
| 11 | Create note | Implemented | title/content validation and PHP create flow. |
| 12 | Update note | Implemented | owner/editor authorization and update flow. |
| 13 | Delete note | Partial | confirm UI exists; needs browser test of cancel/confirm. |
| 14 | Auto-save | Implemented | authorised autosave endpoint plus client flow. |
| 15 | Image/video attachment | Partial | image upload exists; video is not supported. |
| 16 | File attachment | Not implemented | no safe general attachment metadata/storage model yet. |
| 17 | Pin to top | Implemented | pin state and ordered listing exist. |
| 18 | Special indicators | Implemented | pin/share/PIN/reminder indicators in list and grid. |
| 19 | Search | Partial | title/content search exists; debounce needs browser verification. |
| 20 | Label CRUD | Implemented | scoped label APIs/pages exist. |
| 21 | Attach labels | Implemented | note-label relations are supported. |
| 22 | Filter by labels | Implemented | dashboard label filtering exists. |
| 23 | Enable/disable note password | Implemented as approved PIN equivalent | Owner can set/remove a hashed six-digit note PIN. |
| 24 | Change note password/protection | Implemented as approved PIN equivalent | Owner can change PIN; verification is rate-limited and recipient unlock is isolated. |
| 25 | Share/receive notes | Partial | server permissions and recipient UI exist; notification mail/live test is missing. |
| 26 | Realtime collaboration | Partial | conflict/presence polling exists; no WebSocket transport. |
| 27 | AI Summary | Partial | route/UI/service code exists; needs provider credential integration test. |
| 28 | AI Q&A | Partial | retrieval/tooling exists; needs reference-link live test. |
| 29 | UI/UX | Manual review | modern responsive dashboard exists; requires device/manual evaluation. |
| 30 | Responsive | Partial | responsive CSS exists; no device matrix screenshot test. |
| 31 | Offline | Implemented (static) | service worker, IndexedDB stores, sync queue and conflict path checked. |

## Mandatory work before claiming full rubric completion

1. Add a safe attachment table and storage adapter for files/video, with MIME,
   size, ownership and delete tests.
2. Replace collaboration polling with authenticated WebSocket transport, while
   retaining polling as fallback.
3. Add browser/API integration tests for registration, activation, reset,
   upload, delete confirmation, sharing and AI references.
4. Start Docker Desktop and run the compose stack; current Docker validation is
   configuration-only because the local Docker daemon was unavailable.
5. Create a manual responsive test matrix for phone, tablet and desktop.

## Tests currently available

- `php tests/password_reset_security_check.php`
- `php tests/security_regression_check.php`
- `php tests/offline_deployment_static_check.php`
- `php test_system_flow.php` (requires a working MySQL environment)
- `php tests/verify_full_rubric_checklist.php` (requires MySQL; it is not a
  complete 31-item rubric proof and must not be described as one).
