# Portal PQRS multiempresa

Plataforma para gestionar **peticiones, quejas, reclamos, sugerencias y felicitaciones** de varias empresas desde una sola instalación. Cada empresa tiene su propia base de datos, dominio, diseño y formulario.

Construida con Laravel 13, Inertia + React, Tailwind y [stancl/tenancy](https://tenancyforlaravel.com) (una base de datos por empresa).

## Cómo está organizada

| Parte               | URL (desarrollo)                        | Quién la usa                                   |
| ------------------- | --------------------------------------- | ---------------------------------------------- |
| Panel central       | `http://localhost:8000`                 | Super administrador: crea y configura empresas |
| Portal público      | `http://<empresa>.localhost:8000`       | Ciudadanos: radican y consultan PQRS           |
| Panel de la empresa | `http://<empresa>.localhost:8000/admin` | Funcionarios de la empresa: responden PQRS     |

### Panel central

- **Dashboard** con PQRS consolidadas de todas las empresas (abiertas, vencidas, por mes y por tipo).
- **Empresas**: crear (se crea su base de datos y su usuario administrador), suspender, eliminar.
- **Diseño del portal** por empresa: plantilla (corporativo, moderno, minimalista), colores, logo, tipografía y textos, con vista previa en vivo.
- **Formulario PQRS** por empresa: tipos habilitados, nombre y plazo (días hábiles) de cada tipo, datos obligatorios, adjuntos y campos personalizados (texto, lista, opción única, fecha, casilla, etc.).

### Portal público de cada empresa

- Radicación con el formulario propio de la empresa → número de radicado (`PREFIJO-AÑO-00001`) y correo de confirmación.
- Consulta de estado con radicado + correo; muestra respuestas y adjuntos de respuesta.

### Panel de la empresa

- Resumen: abiertas, por vencer, vencidas, % respondidas a tiempo.
- Bandeja con búsqueda y filtros (incluye **Mis PQRS** y "sin responsable"); detalle con historial, notas internas y respuestas al ciudadano (envía correo y cambia el estado a _Respondida_).
- **Usuarios** (solo administradores): crear funcionarios, cambiar rol, desactivar (cierra sus sesiones). Roles: _administrador_ (gestiona usuarios y asigna a cualquiera) y _funcionario_ (puede tomar las PQRS sin responsable o soltar las suyas).
- **Asignación**: cada PQRS puede tener un responsable; se le avisa por correo y queda en el historial interno.

### Reglas de estado

| Desde      | Puede pasar a (a mano)                      |
| ---------- | ------------------------------------------- |
| Radicada   | En trámite · Cerrada (pide motivo)          |
| En trámite | Cerrada (pide motivo)                       |
| Respondida | Cerrada · En trámite (reabrir, pide motivo) |
| Cerrada    | En trámite (reabrir, pide motivo)           |

_Respondida_ solo se alcanza enviando una respuesta (así siempre queda la fecha y se notifica al ciudadano). Una PQRS cerrada no se puede responder sin reabrirla. Todos los cambios, incluidos los automáticos, quedan en el historial.

### Plazos

El vencimiento se cuenta en días hábiles desde el día siguiente a la radicación, sin sábados, domingos ni **festivos de Colombia, que se calculan solos** para cualquier año (`app/Support/ColombianHolidays.php`). Días extra se agregan en `config/pqrs.php`.

## Instalación local

```bash
composer install
npm install
cp .env.example .env   # si no existe
php artisan key:generate
php artisan migrate
php artisan db:seed     # admin central + 3 empresas de ejemplo
php artisan storage:link
npm run build           # o "npm run dev" mientras desarrollas
php artisan serve --host=localhost --port=8000
php artisan queue:work   # en otra terminal: envía los correos (van por cola)
```

> Requiere **PHP 8.4** (Laravel 13 y Pest 5 lo exigen).

Si ya tenías empresas creadas, después de actualizar ejecuta `php artisan tenants:migrate` para agregar el contador de radicados y los roles de usuario a sus bases de datos.

Abre **http://localhost:8000**. El panel central también responde en `http://127.0.0.1:8000` (la dirección que imprime `php artisan serve`), porque los dos están en `CENTRAL_DOMAINS`. Cualquier otro dominio que no sea de una empresa registrada devuelve **404**.

Los portales de las empresas solo funcionan con `<empresa>.localhost`. Los navegadores resuelven `*.localhost` automáticamente, así que los portales de ejemplo funcionan sin tocar el archivo `hosts`:

- http://andina.localhost:8000 — diseño corporativo
- http://santalucia.localhost:8000 — diseño moderno
- http://aguasdelvalle.localhost:8000 — diseño minimalista

Las credenciales de prueba están documentadas en `database/seeders/DatabaseSeeder.php`. Solo son para desarrollo.

## Arquitectura multitenant

- **Base central** (`database/database.sqlite`): `users` (super administradores), `tenants` (empresas: datos, diseño y formulario en JSON), `domains`, caché y colas.
- **Base por empresa** (`database/tenant_<id>`): `users` (funcionarios), `sessions`, `pqrs`, `pqrs_activities`, `pqrs_attachments`. Migraciones en `database/migrations/tenant`.
- La empresa se identifica por **dominio** (`routes/tenant.php`). Las rutas centrales (`routes/web.php`, Fortify) responden **solo** en `CENTRAL_DOMAINS`; en el dominio de una empresa devuelven 404.
- Las sesiones se guardan en la base de cada empresa, así una cookie de sesión nunca sirve en otra empresa.
- Los adjuntos se guardan en `storage/tenant<id>/app/...` (privado) y se descargan solo a través de rutas autorizadas.

Archivos clave:

| Archivo                                            | Para qué                                                   |
| -------------------------------------------------- | ---------------------------------------------------------- |
| `app/Models/Tenant.php`                            | Modelo de empresa, diseño y formulario resueltos           |
| `app/Support/TenantDefaults.php`                   | Valores por defecto de diseño y formulario                 |
| `app/Services/TenantProvisioner.php`               | Alta de una empresa (BD, dominio, admin)                   |
| `app/Services/PqrsFormRules.php`                   | Validación dinámica según el formulario de cada empresa    |
| `app/Services/PqrsService.php`                     | Radicación, respuestas, cambios de estado y notificaciones |
| `resources/js/components/portal/portal-shell.tsx`  | Las 3 plantillas visuales del portal                       |
| `resources/js/components/tenants/form-builder.tsx` | Constructor de formularios                                 |
| `config/pqrs.php`                                  | Festivos y reglas de adjuntos                              |

## Comandos útiles

```bash
php artisan test                 # pruebas (incluye tests/Feature/Tenancy)
php artisan tenants:migrate      # migrar las bases de todas las empresas
php artisan tenants:list         # listar empresas
```

## Antes de pasar a producción

1. **Dominio**: define `APP_URL` y `CENTRAL_DOMAINS` (ej. `pqrs.miempresa.com`). Las empresas usarán `<subdominio>.pqrs.miempresa.com`; configura un DNS comodín (`*.pqrs.miempresa.com`) y un certificado SSL comodín.
2. **Base de datos**: cambia a MySQL/PostgreSQL. El usuario de la BD necesita permiso para crear bases de datos (una por empresa).
3. **Correo**: configura SMTP (`MAIL_*`). Hoy los correos se escriben en `storage/logs/laravel.log`.
4. **Colas**: activa `shouldBeQueued(true)` en `app/Providers/TenancyServiceProvider.php` y ejecuta un worker (`php artisan queue:work`).
5. **Festivos**: los de Colombia ya se calculan solos; agrega en `config/pqrs.php` solo días extra (p. ej. un día cívico decretado).
6. **Credenciales**: cambia o elimina los usuarios de ejemplo del seeder.
