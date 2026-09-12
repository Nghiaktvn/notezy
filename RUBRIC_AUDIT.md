# Notezy — Final Rubric Verification

**Verified:** 2026-09-12
**Target:** GitHub Codespaces full-stack deployment

## Result

The live local Docker environment passed the full rubric checklist: **31/31**
automated checks. The frontend production PWA build completed with zero npm
vulnerabilities, all PHP files passed syntax validation, the AI agent passed
9/9 unit tests, and the authenticated two-client WebSocket broadcast passed.

## Account management

- Registration stores a bcrypt password hash, signs the user in immediately,
  issues a hashed expiring activation OTP and a hashed activation-link token.
- Unverified accounts retain access while the dashboard shows a prominent
  verification notice.
- Login/logout, reset OTP, forced login after reset, profile/avatar editing,
  password change, theme and language preferences were exercised successfully.
- Real SMTP accepted both activation and password-reset messages during the
  local end-to-end verification.

## Note management

- Grid/list views, create/update/delete confirmation, debounced autosave, live
  title/content search, pin ordering and special-note indicators are present.
- Label list/create/rename/delete, multi-label attachment and filtering passed.
- Safe image, video and general-file attachments use ownership checks, metadata,
  MIME/size allowlists and controlled downloads.
- Per-note protection requires a six-digit hashed PIN, confirmation on create
  or change, current secret verification for change/removal and rate limiting.
- Sharing validates registered recipients, supports read/write permissions,
  owner changes/revocation, recipient metadata and shared-note views.
- Realtime collaboration uses authenticated note-scoped WebSocket tokens,
  same-room broadcast, presence/typing state and conflict handling.

## AI, schedule and offline

- AI summary and retrieval-based Q&A return referenced notes and support
  regeneration. Prompt-injection boundaries and tool-risk filtering are tested.
- Notes can be linked directly into the timetable with an alarm or explicitly
  without one. Sunday uses the API contract (`7`), disabled alarms never fire,
  and timetable sharing supports read/write permission and revocation.
- The PWA service worker, IndexedDB notes/labels stores, mutation queue,
  reconnection sync and conflict path passed the offline/deployment checks.

## Architecture and deployment

- Docker health checks cover MySQL, the web app, AI agent and WebSocket relay.
- The service layer separates gateway, auth, user, note, file, AI, premium,
  collaboration and outbox worker services, with Redis and least-privilege
  database accounts.
- `.devcontainer/devcontainer.json` runs the full stack in GitHub Codespaces.
  Per-Codespace database/JWT secrets are generated outside source control.
- Public URLs use GitHub's HTTPS `app.github.dev` tunnel; realtime automatically
  converts it to the corresponding secure WebSocket tunnel.

## GitHub-only launch

Use the **Open in GitHub Codespaces** button in `README.md`, wait for the stack,
then make ports `8080` and `8766` public. Keep phpMyAdmin port `8081` private.
Codespaces must remain running while the
grader uses the URL. Mail and live LLM credentials belong in Codespaces Secrets,
never in the repository.
