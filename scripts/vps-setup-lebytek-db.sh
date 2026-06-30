#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/home/lebytek/htdocs/lebytek.com
DBPASS=$(openssl rand -hex 16)

echo "==> create database lebytek"
clpctl db:add --domainName=lebytek.com --databaseName=lebytek --databaseUserName=lebytek --databaseUserPassword="$DBPASS"

echo "==> update .env DB_PASSWORD"
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DBPASS}/" "$APP_DIR/.env"

echo "==> install.php"
sudo -u lebytek php "$APP_DIR/scripts/install.php"

echo "==> mkt_leads api columns migration"
sudo -u lebytek mariadb -h 127.0.0.1 -u lebytek -p"$DBPASS" lebytek < "$APP_DIR/database/migrations/20260630120000_mkt_leads_api_columns.sql" || echo "migration may partially apply on fresh install"

echo "==> api health"
sudo -u lebytek php "$APP_DIR/scripts/lebytek-api-health.php"

echo "==> smoke"
curl -sfI -k https://127.0.0.1/ -H 'Host: lebytek.com' | head -1
curl -sfI -k https://127.0.0.1/admin/login -H 'Host: lebytek.com' | head -1

echo "DB_SETUP_DONE"
