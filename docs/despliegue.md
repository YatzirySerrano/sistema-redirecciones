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

`public/favicon.ico`, `public/favicon.svg` y `public/apple-touch-icon.png` siguen siendo los del starter kit de Laravel. Sustitúyelos por el logotipo oficial de DASTI cuando esté disponible (no se generó uno provisional). Los QR de los clientes nunca llevan marca de DASTI.

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
