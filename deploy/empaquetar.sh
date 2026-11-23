#!/usr/bin/env bash
# Empaqueta Portal PQRS para producción. Se ejecuta EN TU PC, desde la raíz del proyecto:
#   bash deploy/empaquetar.sh
# Genera dist-deploy/app-pqrs-AAAAMMDD-HHMM.tar.gz listo para subir al servidor.
set -euo pipefail
cd "$(dirname "$0")/.."

STAMP=$(date +%Y%m%d-%H%M)
OUT="dist-deploy"
PKG="$OUT/app-pqrs-$STAMP.tar.gz"
mkdir -p "$OUT"

echo "==> Compilando assets (Vite)…"
[ -d node_modules ] || npm ci
npm run build

[ -f public/build/manifest.json ] || { echo "ERROR: no se generó public/build/manifest.json"; exit 1; }

echo "==> Empaquetando (sin vendor, node_modules, .env ni datos locales)…"
tar -czf "$PKG" \
  --exclude='./.git' --exclude='./.github' --exclude='./.claude' \
  --exclude='./node_modules' --exclude='./vendor' \
  --exclude='./.env' --exclude='./.env.backup' --exclude='./.env.production' \
  --exclude='./.phpunit.result.cache' --exclude='./psql' \
  --exclude='./tests' --exclude='./dist-deploy' \
  --exclude='./public/hot' --exclude='./public/storage' \
  --exclude='./resources/fonts/*.otf' \
  --exclude='./storage/tenant*' --exclude='./storage/inertia-devtools' \
  --exclude='./storage/logs/*.log' \
  --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' \
  --exclude='./storage/framework/cache/data/*' \
  --exclude='./bootstrap/cache/*.php' \
  --exclude='./database/*.sqlite' --exclude='./database/tenant_*' \
  .

echo "==> Listo: $PKG ($(du -h "$PKG" | cut -f1))"
echo "Súbelo al servidor, por ejemplo:"
echo "  scp $PKG usuario@servidor:/tmp/"
