#!/usr/bin/env bash
set -euo pipefail

CONF="/etc/nginx/sites-enabled/lebytek.com.conf"

python3 <<'PY'
from pathlib import Path
p = Path("/etc/nginx/sites-enabled/lebytek.com.conf")
text = p.read_text()
old = """  set $redirect_to_https "";
  if ($scheme != "https") {
    set $redirect_to_https "1";
  }
  if ($skip_https_redirect) {
    set $redirect_to_https "";
  }
  if ($redirect_to_https = "1") {
    return 301 https://$host$request_uri;
  }"""
new = """  if ($scheme != "https") {
    rewrite ^ https://$host$request_uri permanent;
  }"""
if old not in text:
    raise SystemExit("redirect block not found")
p.write_text(text.replace(old, new, 1))
print("nginx redirect restored")
PY

rm -f /etc/nginx/conf.d/lebytek-skip-https-redirect-map.conf

cp /etc/letsencrypt/live/lebytek.com/fullchain.pem /etc/nginx/ssl-certificates/lebytek.com.crt
cp /etc/letsencrypt/live/lebytek.com/privkey.pem /etc/nginx/ssl-certificates/lebytek.com.key
chmod 644 /etc/nginx/ssl-certificates/lebytek.com.crt
chmod 600 /etc/nginx/ssl-certificates/lebytek.com.key

nginx -t
systemctl start nginx

openssl x509 -in /etc/nginx/ssl-certificates/lebytek.com.crt -noout -issuer -dates
curl -sI https://lebytek.com/ | head -5
