# Security audit — 2026-09-02

Scope agreed with the requester: a deep security audit of **existing**
features (not new feature development), honestly verified with the tools
actually available in this environment (no PHP interpreter, no network, no
MySQL — confirmed before starting).

## 1. Finding: the reported label bug was only half-fixed

The request said `api/labels.php` had already been fixed to scope labels by
`user_id` in all 4 branches. On inspection, **that was not accurate**:

- `migrations.sql` *does* add `labels.user_id` and a per-user
  `UNIQUE(user_id, name)` key, dropping the old global-unique `name` key.
- `api/ai/lib/AiServices.php` (the AI assistant's internal tool, used only
  when the AI creates/edits notes) *does* correctly scope label lookups by
  `user_id`.
- But **`api/labels.php`** (the actual REST endpoint the frontend calls) and
  **`api/notes.php`**'s auto-tagging code, and the legacy page
  **`manage_labels.php`**, still did `WHERE name = ?` with no `user_id` at
  all — the exact cross-user label collision bug originally described was
  still fully live in all three.

### Fixed
- `api/labels.php` — rewritten. GET/POST/PUT/DELETE now scope by `user_id`.
  Legacy rows with `user_id IS NULL` are still usable for reads/attach, and
  any **write** (rename/delete) to a legacy shared label now either
  "claims" it (if this user is its only user) or "forks" a private copy and
  remaps only this user's `note_labels` rows — so a write can never again
  silently affect another user's label.
- `api/notes.php` — the AI-auto-tagging label lookup/insert on note creation
  had the same global bug; fixed to match.
- `api/ai/lib/AiServices.php` — one residual line (the race-condition
  fallback after a duplicate-key insert failure) still did a global lookup;
  fixed to stay scoped.
- `manage_labels.php` — a legacy server-rendered page duplicating the same
  bug, plus **no CSRF token at all**. Fixed the label scoping the same way,
  and added a CSRF token (see below) as a proof-of-concept. Also fixed an
  unrelated pre-existing bug in the same query: it used an `INNER JOIN` to
  list labels, so a label with zero notes attached (e.g. right after
  creation) would never show up in the list.

**Note on residual risk**: any note or label data created by *any* user
before this fix used `user_id = NULL` labels. If two different users already
share a collided label row today (created before this patch), the "claim or
fork" logic above resolves it correctly and safely the next time either of
them touches it — no separate manual data migration is required, but until
they touch it, the shared row still exists as-is.

## 2. CSRF — checked, calibrated, not overclaimed

No page in the codebase uses CSRF tokens. However, `api/login.php` (and a
few other entry points) sets `session_set_cookie_params(['samesite' =>
'Lax', ...])` before the session cookie is first issued. Because cookie
attributes are fixed at issuance, this applies to the session cookie for its
whole lifetime. `SameSite=Lax` blocks the classic cross-site auto-submitting
form attack in all current major browsers, so actual CSRF risk was **Low**,
not Critical — I did not build out a full app-wide CSRF token system, since
that would be a bigger change than the audit scope agreed and isn't the
primary risk here.

What I did: added a small reusable helper (`includes/csrf.php`) and wired it
into `manage_labels.php` as a working example, since I was already
editing that file. **Not yet applied** to the other legacy POST-handling
pages (`edit_note.php`, `delete_note.php`, `archive_note.php`, `account.php`,
`update_password.php`, `notepass.php`, `reset_password.php`, etc.) — those
still rely on `SameSite=Lax` alone. Recommend rolling the same `csrf_token()`
/ `csrf_check()` calls out to those pages as a follow-up; the helper is
already written and ready to reuse.

## 3. Reviewed and found solid — no changes needed

- `api/db.php`, `config/database.php` — credentials from environment only,
  CORS restricted to an explicit origin allow-list (not `*`), DB errors not
  leaked to the client.
- `api/upload.php` — ownership check before touching a note, file-size cap,
  extension **and** actual-content MIME-type check (not just the extension),
  randomized filename (prevents path traversal / overwrite / enumeration),
  old file cleanup, DB-failure rollback of the written file.
- `api/notes.php` (GET/POST/PUT/DELETE, aside from the label bug above) —
  prepared statements throughout, ownership checked via `user_id` on every
  read/write, output HTML-escaped before JSON encoding (defends callers that
  render it with `innerHTML`).
- `api/note_pin.php` — PIN lock requires the account password as a second
  factor to set/remove, PIN itself is 6-digit-only and hashed with
  `password_hash`, per-session unlock tracked server-side, ownership checked
  on every branch.
