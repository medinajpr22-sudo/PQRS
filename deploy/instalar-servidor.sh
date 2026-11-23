#!/usr/bin/env bash
# Instala o actualiza Portal PQRS EN EL SERVIDOR.
#   sudo bash instalar-servidor.sh /tmp/app-pqrs-AAAAMMDD-HHMM.tar.gz
# Variables opcionales: APP_DIR (por defecto /var/www/html/app-pqrs), WEB_USER (www-data).
set -euo pipefail

PKG="${1:?Uso: sudo bash instalar-servidor.sh /ruta/app-pqrs-XXXX.tar.gz}"
APP_DIR="${APP_DIR:-/var/www/html/app-pqrs}"
WEB_USER="${WEB_USER:-www-data}"
PHP="${PHP:-php}"

echo "==> Comprobando requisitos…"
$PHP -r 'exit(version_compare(PHP_VERSION,"8.4.0",">=")?0:1);' || { echo "ERROR: se requiere PHP >= 8.4 (hay $($PHP -r 'echo PHP_VERSION;'))"; exit 1; }
for ext in pdo_pgsql mbstring xml curl zip bcmath intl gd fileinfo openssl tokenizer ctype; do
  $PHP -m | grep -qi "^$ext$" || echo "  AVISO: falta la extensión PHP '$ext' (apt install php8.4-${ext/pdo_/})"
done
command -v composer >/dev/null || { echo "ERROR: instala composer (https://getcomposer.org/download/)"; exit 1; }

FIRST_INSTALL=0
[ -f "$APP_DIR/.env" ] || FIRST_INSTALL=1

if [ -f "$APP_DIR/artisan" ]; then
  echo "==> Modo mantenimiento…"
  (cd "$APP_DIR" && sudo -u "$WEB_USER" $PHP artisan down --retry=30 || true)
fi

echo "==> Extrayendo en $APP_DIR…"
mkdir -p "$APP_DIR"
tar -xzf "$PKG" -C "$APP_DIR"
cd "$APP_DIR"

if [ "$FIRST_INSTALL" = 1 ]; then
  cp deploy/env.production.example .env
  echo
  echo "******************************************************************"
  echo " Primera instalación: se creó $APP_DIR/.env desde la plantilla."
  echo " Edítalo (dominio, BD, correo, PQRS_SUPER_ADMIN_*) y vuelve a"
  echo " ejecutar este mismo comando."
  echo "******************************************************************"
  chown "$WEB_USER":"$WEB_USER" .env; chmod 640 .env
  exit 0
fi

echo "==> Dependencias PHP (sin desarrollo)…"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

grep -q '^APP_KEY=base64:' .env || $PHP artisan key:generate --force

echo "==> Permisos…"
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public bootstrap/cache
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
chown "$WEB_USER":"$WEB_USER" .env; chmod 640 .env

echo "==> Base de datos…"
sudo -u "$WEB_USER" $PHP artisan migrate --force
sudo -u "$WEB_USER" $PHP artisan tenants:migrate || echo "  (sin empresas aún)"
if [ ! -f storage/.seeded ]; then
  sudo -u "$WEB_USER" $PHP artisan db:seed --force && touch storage/.seeded
fi

echo "==> Optimizando…"
[ -L public/storage ] || sudo -u "$WEB_USER" $PHP artisan storage:link
sudo -u "$WEB_USER" $PHP artisan optimize:clear
sudo -u "$WEB_USER" $PHP artisan optimize        # config, rutas, eventos y vistas en caché

echo "==> Recargando PHP-FPM (OPcache)…"
systemctl reload php8.4-fpm 2>/dev/null || true

echo "==> Reiniciando worker de colas…"
sudo -u "$WEB_USER" $PHP artisan queue:restart
systemctl is-active --quiet pqrs-worker 2>/dev/null && systemctl restart pqrs-worker || \
  echo "  AVISO: no está el servicio pqrs-worker. Instálalo (ver deploy/DESPLIEGUE.md) o los correos no saldrán."

sudo -u "$WEB_USER" $PHP artisan up
echo "==> Despliegue terminado."
