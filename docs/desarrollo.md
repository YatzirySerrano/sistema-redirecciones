# Guía de desarrollo

## Requisitos

- PHP 8.4 con las extensiones `pdo_mysql`, `gd`, `intl` y `mbstring`.
- Composer 2.
- Node 22 o superior y npm.
- MariaDB 10.11 o superior (probado con 11.4).

## Instalación

```bash
composer install
cp .env.example .env          # si aún no existe
php artisan key:generate
npm install
```

## MariaDB

```sql
CREATE DATABASE sistema_redirecciones          CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE sistema_redirecciones_testing  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

En `.env` configura `DB_CONNECTION=mariadb` y las credenciales. En macOS con Homebrew puedes usar el socket: `DB_SOCKET=/tmp/mysql.sock`, `DB_USERNAME=<tu usuario>` y `DB_PASSWORD=` vacío.

## Migraciones y datos de demostración

```bash
php artisan migrate
php artisan db:seed                  # carga DemoSeeder; se niega a ejecutarse en producción
php artisan dasti:credenciales-demo  # muestra las cuentas de demostración (solo APP_ENV=local)
```

- Cuentas de demostración: `admin@dasti.test`, `cafe@cliente.test`, `estetica@cliente.test`, `taller@cliente.test` y `floreria@cliente.test` (negocio suspendido).
- Contraseña: `DASTI_DEMO_PASSWORD` (por defecto `Demo-Dasti-2026`).
- El seeder es idempotente: no duplica ni borra datos. Solo genera accesos sintéticos para productos que todavía no tienen ninguno.

## Ejecución local

```bash
composer dev        # servidor, cola, logs y Vite
# o por separado:
php artisan serve
npm run dev
```

Los correos (invitaciones y recuperación de contraseña) se escriben en `storage/logs/laravel.log` mientras `MAIL_MAILER=log`. El enlace de invitación es una URL firmada `…/invitacion/{id}?expires=…&huella=…&signature=…`.

En desarrollo, el plugin `@inertiajs/vite` renderiza también en el servidor (SSR). En móvil, la consola muestra avisos de _hydration mismatch_ del sidebar del starter kit (el servidor no conoce el ancho de pantalla) y de los tiempos relativos («hace 1 minuto»). Son solo de desarrollo: en producción no se ejecuta el SSR.

## Pruebas y calidad

Las pruebas usan **MariaDB** sobre `sistema_redirecciones_testing`, configurada en `phpunit.xml`. Las pruebas que renderizan páginas necesitan `public/build/manifest.json` (ejecuta `npm run build` antes) o el servidor de Vite en marcha. `tests/TestCase.php` se niega a ejecutar las pruebas sobre cualquier base cuyo nombre no termine en `_testing`. Las credenciales se toman del entorno:

```bash
DB_USERNAME=root DB_PASSWORD=secreto php artisan test
# o por socket:
DB_SOCKET=/tmp/mysql.sock DB_USERNAME=$USER DB_PASSWORD= php artisan test

vendor/bin/pint --test          # estilo PHP
vendor/bin/phpstan analyse      # análisis estático (nivel 7)
npm run types:check             # vue-tsc
npm run check                   # lint + formato (vite-plus)
npm run build                   # compilación de producción
```

Después de añadir o cambiar rutas, Wayfinder regenera `resources/js/routes` y `resources/js/actions` al compilar (o con `php artisan wayfinder:generate --with-form`).
