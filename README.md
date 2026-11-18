# Portal PQRS multiempresa

Plataforma para gestionar **peticiones, quejas, reclamos, sugerencias y felicitaciones** de varias empresas desde una sola instalación. Cada empresa tiene su propia base de datos, dominio, diseño y formulario.

Construida con Laravel 13, Inertia + React, Tailwind y [stancl/tenancy](https://tenancyforlaravel.com) (una base de datos por empresa).

## Cómo está organizada

| Parte               | URL (desarrollo)                        | Quién la usa                                   |
| ------------------- | --------------------------------------- | ---------------------------------------------- |
| Panel central       | `http://localhost:8000`                 | Super administrador: crea y configura empresas |
| Portal público      | `http://<empresa>.localhost:8000`       | Ciudadanos: radican y consultan PQRS           |
| Panel de la empresa | `http://<empresa>.localhost:8000/admin` | Administrador de empresa y agentes             |

### Panel central

- **Dashboard** con PQRS consolidadas de todas las empresas (abiertas, vencidas, por mes y por tipo).
- **Empresas**: crear (el super administrador define el **nombre de su base de datos** y se crea su usuario «Administrador de empresa»), suspender, eliminar.
- **Diseño del portal** por empresa: plantilla (corporativo, moderno, minimalista o **salud**), colores, logo, tipografía (Circe Rounded es la institucional) y textos, con vista previa en vivo. Todos los portales llevan el sello «Vigilado Supersalud» en el pie.
- **Colores del panel administrativo** por empresa (menú lateral y botones). El panel central usa el vino y rojo de la plataforma.
- **Formulario PQRS** por empresa: tipos habilitados, nombre y plazo (días hábiles) de cada tipo, datos obligatorios, adjuntos y campos personalizados (texto, lista, opción única, fecha, casilla, etc.).
- **Reclamos por nivel de riesgo** (opcional, Supersalud): el ciudadano elige riesgo simple, priorizado o vital; los de riesgo vital salen primero en el resumen de la empresa.

### Portal público de cada empresa

- Radicación con el formulario propio de la empresa → número de radicado (`PREFIJO-AÑO-00001`) y correo de confirmación. Si la empresa tiene sedes, el ciudadano elige a cuál dirige su solicitud.
- Consulta de estado con radicado + correo; muestra respuestas y adjuntos de respuesta.

### Panel de la empresa

- Resumen: abiertas, por vencer, vencidas, % respondidas a tiempo y **totales por sede**.
- Bandeja con búsqueda y filtros (sede, **Mis PQRS**, "sin responsable"…); detalle con historial, notas internas y respuestas al ciudadano (envía correo y cambia el estado a _Respondida_).
- **Sedes** (ej. Neiva, Yopal, Bogotá): cada PQRS pertenece a una. Se desactivan en lugar de borrarse.
- **Usuarios**: crear agentes, asignarles sedes (se pueden cambiar en cualquier momento), desactivar (cierra sus sesiones).
- **Asignación**: cada PQRS puede tener un responsable que tenga acceso a su sede; se le avisa por correo y queda en el historial interno.
- **Traslado de sede**: si el paciente se equivocó de sede, se traslada la PQRS (queda en el historial interno).

### Roles

| Rol                          | Dónde               | Qué puede hacer                                                                                                                                                                      |
| ---------------------------- | ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Super administrador**      | Panel central       | Crea y gestiona las empresas, define el nombre de su base de datos y crea su administrador inicial.                                                                                  |
| **Administrador de empresa** | Panel de la empresa | Toda la operación: sedes, usuarios, sedes de cada agente, todas las PQRS y reportes de todas las sedes.                                                                              |
| **Agente**                   | Panel de la empresa | Solo las PQRS de sus sedes: verlas, gestionarlas, responder, notas internas y trasladarlas a otra sede. Toma las que no tienen responsable o suelta las suyas. Las demás le dan 404. |

Las PQRS sin sede (radicadas antes de crear sedes) solo las ve el administrador de empresa hasta que les asigne una. Si a un agente le quitan una sede, sus PQRS abiertas de esa sede quedan sin responsable.

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

> La tipografía **Circe Rounded** tiene licencia comercial y no está en el repositorio: copia `CirceRounded-Medium.otf` y `CirceRounded-Bold.otf` en `resources/fonts/` antes de compilar (ver `resources/fonts/LEEME.md`). Sin ellas el proyecto funciona con la fuente del sistema.

Si ya tenías empresas creadas, después de actualizar ejecuta `php artisan tenants:migrate` para agregar a sus bases de datos lo nuevo (contador de radicados, roles, sedes). Los antiguos «funcionarios» pasan a ser «agentes» sin sedes: asígnaselas en Usuarios.

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
