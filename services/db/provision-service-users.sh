#!/bin/sh
# Idempotent least-privilege provisioning for the PHP microservices.
# SERVICE_DB_PASSWORD may be overridden by a secret manager in production;
# local Docker falls back to the existing DB_PASSWORD without editing .env.
set -eu

: "${MYSQL_HOST:=db}"
: "${MYSQL_PORT:=3306}"
: "${MYSQL_DATABASE:=notezy}"
: "${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD is required}"
: "${SERVICE_DB_PASSWORD:=${MYSQL_ROOT_PASSWORD}}"

case "$MYSQL_DATABASE" in (*[!A-Za-z0-9_]*|'') echo 'Invalid MYSQL_DATABASE' >&2; exit 2;; esac

# MySQL string escaping for the password literal. The account names and schema
# name below are fixed constants, never caller input.
escaped_password=$(printf '%s' "$SERVICE_DB_PASSWORD" | sed "s/'/''/g")
run_mysql() { MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root --protocol=TCP "$@"; }

# Existing Docker volumes do not re-run docker-entrypoint-initdb.d. Apply all
# idempotent migrations before grants so upgraded deployments match new ones.
if [ -f /migrations.sql ]; then
  run_mysql "$MYSQL_DATABASE" < /migrations.sql
fi
if [ -f /premium.sql ]; then
  run_mysql "$MYSQL_DATABASE" < /premium.sql
fi
if [ -f /production.sql ]; then
  run_mysql "$MYSQL_DATABASE" < /production.sql
fi

run_mysql <<SQL
CREATE USER IF NOT EXISTS 'notezy_auth'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_user'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_note'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_file'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_collaboration'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_premium'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_ai'@'%' IDENTIFIED BY '${escaped_password}';
CREATE USER IF NOT EXISTS 'notezy_outbox'@'%' IDENTIFIED BY '${escaped_password}';

GRANT SELECT, INSERT, UPDATE ON \`${MYSQL_DATABASE}\`.users TO 'notezy_auth'@'%';
GRANT SELECT, UPDATE ON \`${MYSQL_DATABASE}\`.users TO 'notezy_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.notes TO 'notezy_note'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.labels TO 'notezy_note'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.note_labels TO 'notezy_note'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.note_shares TO 'notezy_note'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.notes TO 'notezy_file'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.note_shares TO 'notezy_file'@'%';
GRANT SELECT, INSERT, DELETE ON \`${MYSQL_DATABASE}\`.note_attachments TO 'notezy_file'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.notes TO 'notezy_collaboration'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.note_shares TO 'notezy_collaboration'@'%';
GRANT SELECT, INSERT, UPDATE ON \`${MYSQL_DATABASE}\`.note_collab_presence TO 'notezy_collaboration'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.plans TO 'notezy_premium'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.entitlements TO 'notezy_premium'@'%';
GRANT SELECT ON \`${MYSQL_DATABASE}\`.subscriptions TO 'notezy_premium'@'%';
GRANT SELECT, INSERT, UPDATE ON \`${MYSQL_DATABASE}\`.usage_records TO 'notezy_premium'@'%';
GRANT USAGE ON \`${MYSQL_DATABASE}\`.* TO 'notezy_ai'@'%';
GRANT SELECT, UPDATE ON \`${MYSQL_DATABASE}\`.service_outbox TO 'notezy_outbox'@'%';
FLUSH PRIVILEGES;
SQL

echo 'Service database accounts provisioned.'
