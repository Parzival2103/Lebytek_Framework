#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/home/lebytek/htdocs/lebytek.com
ROOTPASS=$(clpctl db:show:master-credentials 2>/dev/null | awk -F'|' '/Password/ {gsub(/ /,"",$3); print $3}')
DBPASS=$(openssl rand -hex 16)

echo "==> reset mysql user lebytek"
mariadb -h127.0.0.1 -uroot -p"$ROOTPASS" -e "CREATE USER IF NOT EXISTS 'lebytek'@'localhost' IDENTIFIED BY '$DBPASS'; GRANT ALL PRIVILEGES ON lebytek.* TO 'lebytek'@'localhost'; FLUSH PRIVILEGES;"

sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DBPASS}/" "$APP_DIR/.env"

echo "==> install.php"
sudo -u lebytek php "$APP_DIR/scripts/install.php"

echo "==> api columns migration"
sudo -u lebytek mariadb -h127.0.0.1 -ulebytek -p"$DBPASS" lebytek < "$APP_DIR/database/migrations/20260630120000_mkt_leads_api_columns.sql" 2>&1 || true

echo "==> health"
sudo -u lebytek php "$APP_DIR/scripts/lebytek-api-health.php"

echo "==> smoke"
curl -sfI -k https://127.0.0.1/ -H 'Host: lebytek.com' | head -1
curl -sfI -k https://127.0.0.1/admin/login -H 'Host: lebytek.com' | head -1

echo "DB_FIX_DONE"
