# Notezy ERD audit

## Result

The Supabase `public` schema contains the 27 tables needed by the current
application. The ERD is structurally complete, but the PHP runtime is not yet a
Supabase runtime: it still connects through `mysqli`/PDO MySQL and contains
MySQL-specific SQL.

Live verification on 2026-09-21 confirmed that all 27 Notezy tables have Row
Level Security enabled (`pg_class.relrowsecurity = true`). No permissive client
policies have been added, so the Supabase Data API remains deny-by-default.

## Feature-to-table map

| Feature | Tables |
| --- | --- |
| Accounts and security | `users`, `note_pin_unlocks` |
| Notes and organization | `notes`, `labels`, `note_labels`, `note_attachments` |
| Sharing and collaboration | `note_shares`, `note_collab_presence`, `chat_messages` |
| AI and study tools | `ai_conversations`, `ai_messages`, `ai_pending_actions`, `ai_rate_limits`, `flashcards`, `quiz_results` |
| Timetable | `timetable`, `timetable_shares` |
| Premium | `plans`, `entitlements`, `subscriptions`, `usage_records`, `premium_trials` |
| Hosting and integrations | `hosting_github_connections`, `hosting_render_connections`, `hosting_deployments`, `hosting_custom_domains`, `service_outbox` |

## Verified checks

- 27 PostgreSQL tables are declared across the two schema migrations.
- All 27 application table names are represented in the PHP/SQL sources.
- All 106 first-party PHP files pass `php -l`.
- Security regression, password-reset security and offline/deployment static
  test suites pass.
- The live Supabase dashboard shows the schema and its foreign-key edges.
- A live catalog query returned 27 rows with `rls_enabled = true`.

## Runtime blockers

1. `config/database.php` returns `mysqli`, and 59 PHP files call mysqli-specific
   APIs such as `bind_param()`, `get_result()` and `insert_id`.
2. `services/shared/bootstrap.php` uses a `mysql:` PDO DSN.
3. Queries use MySQL-only syntax including `ON DUPLICATE KEY UPDATE`,
   `GROUP_CONCAT`, `DATE_SUB`, `FIELD`, `AUTO_INCREMENT` and `SHOW COLUMNS`.
4. The installed PHP CLI has `pdo_mysql` but not `pdo_pgsql` enabled, even
   though the XAMPP PostgreSQL extension DLLs are present.
5. Application users are integer IDs managed by PHP sessions. They are not yet
   mapped to Supabase Auth UUIDs, so user-scoped RLS policies cannot safely be
   enabled yet.

## Safe migration order

1. Enable `pdo_pgsql` in the deployment PHP runtime.
2. Add the Supabase database password to the server-only secret store.
3. Introduce a PostgreSQL repository layer without changing existing routes.
4. Port queries module-by-module: accounts, notes, collaboration, AI,
   timetable, premium, then hosting.
5. Run dual-database contract tests, migrate data, and switch the runtime only
   after every module passes.
6. Decide whether to retain PHP session auth or migrate to Supabase Auth; only
   then add narrowly scoped RLS policies.

Do not commit a database password or service-role key. Put them only in the
runtime `.env` or the deployment platform's secret store.
