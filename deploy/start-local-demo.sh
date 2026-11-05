#!/bin/bash
set -euo pipefail
cd /var/www/html
php scripts/init_cloud_database.php
php scripts/seed_empty_demo.php
php scripts/seed_demo_admin.php
exec /bin/bash /var/www/html/entrypoint.sh
