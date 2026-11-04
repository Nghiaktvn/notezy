# Free Render demonstration

This is a small demonstration deployment: one **Free Web Service** runs Apache/PHP,
the Python AI agent and the WebSocket relay. MySQL runs separately on a provider's
explicit **Free** plan. It does not change the original Docker Compose topology.

For an existing Render service, edit that service instead of creating a duplicate:

1. Deploy the reviewed branch `codex/fix-note-access-and-free-deploy` and set its
   Dockerfile path to `Dockerfile.render`. Keep its instance type **Free**.
2. Create an **Aiven for MySQL Free** service (not a trial/paid service), or reuse an
   existing MySQL database. Do not add a card or enable a paid upgrade for this demo.
3. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` using the
   provider's exact values. Aiven commonly provides `defaultdb`; the import script
   supports that name. `localhost` and Docker's hostname `db` cannot reach a
   different hosted MySQL service from Render. Provider ports may differ from 3306.
4. Download the database provider CA and add it as a Render **secret file** named
   `aiven-ca.pem`. Set `DB_SSL_REQUIRED=true` and `DB_SSL_CA=/etc/secrets/aiven-ca.pem`.
   Do not disable certificate verification to make a failed TLS connection pass.
5. Set `AI_AGENT_SHARED_SECRET` to a generated random value. Set
   `AUTO_INIT_DATABASE=true`; the CLI bootstrap imports the baseline only into an
   empty database, then applies the idempotent migrations. Existing data is kept.
6. Optionally set `NOTEZY_SEED_EMPTY_DEMO=true`. This creates `user1`/`user2` with
   the documented `Password123!` **only if the users table is empty**. It does not
   reset existing accounts. These are public demo accounts; keep real notes out.
7. Deploy and wait for `/health.php` to return HTTP 200 with `database: connected`.
   A live PHP landing page alone does not prove that MySQL is working.

`render.yaml` is a template for a **new** service. Review the name before using it
when a service already exists. It explicitly selects the Free plan. The platform
supplies `RENDER_EXTERNAL_URL`, which this image uses for `APP_URL` unless you set
`APP_URL` yourself. WebSocket clients use the same HTTPS hostname at `/ws`.

## AI and email

The template deliberately uses `LLM_PROVIDER=mock`: the AI create-note and chat
workflow can be demonstrated without an external paid API call. This is a
deterministic demo provider, not a real large language model. To enable Gemini,
set `LLM_PROVIDER=gemini`, `GEMINI_API_KEY` and a supported `GEMINI_MODEL` privately.
Check the actual Google AI Studio project's **Plan**; a model name or environment
variable cannot prove its billing tier. Do not enable billing or top up credits
when the requirement is zero cost. The currently reviewed local `.env` has no
Gemini key and uses `mock`.

Email delivery is not enabled by this template. Render Free blocks outbound SMTP
ports 25/465/587. Keep the existing email workflow pending until its owner provides
a working HTTPS email service configuration. A registration saved without an OTP
email is not a passed external email-delivery test. The seeded demo accounts are
already activated and can demonstrate the other features meanwhile.

## Limits and troubleshooting

- Render Free sleeps on inactivity and has a shared free-hours quota. Uploaded
  files and sessions are ephemeral across restarts/redeployments. MySQL data lives
  in the separate database; durable upload storage is not configured by this demo.
- `/health.php` returning 503 means the database connection/ping failed. Inspect
  the service log for `Notezy DB Connect Error`; check host, provider port, password,
  database name, network allowlist and CA configuration. Keep credentials private.
- A fresh Render deployment does not launch `docker-compose.yml` or create MySQL
  automatically. `Dockerfile.render` includes the app processes, not a MySQL server.
- The bootstrap stops on a non-empty database without a `users` table rather than
  overwriting an unknown schema. Resolve partial imports explicitly.
- Only claim a working public deployment after registration/login, note CRUD,
  protection, sharing, AI and the WebSocket proxy have been checked at that URL.

Provider references checked on 4 November 2026:
[Render Free](https://render.com/docs/free),
[Aiven MySQL Free](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier),
[Gemini billing](https://ai.google.dev/gemini-api/docs/billing).
