# Despliegue de Portal PQRS en producción

La app queda en `/var/www/html/app-pqrs` y el servidor web sirve **solo** `/var/www/html/app-pqrs/public`.

> ⚠️ No descomprimas el proyecto directamente en `/var/www/html` con el DocumentRoot por defecto: el `.env` (contraseñas de BD, secretos de Microsoft Graph, APP_KEY) quedaría descargable desde el navegador.

## 0. Requisitos del servidor (una sola vez)

```bash
# PHP 8.4 + extensiones (Ubuntu/Debian; en Ubuntu < 24.10 agrega antes ppa:ondrej/php)
sudo apt install php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd unzip
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# PostgreSQL: usuario con permiso para crear bases (una por empresa)
sudo -u postgres psql -c "CREATE USER pqrs WITH PASSWORD 'CAMBIAR' CREATEDB;"
sudo -u postgres psql -c "CREATE DATABASE pqrs OWNER pqrs;"
```

Ajustes de PHP: copia `deploy/php-produccion.ini` a `/etc/php/8.4/fpm/conf.d/99-pqrs.ini` y `sudo systemctl restart php8.4-fpm`.

**DNS y SSL**: registros `A` para `pqrs.tudominio.com` y `*.pqrs.tudominio.com`, y un certificado comodín. Con Let's Encrypt el comodín exige validación DNS:
```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d pqrs.tudominio.com -d '*.pqrs.tudominio.com'
```

## 1. En tu PC: empaquetar

```bash
cd ~/Documentos/app-pqrs
bash deploy/empaquetar.sh
scp dist-deploy/app-pqrs-*.tar.gz usuario@servidor:/tmp/
scp deploy/instalar-servidor.sh usuario@servidor:/tmp/
```

El paquete lleva los assets ya compilados (incluida la fuente Circe Rounded dentro de `public/build`), sin `vendor`, `node_modules`, `.env`, `.git` ni los datos locales de empresas (`storage/tenant*`).

## 2. En el servidor: primera instalación

```bash
sudo bash /tmp/instalar-servidor.sh /tmp/app-pqrs-XXXXXXXX-XXXX.tar.gz
# → crea /var/www/html/app-pqrs/.env desde la plantilla y se detiene
sudo nano /var/www/html/app-pqrs/.env      # dominio, BD, correo, PQRS_SUPER_ADMIN_*, PASSKEYS_USER_HANDLE_SECRET
sudo bash /tmp/instalar-servidor.sh /tmp/app-pqrs-XXXXXXXX-XXXX.tar.gz   # ahora sí instala
```

Genera el secreto de passkeys con `openssl rand -base64 32` y **no lo cambies después**.

### Servidor web (elige uno)

**Apache** (lo habitual con `/var/www/html`):
```bash
sudo cp /var/www/html/app-pqrs/deploy/apache-pqrs.conf /etc/apache2/sites-available/pqrs.conf
sudo nano /etc/apache2/sites-available/pqrs.conf          # pon tu dominio
sudo a2enmod rewrite headers ssl proxy_fcgi setenvif && sudo a2enconf php8.4-fpm
sudo a2ensite pqrs && sudo apache2ctl configtest && sudo systemctl reload apache2
```

**nginx**: `deploy/nginx-pqrs.conf` → `/etc/nginx/sites-available/pqrs`, enlázalo en `sites-enabled`, `nginx -t`, recarga.

### Worker de colas (sin esto no salen los correos)
```bash
sudo cp /var/www/html/app-pqrs/deploy/pqrs-worker.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now pqrs-worker
sudo systemctl status pqrs-worker
```

## 3. Verificar

```bash
cd /var/www/html/app-pqrs
sudo -u www-data php artisan about                       # Environment: production, Debug: OFF
sudo -u www-data php artisan pqrs:probar-correo tu@correo.com
curl -I https://pqrs.tudominio.com/up                    # 200
```
Entra a `https://pqrs.tudominio.com`, inicia sesión con el super administrador y crea una empresa de prueba; su portal debe abrir en `https://<subdominio>.pqrs.tudominio.com`.

Comprueba también que `https://pqrs.tudominio.com/.env` devuelve 404/403.

## 4. Actualizaciones siguientes

```bash
# PC
bash deploy/empaquetar.sh && scp dist-deploy/app-pqrs-NUEVO.tar.gz usuario@servidor:/tmp/
# Servidor
sudo bash /var/www/html/app-pqrs/deploy/instalar-servidor.sh /tmp/app-pqrs-NUEVO.tar.gz
```
El script pone la app en mantenimiento, actualiza dependencias, migra la base central y **todas las empresas** (`tenants:migrate`), regenera cachés, recarga PHP-FPM y reinicia el worker.

## Respaldos

Cada empresa tiene su propia base `tenant_<id>` y sus adjuntos en `storage/tenant<id>/`. El respaldo debe incluir **todas** las bases y la carpeta `storage/`:
```bash
for db in $(sudo -u postgres psql -Atc "SELECT datname FROM pg_database WHERE datname='pqrs' OR datname LIKE 'tenant_%'"); do
  sudo -u postgres pg_dump -Fc "$db" > /backups/$db-$(date +%F).dump
done
tar -czf /backups/storage-$(date +%F).tar.gz -C /var/www/html/app-pqrs storage
```
