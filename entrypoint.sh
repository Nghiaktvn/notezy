#!/bin/bash
set -e

# Support dynamic port binding on Railway, Render, Fly.io, Heroku
PORT="${PORT:-80}"

echo "Starting Notezy Apache server on port ${PORT}..."
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/*.conf 2>/dev/null || true

# Ensure uploads directory is ready
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# Persistent server-side sessions must survive Apache restarts and be writable
# by the unprivileged web user.  Vietnam is the application timezone.
mkdir -p /var/www/html/storage/sessions
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
export TZ=Asia/Ho_Chi_Minh

exec apache2-foreground
