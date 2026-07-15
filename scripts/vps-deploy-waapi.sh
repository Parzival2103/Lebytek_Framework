#!/usr/bin/env bash
set -euo pipefail

APP_DIR=/home/lebytek-waapi/htdocs/waapi.lebytek.com
REPO=https://github.com/Parzival2103/Lebytek_Framework.git
BRANCH=feature/backoffice-api-integration
ENV_BAK=/tmp/waapi-env-backup.env

if [ -f "$APP_DIR/skeleton/.env" ]; then
  cp "$APP_DIR/skeleton/.env" "$ENV_BAK"
elif [ -f "$APP_DIR/.env" ]; then
  cp "$APP_DIR/.env" "$ENV_BAK"
else
  touch "$ENV_BAK"
fi

echo "==> clone branch $BRANCH"
rm -rf /tmp/waapi-deploy
git clone --depth 1 --branch "$BRANCH" "$REPO" /tmp/waapi-deploy

echo "==> replace app files"
find "$APP_DIR" -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +
cp -a /tmp/waapi-deploy/. "$APP_DIR/"
cp "$ENV_BAK" "$APP_DIR/.env"
chown -R lebytek-waapi:lebytek-waapi "$APP_DIR"

grep -q '^WAAPI_PORTAL_ENABLED=' "$APP_DIR/.env" \
  && sed -i 's/^WAAPI_PORTAL_ENABLED=.*/WAAPI_PORTAL_ENABLED=true/' "$APP_DIR/.env" \
  || echo 'WAAPI_PORTAL_ENABLED=true' >> "$APP_DIR/.env"
grep -q '^MKT_EMAIL_DASHBOARD_URL=' "$APP_DIR/.env" \
  || echo 'MKT_EMAIL_DASHBOARD_URL=https://waapi.lebytek.com/portal/acceso' >> "$APP_DIR/.env"
grep -q '^MKT_EMAIL_DOCS_URL=' "$APP_DIR/.env" \
  || echo 'MKT_EMAIL_DOCS_URL=https://docs.lebytek.com' >> "$APP_DIR/.env"
grep -q '^LEBYTEK_API_URL=' "$APP_DIR/.env" \
  || echo 'LEBYTEK_API_URL=https://api.lebytek.com/api/v1' >> "$APP_DIR/.env"
sed -i "s/'marketing'      => false/'marketing'      => true/" "$APP_DIR/config/vertical.php" || true

echo "==> composer install"
cd "$APP_DIR"
sudo -u lebytek-waapi composer install --no-dev --optimize-autoloader --no-interaction
sudo -u lebytek-waapi mkdir -p storage/logs storage/cache storage/uploads storage/temp storage/exports storage/imports public/uploads
sudo -u lebytek-waapi chmod -R ug+rwX storage public/uploads 2>/dev/null || true

CONF=/etc/nginx/sites-enabled/waapi.lebytek.com.conf
if grep -q 'skeleton/public' "$CONF"; then
  sed -i 's|/home/lebytek-waapi/htdocs/waapi.lebytek.com/skeleton/public|/home/lebytek-waapi/htdocs/waapi.lebytek.com/public|g' "$CONF"
  nginx -t
  systemctl reload nginx
  echo "nginx reloaded"
fi

curl -sfI https://127.0.0.1/portal/acceso -H 'Host: waapi.lebytek.com' -k | head -1 || true
curl -sfI https://127.0.0.1/ -H 'Host: waapi.lebytek.com' -k | head -1 || true
echo "WAAPI_DEPLOY_DONE"
