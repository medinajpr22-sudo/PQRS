# Enviar los correos con Microsoft 365

La plataforma envía los correos (acuses de recibo, respuestas y avisos al
equipo) con **Microsoft Graph**. La app se identifica con OAuth: un id de
app y un secreto creados en Microsoft Entra. **No usa la contraseña del
buzón.**

Por qué no SMTP con contraseña: Microsoft la **desactiva por defecto a
finales de diciembre de 2026** y luego la retirará del todo.

Lo hace un administrador de Microsoft 365 de la empresa, una sola vez
(unos 20 minutos).

## 0. Lo que necesitas

- **Un buzón para PQRS**, por ejemplo `pqrs@tuempresa.com`. Puede ser un
  **buzón compartido**: no necesita licencia. Las respuestas que manden los
  ciudadanos llegan al correo de contacto de cada empresa, no a este buzón.
- **Una persona con estos roles:**
    - _Administrador de aplicaciones_, en Microsoft Entra.
    - _Administrador de Exchange_ (grupo _Organization Management_), para el
      paso 4.

## 1. Registrar la app en Microsoft Entra

1. Entra a <https://entra.microsoft.com> y busca **Registros de aplicaciones**
   en la barra superior. Luego **Nuevo registro**.
2. Llena el registro:
    - **Nombre:** `Plataforma PQRS – envío de correos`.
    - **Tipos de cuenta:** _Solo cuentas de este directorio organizativo_.
    - **URI de redirección:** déjala vacía.
3. Pulsa **Registrar**.
4. En la página de la app, copia:
    - **Id. de aplicación (cliente)** → `MICROSOFT_GRAPH_CLIENT_ID`
    - **Id. de directorio (inquilino)** → `MICROSOFT_GRAPH_TENANT_ID`

## 2. Crear el secreto

1. En la app, entra a **Certificados y secretos** → **Nuevo secreto de
   cliente**.
2. Ponle una descripción y un vencimiento (máximo 24 meses). **Anota la
   fecha en que vence** (ver «Mantenimiento»).
3. Copia la columna **Valor**, no el «Id. de secreto». Va en
   `MICROSOFT_GRAPH_CLIENT_SECRET`. Solo se ve una vez: si cierras la
   página, crea otro.

## 3. No dar permisos en «Permisos de API»

**No** agregues `Mail.Send` en _Permisos de API_ de Entra. Ese permiso deja
a la app enviar correos **como cualquier persona de la empresa**: el
gerente, contabilidad, todos.

El permiso se da en el paso 4, limitado al buzón de PQRS. Si ya lo
agregaste, quítalo y revoca el consentimiento del administrador. Los dos
permisos se suman: si queda el de Entra, el límite del paso 4 no sirve de
nada.

## 4. Permitir enviar solo desde el buzón de PQRS

Primero busca dos datos en Entra. Ve a **Aplicaciones empresariales**, no a
_Registros de aplicaciones_ (muestra otros valores), y busca la app. Copia:

- **Id. de aplicación**
- **Id. de objeto**

Luego, en PowerShell, como administrador de Exchange:

```powershell
# Solo la primera vez en ese computador:
Install-Module ExchangeOnlineManagement

Connect-ExchangeOnline -UserPrincipalName admin@tuempresa.com

# 1. Le presenta la app a Exchange.
New-ServicePrincipal -AppId "<Id. de aplicación>" -ObjectId "<Id. de objeto>" -DisplayName "Plataforma PQRS"

# 2. Un grupo con un solo buzón: el de PQRS.
New-ManagementScope -Name "Buzón PQRS" -RecipientRestrictionFilter "PrimarySmtpAddress -eq 'pqrs@tuempresa.com'"

# 3. Le da a la app permiso de enviar, solo dentro de ese grupo.
New-ManagementRoleAssignment -App "<Id. de objeto>" -Role "Application Mail.Send" -CustomResourceScope "Buzón PQRS"

# Comprobación: InScope debe decir True.
Test-ServicePrincipalAuthorization -Identity "<Id. de objeto>" -Resource pqrs@tuempresa.com
```

Para ver que el límite funciona, cambia `-Resource` por otro buzón de la
empresa: `InScope` debe decir **False**.

Microsoft tarda **entre 30 minutos y 2 horas** en aplicar los permisos.
Mientras tanto, la prueba del paso 5 puede responder `ErrorAccessDenied`.

## 5. Configurar la plataforma

En el `.env` del servidor:

