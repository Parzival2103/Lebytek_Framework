#!/usr/bin/env bash
# Despliegue del skeleton Lebytek en VPS (Ubuntu).
# Uso:
#   export APP_DIR=/var/www/tudominio
#   export REPO_URL=https://github.com/Parzival2103/Lebytek_Framework.git
#   bash vps-deploy-skeleton.sh
#
# Requisitos: git, composer, php8.1+ (cli + fpm), mysql/mariadb, nginx o apache.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/lebytek-app}"
REPO_URL="${REPO_URL:-https://github.com/Parzival2103/Lebytek_Framework.git}"
BRANCH="${BRANCH:-main}"
DOMAIN="${DOMAIN:-}"  # opcional, para mensaje final

echo "==> APP_DIR=$APP_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php no instalado. Ej: apt install php-cli php-fpm php-mysql php-mbstring php-xml php-curl"
  exit 1
fi
if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: composer no instalado. Ver https://getcomposer.org/download/"
  exit 1
fi

mkdir -p "$(dirname "$APP_DIR")"

if [[ -d "$APP_DIR/.git" ]]; then
  echo "==> Actualizando repo en $APP_DIR"
  git -C "$APP_DIR" fetch origin
  git -C "$APP_DIR" checkout "$BRANCH"
  git -C "$APP_DIR" pull origin "$BRANCH"
else
  echo "==> Clonando $REPO_URL -> $APP_DIR"
  git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi

SKELETON="$APP_DIR/skeleton"
if [[ ! -f "$SKELETON/public/index.php" ]]; then
  echo "ERROR: no existe $SKELETON/public/index.php — ¿pusheaste main con el carve a GitHub?"
  exit 1
fi

cd "$SKELETON"

# Monorepo: consumir el paquete desde el directorio padre (sin Packagist).
composer config repositories.local path ../
composer install --no-dev --optimize-autoloader --no-interaction

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "==> Creado .env desde .env.example — EDITA credenciales antes de producción:"
  echo "    nano $SKELETON/.env"
fi

mkdir -p storage/logs storage/cache storage/uploads storage/temp storage/exports storage/imports
chmod -R ug+rwX storage public/uploads 2>/dev/null || true

echo ""
echo "==> Skeleton listo en: $SKELETON"
echo "    Document root del vhost debe apuntar a: $SKELETON/public"
echo ""
echo "Siguiente:"
echo "  1. Editar .env (APP_URL, DB_*, APP_ENV=production, APP_DEBUG=false, SESSION_SECURE=true)"
echo "  2. Instalar BD: php $APP_DIR/scripts/install.php  (desde repo) o wizard: /install/"
echo "  3. Nginx/Apache: root -> $SKELETON/public"
if [[ -n "$DOMAIN" ]]; then
  echo "  4. Probar: https://$DOMAIN/login"
fi
