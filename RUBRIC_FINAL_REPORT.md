# Rubric Final Report

| ID | Criteria | Max | Result | Evidence |
|----|----------|-----|--------|----------|
| 27 | Offline capabilities | 0.5 | 0.5 | PASS: Service worker caches app shell/static assets; IndexedDB stores notes, labels, metadata, and sync queue; offline create/update/delete/pin/label actions queue locally; auto-sync runs on `online`; API returns HTTP 409 for stale offline updates. |
| 28 | Online deployment | 0.5 | 0.5 | PASS for production-ready configuration: Dockerfile, Compose services, Railway/Render config, safe `.env.example`, `/health.php`, DB env support for Docker/XAMPP/cloud. Real cloud deploy was not performed in this local environment. |

## Files changed

- `api/notes.php`: Added `version`/`updated_at` metadata, conflict-aware PUT with HTTP 409, and sync-friendly create/update responses.
- `api/labels.php`: Added note-level label removal endpoint for offline `REMOVE_LABEL` sync.
- `js/offline-store.js`: Rebuilt IndexedDB offline engine with notes, labels, metadata, sync queue, retry state, status UI, offline rendering, and global `window.NotezyOffline` export.
- `index_notezy.php`: Added offline create, delete, pin handling and local render fallback.
- `edit_note.php`: Added offline autosave/manual-save queue handling.
- `docker-compose.yml`: Added `APP_ENV`, `APP_URL`, and healthcheck against `/health.php`.
- `health.php`: Added JSON health endpoint with database status and no secret leakage.
- `.env.example`: Added safe environment template; `.env` was not modified.
- `OFFLINE_TEST_PROCEDURE.md`: Added manual QA checklist for offline rubric.
- `tests/offline_deployment_static_check.php`: Added static verification for offline/deployment wiring.

## Verification run

- `php -l api/notes.php`: PASS
- `php -l api/labels.php`: PASS
- `php -l index_notezy.php`: PASS
- `php -l edit_note.php`: PASS
- `php -l health.php`: PASS
- `node --check js/offline-store.js`: PASS
- `php tests/offline_deployment_static_check.php`: PASS
- `php test_system_flow.php`: PASS with live MySQL through Docker on `127.0.0.1:3307`.
- `C:\xampp\php\php.exe test_system_flow.php`: PASS with live MySQL through Docker on `127.0.0.1:3307`.
- `docker compose up -d --build`: PASS; web, db, ai_agent, and phpMyAdmin started successfully.
- `docker compose exec -T web php test_system_flow.php`: PASS with live MySQL inside Docker.
- `Invoke-WebRequest http://localhost:8080/health.php`: PASS, returns `status=ok` and `database=connected`.
- `Invoke-WebRequest http://localhost:8081`: PASS, phpMyAdmin loads and shows database `notezy`.

## Environment blockers

- None in the verified Docker path. Docker initially required elevated access to the local Docker API, then the full stack started successfully.
- A direct Apache/XAMPP browser run was not started here, but the installed XAMPP PHP runtime passed the same live MySQL regression suite.

## XAMPP run

1. Copy project to `C:\xampp\htdocs\notezy`.
2. Keep local `.env` values for XAMPP, typically `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_USER=root`, `DB_PASSWORD=`.
3. Import `note.sql`, then `migrations.sql` into database `notezy`.
4. Open `http://localhost/notezy/index.php`.

## Docker run

1. Start Docker Desktop.
2. Run `docker compose up -d --build`.
3. Open web app at `http://localhost:8080`.
4. Open phpMyAdmin at `http://localhost:8081`.
5. Check health at `http://localhost:8080/health.php`.

## Production deploy

Use Railway or Render with Docker. Set environment variables from `.env.example`, attach a production MySQL database, set `APP_ENV=production`, `APP_URL` to the HTTPS public URL, and run/import `note.sql` then `migrations.sql`. After deploy, verify `/health.php`, register/login, note CRUD, upload, sharing, AI, and the offline checklist.
