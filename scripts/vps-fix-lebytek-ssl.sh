#!/usr/bin/env bash
# Issue Let's Encrypt for lebytek.com via certbot standalone (brief nginx stop).
set -euo pipefail

WEBROOT="/home/lebytek/htdocs/lebytek.com/public"
EMAIL="${LEBYTEK_SSL_EMAIL:-admin@lebytek.com}"

if ! command -v certbot >/dev/null 2>&1; then
  apt-get update -qq && apt-get install -y -qq certbot
fi

systemctl stop nginx

certbot certonly --standalone \
  -d lebytek.com -d www.lebytek.com \
  --non-interactive --agree-tos -m "$EMAIL" \
  --preferred-challenges http \
  --force-renewal

systemctl start nginx

clpctl site:install:certificate \
  --domainName=lebytek.com \
  --privateKey="/etc/letsencrypt/live/lebytek.com/privkey.pem" \
  --certificate="/etc/letsencrypt/live/lebytek.com/cert.pem" \
  --certificateChain="/etc/letsencrypt/live/lebytek.com/fullchain.pem"

systemctl reload nginx
openssl x509 -in /etc/nginx/ssl-certificates/lebytek.com.crt -noout -issuer -dates
