#!/bin/bash
# Render mounts secret files for root. Apache workers need a readable copy of
# this public CA certificate, outside the document root. Never loosen the
# mounted secret's permissions or disable TLS certificate verification.
if [[ -n "${DB_SSL_CA:-}" ]]; then
    if [[ ! -r "$DB_SSL_CA" ]]; then
        echo 'Configured database CA certificate is unavailable.' >&2
        exit 1
    fi
    install -d -m 0755 /var/lib/notezy
    install -m 0644 "$DB_SSL_CA" /var/lib/notezy/db-ca.pem
    export DB_SSL_CA=/var/lib/notezy/db-ca.pem
fi
