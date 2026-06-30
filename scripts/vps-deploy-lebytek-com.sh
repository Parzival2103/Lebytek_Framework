#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/home/lebytek/htdocs/lebytek.com
REPO=https://github.com/Parzival2103/Lebytek_Framework.git
BRANCH=feature/backoffice-api-integration
ENV_BAK=/tmp/lebytek-env-backup.env

echo "==> backup .env"
cp "$APP_DIR/.env" "$ENV_BAK"

echo "==> clone branch $BRANCH"
rm -rf /tmp/lebytek-deploy
git clone --depth 1 --branch "$BRANCH" "$REPO" /tmp/lebytek-deploy

echo "==> replace app files (keep .env)"
find "$APP_DIR" -mindepth 1 -maxdepth 1 ! -name '.env' -exec rm -rf {} +
cp -a /tmp/lebytek-deploy/. "$APP_DIR/"
cp "$ENV_BAK" "$APP_DIR/.env"
chown -R lebytek:lebytek "$APP_DIR"

echo "==> enable marketing module"
sed -i "s/'marketing'      => false/'marketing'      => true/" "$APP_DIR/config/vertical.php"

echo "==> composer install"
cd "$APP_DIR"
sudo -u lebytek composer install --no-dev --optimize-autoloader --no-interaction

echo "==> storage dirs"
sudo -u lebytek mkdir -p storage/logs storage/cache storage/uploads storage/temp storage/exports storage/imports public/uploads
sudo -u lebytek chmod -R ug+rwX storage public/uploads 2>/dev/null || true

echo "==> nginx docroot -> public/"
if grep -q 'root /home/lebytek/htdocs/lebytek.com;' /etc/nginx/sites-enabled/lebytek.com.conf; then
  sed -i 's|root /home/lebytek/htdocs/lebytek.com;|root /home/lebytek/htdocs/lebytek.com/public;|g' /etc/nginx/sites-enabled/lebytek.com.conf
  nginx -t
  systemctl reload nginx
  echo "nginx reloaded"
fi

echo "==> APP_KEY check"
if grep -q 'change_me_before_deploy' "$APP_DIR/.env"; then
  NEWKEY=$(openssl rand -hex 16)
  sed -i "s/^APP_KEY=.*/APP_KEY=${NEWKEY}/" "$APP_DIR/.env"
  echo "generated APP_KEY"
fi

echo "==> install DB"
set +e
sudo -u lebytek php "$APP_DIR/scripts/install.php"
INSTALL_RC=$?
set -e

if [ "$INSTALL_RC" -eq 0 ]; then
  echo "==> apply mkt_leads api migration SQL"
  sudo -u lebytek php "$APP_DIR/scripts/migrate.php" 2>/dev/null || true
  if [ -f "$APP_DIR/database/migrations/20260630120000_mkt_leads_api_columns.sql" ]; then
    sudo -u lebytek bash -c "cd '$APP_DIR' && mariadb -h \"\$(grep ^DB_HOST= .env | cut -d= -f2)\" -u \"\$(grep ^DB_USERNAME= .env | cut -d= -f2)\" -p\"\$(grep ^DB_PASSWORD= .env | cut -d= -f2-)\" \"\$(grep ^DB_DATABASE= .env | cut -d= -f2)\" < database/migrations/20260630120000_mkt_leads_api_columns.sql" 2>&1 || echo "migration sql skipped"
  fi
else
  echo "WARN: install.php failed — set DB_PASSWORD in $APP_DIR/.env and re-run: php scripts/install.php"
fi

echo "==> api health"
set +e
sudo -u lebytek php "$APP_DIR/scripts/lebytek-api-health.php"
HEALTH_RC=$?
set -e

echo "==> smoke"
curl -sfI -k https://127.0.0.1/ -H 'Host: lebytek.com' | head -1 || true
curl -sfI -k https://127.0.0.1/admin/login -H 'Host: lebytek.com' | head -1 || true

echo "DEPLOY_DONE health_rc=$HEALTH_RC install_rc=$INSTALL_RC"
