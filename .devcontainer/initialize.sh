#!/usr/bin/env bash
set -euo pipefail

# Compose interpolates required secrets before the development container exists.
# Generate per-Codespace values on the GitHub host and keep them in ignored .env.
if [[ ! -f .env ]]; then
  umask 077
  secret="$(openssl rand -hex 32)"
  db_password="$(openssl rand -hex 24)"
  codespace_name="${CODESPACE_NAME:-}"
  forwarding_domain="${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
  if [[ -n "$codespace_name" ]]; then
    app_url="https://${codespace_name}-8080.${forwarding_domain}"
    websocket_url="wss://${codespace_name}-8766.${forwarding_domain}"
  else
    app_url="http://localhost:8080"
    websocket_url="ws://localhost:8766"
  fi

  printf '%s\n' \
    'DB_HOST=db' \
    'DB_PORT=3306' \
    'DB_NAME=notezy' \
    'DB_USER=root' \
    "DB_PASSWORD=${db_password}" \
    "SERVICE_DB_PASSWORD=${db_password}" \
    "AI_AGENT_SHARED_SECRET=${secret}" \
    "JWT_SECRET=${secret}" \
    "APP_URL=${app_url}" \
    "COLLAB_WS_PUBLIC_URL=${websocket_url}" \
    'LLM_PROVIDER=mock' > .env
fi