- `api/ai/*` — origin check, session auth, per-user rate limiting, strict
  tool allow-list, risk classification (destructive actions require a
  server-issued, single-use, expiring confirmation token — the client can't
  forge one), `user_id`/`userId` stripped from any AI-supplied tool
  arguments before execution (the AI cannot smuggle another user's id in),
  every tool query scoped by `user_id` in `AiServices.php` (aside from the
  one line fixed above).
- No raw string-interpolated SQL (`->query("... $_GET ...")`) found anywhere
  in the codebase — grepped explicitly for this pattern across all `.php`
  files; every dynamic query goes through prepared statements.

## 4. Secrets / `.env`

- `.env.example` already existed and looks complete.
- **`.gitignore` did not exist at all** — created one (`.gitignore` in repo
  root) excluding `.env`, `vendor/`, `node_modules/`, build output, and
  user-uploaded files, per your explicit requirement not to ship secrets.
- `.env` itself was **not modified**, per your instruction (you're using
  your own Gmail account for OTP delivery there).
- `.env` **is excluded from this delivered zip** — it contains live
  credentials (a populated Gmail `MAIL_USERNAME`/`MAIL_PASSWORD`, and other
  provider keys), and shipping the actual archive with those in it would
  violate the "no secrets in the submission" requirement even though the
  file itself is untouched in your working copy. Only `.env.example` is
  included here.

## 5. Explicitly NOT done in this pass (be aware)

- No new features were built (trash/restore, version history, folders,
  sharing UI, storage quotas, etc.) — this pass was scoped to security
  fixes on existing functionality only, per your confirmation.
- CSRF tokens were added to one page as a template, not rolled out
  everywhere (see §2).
- `php -l`, `npm ci`, `npm build`, `npm lint`, DB migration execution, live
  API testing, and Docker build/run are **BLOCKED** in this sandbox: no PHP
  interpreter is installed, there is no network access to install one or run
  `npm`, and there is no MySQL instance available. I confirmed this directly
  (`php -v` → not found; `apt-get install php-cli` → `403 Forbidden`, no
  network). I did not fabricate results for any of these.

## 6. What was actually verified, and how

- **PHP-tag-aware structural check** — I initially wrote a naive brace/paren
  balance checker (since `php -l` isn't available) and ran it only on the
  files I edited. On your follow-up request to re-verify "the whole app,
  everywhere," I re-ran it against **all 47 PHP files in the project**
  (excluding the vendored `PHPMailer-master/` library and the frontend). The
  first version gave 3 false-positive failures (`index_notezy.php`,
  `themghichu.php`, `edit_note.php`) — these files embed `<style>` CSS blocks
  outside the `<?php ?>` tags, and my naive scanner was counting CSS `{ }`
  braces as if they were PHP code. I fixed the checker to only scan inside
  `<?php ... ?>` / `<?= ?>` segments and re-ran it: **all 47 files now pass**,
  including those 3 (which I never edited — confirming it was a tool
  limitation, not a real bug in your code).
  **This is still not a real PHP parser** — it cannot catch every syntax
  error class (e.g. a misused keyword, wrong function arity), only
  mechanical brace/paren/bracket mismatches. It is not a substitute for
  running `php -l` on a machine with PHP installed.
- **`ai_agent` Python test suite** — ran for real, twice, both times:
  `python3 -m unittest discover -s tests` → **9/9 PASS**.
- **`python3 -m py_compile`** on all `ai_agent/*.py` — compiles cleanly.
- **`note.sql` / `migrations.sql`** — checked for balanced parentheses
  outside string literals: none found unbalanced. This is a weak signal
  (not a real SQL parser, cannot catch e.g. a wrong column name or type
  mismatch) — the SQL was never actually executed against a MySQL server.
- **`docker-compose.yml`** — parsed with Python's `yaml` library: valid
  YAML, 5 services (`web`, `db`, `ai_agent`, `mailpit`, `phpmyadmin`), 2
  named volumes, every service has an `image` or `build` key. This confirms
  the file is *well-formed*, not that `docker compose up` succeeds — no
  Docker daemon is available in this sandbox to actually build/run it
  (confirmed again just now: `docker: not found`, no network to install it).
- **`Dockerfile`** — read manually: base image `php:8.2-apache`, installs
  `mysqli`/`pdo_mysql`/`mbstring`/`intl`, enables `mod_rewrite`, sets
  `AllowOverride All`, copies the project, fixes `uploads/` ownership/perms.
  Looks structurally correct; **not build-tested** (no Docker here).
- **`env.php`** — manually verified the precedence logic is correct: real
  environment variables (e.g. the ones `docker-compose.yml` injects, like
  `DB_HOST=db`) always win over `.env` file values, `.env` only fills in
  gaps. So running under Docker will correctly use `db` as the MySQL host
  even though `.env` says `127.0.0.1` for local/XAMPP use — no conflict.
- Manual line-by-line review (not automated) of every file listed in §3.

### What I still cannot confirm, and why

I do **not** have a working PHP interpreter, MySQL server, Docker daemon, or
network access in this sandbox (re-confirmed on this pass: `php -v` → not
found, `docker` → not found, `apt-get install php-cli` → `403 Forbidden`,
`pip install phply` → no network). That means I cannot:
- Actually run `docker compose up` and watch all 5 containers start healthy.
- Actually run `php -l` / execute any PHP page and see it respond with no
  fatal error.
- Actually run the migrations against a real MySQL instance.
- Actually exercise the API endpoints over HTTP.

**I am not going to claim these passed when I could not run them.** If you
need that level of confirmation, the reliable way is to run it yourself on a
machine with Docker installed:
```
docker compose up --build
# then check container logs for errors:
docker compose logs web
docker compose logs db
docker compose logs ai_agent
```
and/or install PHP locally and run `php -l` on every file, e.g.:
```
find . -name "*.php" -not -path "./PHPMailer-master/*" -exec php -l {} \;
```
I'm glad to review whatever error output that produces, if you run it and
paste it back.

## 7. Recommended next steps (not done here)

1. Roll `includes/csrf.php` out to the other legacy form-posting pages.
2. Run `php -l` on the changed files and the full app on a machine with PHP,
   before deploying.
3. Run the actual migration (`migrations.sql`) against a real MySQL instance
   and manually exercise `api/labels.php`'s claim/fork logic against
   pre-existing collided rows from before this fix, if any exist in
   production data.
4. If you want the larger feature set from the original request (trash,
   versioning, folders, sharing, storage quotas, AI knowledge-assistant
   expansion, dashboard/landing page, etc.), scope that as a separate,
   explicit follow-up — it's a substantial build, not a security patch.
