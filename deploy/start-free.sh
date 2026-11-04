#!/bin/bash
set -euo pipefail
cd /var/www/html
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT:-80}}}"
: "${AI_AGENT_SHARED_SECRET:?Set AI_AGENT_SHARED_SECRET before starting the demo}"
source deploy/prepare-ca.sh
if [[ "${AUTO_INIT_DATABASE:-false}" == "true" ]]; then
    php scripts/init_cloud_database.php
fi
if [[ "${NOTEZY_SEED_EMPTY_DEMO:-false}" == "true" ]]; then
    php scripts/seed_empty_demo.php
fi
exec /usr/bin/supervisord -c /etc/supervisor/notezy-free.conf
