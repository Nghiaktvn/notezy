# PHP service layer

This directory is the incremental PHP-only service layer. It does not replace
the server-rendered PHP application used by XAMPP; that app remains the
backward-compatible UI while routes are moved behind the gateway.

## Docker

Add `JWT_SECRET` (long random value) to your local `.env`, then run:

```powershell
docker compose -f docker-compose.yml -f docker-compose.services.yml up -d --build
```

- Legacy PHP application: `http://localhost:8080`
- PHP API gateway: `http://localhost:8088/health`
- Internal services are not exposed to the host.

The first run of a fresh MySQL volume imports `services/db/premium.sql` after
the legacy schema/migrations. To import Premium tables into an existing local
volume, run that SQL once with an authorized MySQL client; it is idempotent.

## XAMPP

Keep serving the project root from Apache exactly as before. The legacy PHP
pages and APIs remain the XAMPP compatibility path. The service files are
plain PHP and may be run for local API development with:

```powershell
php -S localhost:8088 -t services/gateway/public services/gateway/router.php
```

Gateway upstream hostnames (`auth_service`, `note_service`, and so on) are
Docker-only. Under XAMPP, continue to use the legacy local PHP APIs until a
local service host configuration is explicitly added.