```dotenv
MAIL_MAILER=microsoft-graph
MAIL_FROM_ADDRESS="pqrs@tuempresa.com"
MAIL_FROM_NAME="${APP_NAME}"

MICROSOFT_GRAPH_TENANT_ID=<Id. de directorio (inquilino)>
MICROSOFT_GRAPH_CLIENT_ID=<Id. de aplicación (cliente)>
MICROSOFT_GRAPH_CLIENT_SECRET=<Valor del secreto>
```

Con Microsoft Graph no se usan `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME` ni
`MAIL_PASSWORD`.

Después:

1. Si usas `php artisan config:cache`, vuelve a correrlo.
2. Prueba el envío:

    ```bash
    php artisan pqrs:probar-correo tucorreo@gmail.com
    ```

    El comando muestra la configuración (nunca el secreto). Si falla, dice
    qué hacer.

3. Reinicia la cola para que tome la configuración nueva:
   `php artisan queue:restart`, o detén y vuelve a iniciar
   `php artisan queue:work`.

Los correos enviados quedan en **Elementos enviados** del buzón de PQRS.

## 6. Que Gmail no los mande a spam (DNS del dominio)

Gmail y Outlook revisan que el dominio del remitente autorice a Microsoft a
enviar por él. En el DNS de `tuempresa.com` deben estar estos tres
registros:

- **SPF:** registro TXT en `@` con
  `v=spf1 include:spf.protection.outlook.com -all`.
    - Microsoft 365 suele crearlo al agregar el dominio.
    - Si otro servicio también envía por el dominio, se agrega en el mismo
      registro: solo puede haber uno.
- **DKIM:** en <https://security.microsoft.com>, ve a _Correo electrónico y
  colaboración_ → _Directivas y reglas_ → _Directivas de amenazas_ →
  _Configuración de autenticación de correo_ → **DKIM**. Elige el dominio:
    - Microsoft muestra dos registros CNAME (`selector1` y `selector2`) para
      crear en el DNS.
    - Después, activa **Firmar mensajes para este dominio con firmas DKIM**.
- **DMARC:** registro TXT en `_dmarc` con
  `v=DMARC1; p=none; rua=mailto:dmarc@tuempresa.com`.
    - Pasadas unas semanas sin problemas, se puede subir a `p=quarantine`.

## Mantenimiento

- **Vencimiento del secreto:** cuando vence, los correos dejan de salir. La
  prueba muestra el error `AADSTS7000222`. Antes de esa fecha:
    1. Crea un secreto nuevo (paso 2).
    2. Cámbialo en el `.env`.
    3. Reinicia la cola.
    4. Borra el secreto viejo en Entra.
- **Límites de Microsoft:** 30 correos por minuto y 10.000 destinatarios al
  día. La plataforma envía máximo 20 por minuto (`PQRS_MAIL_PER_MINUTE`).
  Los demás esperan en la cola.
- **Adjuntos:** Microsoft Graph acepta correos de hasta 4 MB.
    - Las respuestas adjuntan archivos de hasta 1,8 MB en total, contando el
      logo de la empresa (`PQRS_MAIL_MAX_ATTACHMENTS_KB`). Los archivos viajan
      codificados y ocupan casi el doble.
    - Si pesan más, el correo le dice al ciudadano que los descargue al
      consultar su radicado.

## Errores comunes

| Error                                              | Qué hacer                                                                                                                        |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `AADSTS7000215`                                    | En `MICROSOFT_GRAPH_CLIENT_SECRET` va el **Valor** del secreto, no su «Id. de secreto».                                          |
| `AADSTS7000222`                                    | El secreto venció: crea uno nuevo (ver «Mantenimiento»).                                                                         |
| `AADSTS700016`                                     | Revisa `MICROSOFT_GRAPH_CLIENT_ID`.                                                                                              |
| `AADSTS90002` o `AADSTS900023`                     | Revisa `MICROSOFT_GRAPH_TENANT_ID`.                                                                                              |
| `ErrorAccessDenied`                                | Falta el paso 4, el buzón no está en el alcance, o no han pasado las 2 horas. Revísalo con `Test-ServicePrincipalAuthorization`. |
| `ErrorInvalidUser` o `MailboxNotEnabledForRESTAPI` | El buzón de `MAIL_FROM_ADDRESS` no existe en Microsoft 365 o no tiene Exchange.                                                  |
| `429` o `ApplicationThrottled`                     | Microsoft frenó los envíos por exceso. La cola los reintenta sola; si pasa seguido, baja `PQRS_MAIL_PER_MINUTE`.                 |
