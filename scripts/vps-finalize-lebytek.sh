#!/usr/bin/env bash
set -e
APP_DIR=/home/lebytek/htdocs/lebytek.com
DBPASS=$(grep '^DB_PASSWORD=' "$APP_DIR/.env" | cut -d= -f2-)

echo "==> api columns migration"
sudo -u lebytek mariadb -h127.0.0.1 -ulebytek -p"$DBPASS" lebytek < "$APP_DIR/database/migrations/20260630120000_mkt_leads_api_columns.sql" 2>&1 || echo "migration note: may already exist"

echo "==> health"
sudo -u lebytek php "$APP_DIR/scripts/lebytek-api-health.php"

echo "==> smoke"
curl -sfI -k https://127.0.0.1/ -H 'Host: lebytek.com' | head -1
curl -sfI -k https://127.0.0.1/admin/login -H 'Host: lebytek.com' | head -1

echo "==> api routes on server"
curl -sf -H "Authorization: Bearer $(grep '^LEBYTEK_API_TOKEN=' $APP_DIR/.env | cut -d= -f2-)" https://api.lebytek.com/api/v1/health | head -c 200; echo

echo "FINAL_OK"
