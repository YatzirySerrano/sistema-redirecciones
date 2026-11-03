# Despliegue (Ubuntu + Nginx + PHP-FPM + MariaDB + HTTPS)

> Esta guía no se ha aplicado a ningún servidor; es el procedimiento previsto.

## Requisitos

- Ubuntu 22.04/24.04.
- Nginx.
- PHP 8.4-FPM con las extensiones `mysql`, `gd`, `intl`, `mbstring`, `xml`, `curl`, `zip` y `bcmath`.
- Composer 2.
- Node 22, solo para compilar los assets (también puedes compilarlos fuera del servidor).
- MariaDB 10.11 o superior.
- Certificado TLS (p. ej. Let's Encrypt).

## MariaDB

```sql
CREATE DATABASE sistema_redirecciones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sistema_redirecciones'@'localhost' IDENTIFIED BY '<contraseña fuerte>';
GRANT ALL PRIVILEGES ON sistema_redirecciones.* TO 'sistema_redirecciones'@'localhost';
```

## Variables de entorno (`.env` de producción)

```
APP_NAME="DASTI Redirecciones"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://redireccion.dasti.cloud
APP_KEY=            # php artisan key:generate (una vez; respáldala: cifra sesiones y huellas)
DB_CONNECTION=mariadb
DB_DATABASE=sistema_redirecciones
DB_USERNAME=sistema_redirecciones
DB_PASSWORD=...
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
LOG_STACK=daily
LOG_LEVEL=warning
MAIL_MAILER=smtp    # ver «Correo electrónico» más abajo
MAIL_TIMEOUT=10
# DASTI_EXIGIR_2FA_ADMIN=true   # ya es el valor por defecto en production
```

`APP_URL` (o `DASTI_URL_PUBLICA`) **no debe cambiar** después de entregar productos: es la dirección impresa en los QR y grabada en las tarjetas NFC.

## Comandos de despliegue

```bash
git pull                                        # en el directorio de la app
composer install --no-dev --optimize-autoloader
npm ci && npm run build                         # o sube public/build ya compilado
php artisan migrate --force
php artisan optimize                            # config, rutas, eventos y vistas en caché
sudo systemctl reload php8.4-fpm
```

Primer despliegue: crea el administrador inicial. Nunca uses los seeders de demostración en producción; además, se niegan a ejecutarse.

```bash
php artisan dasti:crear-administrador           # pide la contraseña de forma oculta
```

Permisos: el usuario de PHP-FPM debe poder escribir en `storage/` y `bootstrap/cache/`. Los logotipos se guardan en `storage/app/private`, que no es público.

## Nginx

```nginx
server {
    listen 80;
    server_name redireccion.dasti.cloud;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name redireccion.dasti.cloud;
    root /var/www/sistema-redirecciones/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/redireccion.dasti.cloud/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/redireccion.dasti.cloud/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000" always;
    client_max_body_size 2m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

La aplicación confía en `127.0.0.1` como proxy (`bootstrap/app.php`) para obtener la IP y el esquema reales.

## Tareas programadas y colas

Añade al cron del usuario de la aplicación:

```
* * * * * cd /var/www/sistema-redirecciones && php artisan schedule:run >> /dev/null 2>&1
```

Ejecuta `dasti:depurar-eventos` a diario (03:30): aplica la política de conservación.

Los correos (invitaciones y recuperación de contraseña) se envían **en cola** (`ShouldQueue`, 3 intentos con esperas de 1 y 5 minutos), así que en producción debe ejecutarse siempre un worker. Si los tres intentos fallan, la invitación queda marcada como «fallida» en la ficha del usuario y en la auditoría (`usuario.invitacion_fallida`), y se puede reenviar con «Enviar enlace de acceso». Los accesos a enlaces se registran tras responder (`defer()`), salvo con `DASTI_EVENTOS_EN_COLA=true`, que también usa el worker.

Servicio de systemd de ejemplo (`/etc/systemd/system/dasti-cola.service`):

```ini
[Unit]
Description=DASTI Redirecciones - cola
After=network.target mariadb.service

[Service]
User=www-data
WorkingDirectory=/var/www/sistema-redirecciones
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

Tras cada despliegue: `php artisan queue:restart`.

## Seguridad

- `APP_DEBUG=false`, HTTPS obligatorio y cookies `secure` y `httponly`.
- No hay registro público; el login tiene límite de intentos por correo e IP.
- Los administradores deben activar la verificación en dos pasos antes de usar el panel (crea el primero con `dasti:crear-administrador` y actívala en su primer acceso).
- `trustProxies` confía solo en `127.0.0.1`/`::1` (Nginx en el mismo servidor). Si se añade Cloudflare o un balanceador, ajústalo para que los límites por IP sigan funcionando.
- El SSR de Inertia no se usa en producción (no se ejecuta `inertia:start-ssr`): la interfaz se renderiza en el navegador.
- No guardes secretos en el repositorio. Respalda `.env` y `APP_KEY` fuera del servidor.
- Respalda la base a diario (`mariadb-dump --single-transaction`).

## Identidad visual pendiente

- **Logotipo del panel:** copia los archivos oficiales a `public/marca/` (`dasti-logo.svg`, `dasti-isotipo.svg` y, opcionalmente, sus versiones `-oscuro`). La interfaz los detecta sola (`App\Services\Apariencia\MarcaDasti`); mientras no existan, el sidebar y el inicio de sesión muestran un recurso neutral temporal. Ver `public/marca/LEEME.txt`.
- `public/favicon.*`, `public/apple-touch-icon.png` y `public/iconos/` son temporales y neutrales. Sustitúyelos por los oficiales.
- Los QR de los clientes y las páginas públicas de los negocios (Página de enlaces y tarjeta de Fidelidad) nunca llevan marca de DASTI.

## Verificaciones posteriores al despliegue

```bash
curl -I https://redireccion.dasti.cloud/up              # 200
curl -I https://redireccion.dasti.cloud/login           # 200 y cookies "secure"
curl -I https://redireccion.dasti.cloud/r/zzzzzzzzzz    # 404 con página «no disponible»
php artisan about --only=environment                    # production, Debug OFF
```

Después, crea un producto de prueba, comprueba que `/r/{codigo}` responde **302** hacia su destino, y suspéndelo o elimínalo.

## Correo electrónico

El proyecto no incluye credenciales de correo. Configura en el `.env` del servidor los datos que entregue tu proveedor SMTP (el de tu dominio, Google Workspace, Microsoft 365, Amazon SES, etc.):

| Variable            | Valor                                                     |
| ------------------- | --------------------------------------------------------- |
| `MAIL_MAILER`       | `smtp`                                                    |
| `MAIL_HOST`         | Servidor SMTP del proveedor (p. ej. `smtp.tudominio.com`) |
| `MAIL_PORT`         | `587` (STARTTLS) o `465` (TLS implícito)                  |
| `MAIL_SCHEME`       | vacío para 587; `smtps` para 465                          |
| `MAIL_USERNAME`     | Usuario de la cuenta SMTP                                 |
| `MAIL_PASSWORD`     | Contraseña o clave de aplicación                          |
| `MAIL_FROM_ADDRESS` | Remitente verificado, p. ej. `no-responder@dasti.cloud`   |
| `MAIL_FROM_NAME`    | `"DASTI Redirecciones"`                                   |
| `QUEUE_CONNECTION`  | `database` (con el worker activo)                         |

Después: `php artisan config:cache && php artisan queue:restart`. Para comprobarlo sin escribir a clientes, crea un acceso de prueba con tu propio correo y usa «Enviar enlace de acceso», o solicita «¿Olvidaste tu contraseña?» con tu cuenta. Configura SPF, DKIM y DMARC del dominio remitente para evitar que los mensajes lleguen a spam. Los errores de envío aparecen en `storage/logs/laravel.log` y en la tabla `failed_jobs` (`php artisan queue:failed`).

## PWA

- Requiere HTTPS (los Service Workers solo funcionan en contextos seguros).
- Servir `sw.js` sin caché para que las versiones nuevas se detecten:

```nginx
location = /sw.js { add_header Cache-Control "no-cache"; try_files $uri =404; }
location = /manifest.webmanifest { default_type application/manifest+json; }
```

- Al publicar una versión con cambios en `public/sw.js`, incrementar `VERSION` dentro del archivo.
- Sustituir los íconos temporales de `public/iconos/`, `public/favicon.*` y `public/apple-touch-icon.png` por los oficiales de DASTI.

## Pagos en línea con Openpay

La contratación de **Fidelidad** y **Página de enlaces** (pago único, MXN) usa el formulario de pago alojado por Openpay («redireccionamiento»): DASTI crea el cargo con `confirm=false`, el cliente captura su tarjeta en la página de Openpay y regresa a `/panel/servicios/pago/retorno?folio=…`. El servidor **nunca** recibe datos de tarjeta y **nunca** activa un servicio por el regreso del navegador: siempre consulta el cargo a la API de Openpay con la llave privada.

### 1. Credenciales (sandbox primero)

En el panel de Openpay (sandbox: `https://sandbox-dashboard.openpay.mx`) obtén el **ID de comercio** y la **llave privada** (Mi cuenta › Credenciales de API). Agrega al `.env`:

```
OPENPAY_MERCHANT_ID=m...
OPENPAY_PRIVATE_KEY=sk_...
OPENPAY_PUBLIC_KEY=pk_...        # no se usa con el formulario alojado; se conserva por si se adopta Openpay.js
OPENPAY_PRODUCTION=false         # true SOLO con las credenciales de producción
OPENPAY_WEBHOOK_USER=<usuario largo y aleatorio>
OPENPAY_WEBHOOK_PASSWORD=<contraseña larga y aleatoria>
# OPENPAY_WEBHOOK_IPS=           # opcional: IP/CIDR de Openpay, separados por comas
# OPENPAY_HORAS_EXPIRACION=24    # tras este tiempo un cobro no completado caduca
```

Después: `php artisan config:cache`. Nunca compartas la llave privada ni la subas al repositorio. Las credenciales de sandbox y producción son distintas; el sistema rechaza (y marca para revisión) cualquier cargo cuyo ambiente no coincida con `OPENPAY_PRODUCTION`.

### 2. Notificaciones (webhook)

1. En el panel de Openpay › Webhooks › **Agregar**, URL: `https://redireccion.dasti.cloud/openpay/notificaciones`. Activa la **autenticación HTTP Basic** con el mismo usuario y contraseña de `OPENPAY_WEBHOOK_USER` / `OPENPAY_WEBHOOK_PASSWORD`.
2. Selecciona al menos los eventos `charge.succeeded`, `charge.failed`, `charge.cancelled`, `charge.refunded`, `chargeback.created`, `chargeback.rejected` y `chargeback.accepted`.
3. Openpay envía un **código de verificación**: aparece en DASTI › Administración › Pagos (sección Openpay). Cópialo en Openpay › Webhooks › **Verificar**.

Requisitos de Openpay para el endpoint: dominio (no IP), HTTPS con certificado válido (TLS 1.2+), puerto 443. Sin usuario/contraseña configurados el endpoint responde 503 y rechaza todo.

Openpay no firma sus notificaciones: por eso, además de HTTP Basic, cada notificación se trata solo como aviso y el estado se confirma consultando `GET /v1/{merchant}/charges/{id}`. Las notificaciones repetidas o fuera de orden son idempotentes.

### 3. Conciliación programada

El cron de Laravel ejecuta `dasti:conciliar-pagos` cada 10 minutos: consulta a Openpay los cobros que siguen abiertos (por si una notificación no llegó) y marca como caducados los que superan `OPENPAY_HORAS_EXPIRACION`. Requiere la entrada de cron `schedule:run` descrita arriba.

### 4. Precios

DASTI configura el precio final (MXN, con o sin IVA incluido), la descripción y los beneficios de cada servicio en Administración › Servicios contratados › Catálogo y precios, y habilita «Permitir contratación en línea». Sin precio no se puede contratar en línea. Pago único: no existen mensualidades ni cobros recurrentes.

### 5. Pruebas en sandbox (pendientes de credenciales reales)

Con las credenciales de sandbox y el webhook verificado:

| Caso                      | Tarjeta de prueba (Openpay)                                                                    | Resultado esperado en DASTI                                 |
| ------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Pago aprobado             | `4111111111111111` (Visa) o `5555555555554444` (Mastercard), vigencia futura, CVV de 3 dígitos | Pago «Pagado» y servicio activo una sola vez                |
| Rechazada                 | `4222222222222220`                                                                             | Pago «Rechazado», servicio sin activar; se puede reintentar |
| Fondos insuficientes      | `4444444444444448`                                                                             | Igual que rechazada, con el mensaje de Openpay              |
| Tarjeta robada            | `4000000000000119`                                                                             | Igual que rechazada                                         |
| Duplicados                | Reenviar la misma notificación desde el panel de Openpay                                       | Sin activaciones ni cambios adicionales                     |
| Reembolso total / parcial | Botón «Reembolsar» en Administración › Pagos                                                   | Total: servicio suspendido; parcial: revisión de DASTI      |

La lista completa de tarjetas está en `https://documents.openpay.mx/docs/testing`. Los contracargos no se pueden provocar en sandbox desde DASTI; su manejo está cubierto por las pruebas automatizadas (`tests/Feature/Servicios/OpenpayTest.php`).

### 6. Producción

Cambia a las credenciales de producción, `OPENPAY_PRODUCTION=true`, registra y verifica de nuevo el webhook en el panel de producción y realiza una compra real de bajo importe antes de anunciarlo. Los pagos de sandbox anteriores quedan como historial y nunca se confirman con la configuración de producción.

### Historial de Stripe

Stripe ya no se usa. Las columnas `pagos.stripe_*`, la tabla `eventos_stripe` y el origen «Stripe (histórico)» se conservan solo para leer pagos anteriores; no hay código que procese eventos de Stripe. Si existía un webhook en el panel de Stripe, elimínalo.

## Página de enlaces y dominios personalizados

> Procedimiento previsto; no se ha aplicado a ningún servidor. Las comprobaciones de DNS y HTTPS están implementadas y probadas con un DNS simulado; la emisión real de certificados depende de Caddy en el servidor.

### Variables de entorno

```
DASTI_PAGINAS_PREFIJO=l                       # /l/{slug}; NO cambiar tras compartir direcciones
DASTI_PAGINAS_CNAME=paginas.dasti.cloud       # destino CNAME para subdominios de clientes
DASTI_PAGINAS_IPS_INGRESO=203.0.113.10        # IP(s) públicas de Caddy (A/AAAA), separadas por comas
DASTI_PAGINAS_SECRETO_TLS=                    # opcional: secreto para /interno/dominios/autorizar
DASTI_PAGINAS_HOSTS_PROPIOS=                  # hosts extra de DASTI (p. ej. www.redireccion.dasti.cloud)
DASTI_PAGINAS_DOMINIOS_RESERVADOS=dasti.cloud,dasti.mx,dasti.com
DASTI_PAGINAS_DIAS_RETENCION=400              # eventos individuales (los totales diarios se conservan)
```

Sin `DASTI_PAGINAS_IPS_INGRESO` los dominios pueden llegar a «DNS verificado», pero **nunca** a «Activo»: la comprobación HTTPS solo se conecta a esas IP (evita SSRF y _DNS rebinding_).

### DNS de DASTI

- `paginas.dasti.cloud` → registro A/AAAA con la IP del servidor con Caddy.
- Los clientes crean, en su propio proveedor de DNS:
    - `TXT _dasti-verificacion.<su-host>` = token mostrado en el panel (prueba de propiedad).
    - Subdominio (`enlaces.sunegocio.com`): `CNAME` → `paginas.dasti.cloud`.
    - Dominio raíz (`sunegocio.com`): `A`/`AAAA` → `DASTI_PAGINAS_IPS_INGRESO`.

### Caddy (certificados bajo demanda, emisión y renovación automáticas)

Nginx no puede emitir certificados bajo demanda para dominios desconocidos. Se recomienda Caddy en el puerto 443 delante de PHP-FPM (o delante de Nginx). Ejemplo:

```caddy
{
    # Caddy pregunta a la app antes de emitir o renovar un certificado.
    on_demand_tls {
        ask http://127.0.0.1:8081/interno/dominios/autorizar
    }
}

# Panel, enlaces permanentes y páginas /l/... de DASTI.
redireccion.dasti.cloud {
    root * /var/www/sistema-redirecciones/public
    encode zstd gzip
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
}

# Dominios personalizados: cualquier host que la app autorice.
https:// {
    tls {
        on_demand
    }
    root * /var/www/sistema-redirecciones/public
    encode zstd gzip
    # Solo los recursos compilados; ningún otro archivo de public/ (íconos,
    # manifiesto o Service Worker de DASTI) se sirve en dominios de clientes.
    handle /build/* {
        file_server
    }
    handle {
        php_fastcgi unix//run/php/php8.4-fpm.sock {
            try_files index.php
        }
    }
}

# Solo para la pregunta de Caddy (no expuesto a Internet).
http://127.0.0.1:8081 {
    bind 127.0.0.1
    root * /var/www/sistema-redirecciones/public
    php_fastcgi unix//run/php/php8.4-fpm.sock {
        try_files index.php
    }
}
```

- `/interno/dominios/autorizar?domain=<host>` responde 200 solo para los hosts de DASTI y los dominios con DNS verificado (propiedad + destino); 404 para el resto y 403 si la petición no viene de 127.0.0.1/::1 sin cabeceras de proxy (o con `?secreto=` igual a `DASTI_PAGINAS_SECRETO_TLS`). Bloquea además `/interno/` en cualquier proxy público.
- Para cualquier host que no sea de DASTI la aplicación (`EnrutarDominiosPagina`) solo atiende la página del negocio dueño de ese dominio (`/`, `/{subpagina}`, `/ir/{id}`, `/contacto`, `/qr.svg`, `/medios/*` de ese negocio y `/.well-known/dasti-verificacion/{token}`); todo lo demás responde 404 sin iniciar sesión.
- No uses `ASSET_URL` con otro dominio: la CSP de las páginas públicas solo permite scripts y estilos del mismo origen.

### Tareas programadas

Ya están en `routes/console.php` (requieren el cron de `schedule:run`):

- `dasti:revisar-dominios` cada 10 minutos: verifica DNS y HTTPS de los dominios cuya revisión toca (pendientes cada 15 min el primer día y luego cada 6 h; activos una vez al día; un activo que falla 3 revisiones seguidas pasa a «Requiere atención» y deja de enrutarse). `--todos` fuerza la revisión de todos.
- `dasti:depurar-paginas` a diario (03:45): borra las huellas diarias anónimas tras `DASTI_DIAS_CONSERVACION_HUELLA` y los eventos individuales tras `DASTI_PAGINAS_DIAS_RETENCION`.

### Verificación tras el despliegue

1. Publica una página de prueba y abre `https://redireccion.dasti.cloud/l/<slug>`: revisa la CSP en las herramientas del navegador (sin errores) y que no aparezca ninguna marca de DASTI.
2. Conecta un subdominio de prueba, crea los registros DNS y pulsa «Revisar ahora»: debe pasar a «DNS verificado» y, en cuanto Caddy obtenga el certificado, a «Activo con HTTPS».
3. Comprueba que `https://<dominio-de-prueba>/login` y `/admin` responden 404.
