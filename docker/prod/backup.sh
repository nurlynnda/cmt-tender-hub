#!/bin/sh
# Daily database backup on the server: backups/tender_hub-YYYY-MM-DD.sql.gz, the last 7 days kept.
# Runs from cron (see docs/DEPLOY.md). Restore with: docs/DEPLOY.md, "Restoring a backup".
set -e
cd "$(dirname "$0")/../.."
mkdir -p backups
file="backups/tender_hub-$(date +%F).sql.gz"
docker compose -f docker-compose.prod.yml exec -T mysql sh -c \
    'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --no-tablespaces "$MYSQL_DATABASE"' \
    | gzip > "$file.part"
mv "$file.part" "$file"          # only a complete backup gets the real name
find backups -name 'tender_hub-*.sql.gz' -mtime +7 -delete
echo "Backup saved: $file"
