#!/usr/bin/env bash
# Lingkungan lokal /event (tanpa docker compose). Struktur folder meniru Hostinger:
#   /var/www/site/public_html/event  <- public/event (repo)
#   /var/www/site/event-config.php   <- dev/event/event-config.php
#   /var/www/site/event-uploads/     <- volume docker
# Buka: http://localhost:8090/event
set -euo pipefail
cd "$(dirname "$0")"
REPO="$(cd ../.. && pwd)"
docker network inspect kalcer-event >/dev/null 2>&1 || docker network create kalcer-event >/dev/null
docker build --network host -t kalcer-event-web . >/dev/null
docker rm -f kalcer-event-db kalcer-event-web >/dev/null 2>&1 || true
docker run -d --name kalcer-event-db --network kalcer-event --network-alias db \
  -e MARIADB_DATABASE=kalcer_event -e MARIADB_USER=kalcer -e MARIADB_PASSWORD=devpass -e MARIADB_ROOT_PASSWORD=devroot \
  -v kalcer-event-db:/var/lib/mysql mariadb:11 >/dev/null
docker run -d --name kalcer-event-web --network kalcer-event -p 127.0.0.1:8090:80 \
  -v "$REPO/public/event:/var/www/site/public_html/event" \
  -v "$PWD/event-config.php:/var/www/site/event-config.php:ro" \
  -v kalcer-event-uploads:/var/www/site/event-uploads \
  -v "$REPO/public/logo.png:/var/www/site/public_html/logo.png:ro" \
  -v "$REPO/public/logo-kalcer.png:/var/www/site/public_html/logo-kalcer.png:ro" \
  -v "$REPO/public/favicon.ico:/var/www/site/public_html/favicon.ico:ro" \
  -v "$REPO/public/favicon-64.png:/var/www/site/public_html/favicon-64.png:ro" \
  kalcer-event-web >/dev/null
docker exec kalcer-event-web sh -c 'mkdir -p /var/www/site/event-uploads && chown www-data /var/www/site/event-uploads'
echo "http://localhost:8090/event"
