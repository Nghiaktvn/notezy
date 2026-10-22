# Supabase ERD for Notezy

This folder contains the PostgreSQL translation of the existing MySQL schema in
`note.sql` and `migrations.sql`. The two schema migrations create the complete
27-table ERD. The current PHP runtime still uses MySQL/mysqli; see
`ERD_AUDIT.md` for the verified feature mapping and migration blockers.

## Create the ERD

1. Create a new project at [Supabase](https://supabase.com/dashboard).
2. Open **SQL Editor**, create a new query, and paste the contents of
   `migrations/20260916000000_notezy_schema.sql`.
3. Run it once. In the left navigation, open **Database > ERD** and choose the
   `public` schema. Every relationship is shown from its foreign keys.
4. Run `20260916010000_complete_feature_schema.sql` to add chat, premium and
   service tables.
5. Review and run `20260916020000_rls_hardening.sql` before exposing the Data
   API. It enables RLS without creating permissive client policies.

For a CLI-based workflow, install and authenticate the Supabase CLI, link the
project, then run:

```powershell
supabase db push
```

## Verify the live project

After adding `SUPABASE_DB_PASSWORD` to the server-only `.env`, run:

```powershell
php scripts/check_supabase.php
```

The check is read-only. It verifies the database connection, all 27 expected
tables and RLS status. The project Docker image includes `pdo_pgsql`; XAMPP
users must enable `extension=pdo_pgsql` in their local PHP configuration.
Copy the variable names from `supabase/.env.example`; never put real credentials
in the committed root `.env.example` template.

## Important

- Add only the project URL and publishable/anon key to a client application.
  Never expose a database password or service-role key in browser code or Git.
- This repository's PHP pages use MySQLi SQL syntax and connection code. A real
  production migration to Supabase also needs the PHP data layer to be rewritten
  for PostgreSQL or replaced with the Supabase API.
- Do not set `DB_DRIVER=pgsql` yet. The current PHP runtime has direct mysqli
  dependencies and uses MySQL-only SQL in multiple features.
- The migration adds foreign keys for relationships that were implicit in the
  legacy schema (`timetable.note_id`, `quiz_results.note_id`, collaboration
  presence, and pending AI actions), so the ERD accurately represents the
  application's model.
