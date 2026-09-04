# Transicion del software a `cnscvaldivia.cl`

Este procedimiento mueve el software completo al dominio canonico
`https://cnscvaldivia.cl`. `cnscgestion.cl`, `www.cnscgestion.cl` y
`www.cnscvaldivia.cl` quedan unicamente como entradas de redireccion permanente
al nuevo dominio. No cambia usuarios, correos, contrasenas ni ningun otro dato
persistido.

## Alcance y condiciones de seguridad

- La raiz web de todos los nombres debe apuntar al mismo `public/` de Laravel.
- El cambio es de Apache/cPanel, TLS y variables de entorno; no necesita una
  migracion ni un seeder.
- Antes de tocar produccion se debe crear y verificar un respaldo de la base de
  datos, `.env`, configuracion Apache/cPanel, certificados y version desplegada.
- No ejecutar `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` ni
  seeders. Tampoco es necesario ejecutar `php artisan migrate` para este cambio.
- No editar los vhosts generados directamente por cPanel: los puede sobrescribir
  en la siguiente reconstruccion.

## 1. Preflight y respaldo

1. Registrar el document root, version PHP, estado PHP-FPM, vhosts y aliases
   efectivos antes del cambio. En este servidor el document root esperado es
   `/home/gestioncnsc/app/public`, pero se debe confirmar en vivo.
2. Crear un respaldo completo de la base de datos con la rutina operacional del
   proyecto y verificar integridad, tamano y fecha del archivo generado.
3. Respaldar `.env`, `/var/cpanel/userdata/gestioncnsc/`, cualquier include
   Apache local, la configuracion del servicio Reverb y la unidad de colas.
4. Ejecutar solo comprobaciones de lectura: `php artisan migrate:status`, conteo
   de trabajos pendientes/fallidos y revision de logs. Este cambio no autoriza
   modificar registros productivos.

## 2. Dominio y vhost en cPanel/WHM

En **cPanel > Domains**, agregar `cnscvaldivia.cl` como dominio de la cuenta
`gestioncnsc` y asignar como document root el `public/` real de la aplicacion.
`www.cnscvaldivia.cl` debe quedar como alias del mismo vhost. Si cPanel no
permite reutilizar la raiz, crear el dominio desde WHM y aplicar un include
Apache administrado por cPanel; no crear una segunda copia de la aplicacion.

Mantener `cnscgestion.cl` y `www.cnscgestion.cl` asociados temporalmente al mismo
`public/`. La primera regla de [`public/.htaccess`](../public/.htaccess) responde
con `308` hacia el apex nuevo y conserva ruta y query string. No configurar una
redireccion adicional de cPanel que compita con esa regla o degrade el codigo a
`301`.

Cloudflare debe usar SSL/TLS **Full (strict)** para conectarse al origen por
HTTPS. La aplicacion no confia en `X-Forwarded-Proto` para omitir la redireccion,
porque esa cabecera puede ser enviada directamente por un cliente.

Despues del cambio, reconstruir y validar la configuracion con las herramientas
de cPanel y exigir `Syntax OK` antes de recargar Apache. Confirmar que el vhost
usa la version PHP/FPM soportada por el proyecto y conserva el propietario de la
cuenta; el document root nunca debe ser la raiz Laravel situada sobre `public/`.

## 3. DNS web y TLS

El apex `cnscvaldivia.cl` ya debe resolver a la IP publica del VPS. Verificarlo
desde mas de un resolvedor y configurar tambien `www` como CNAME al apex o con
el mismo A. Si Reverb usa `ws.cnscvaldivia.cl`, ese nombre tambien debe apuntar
al proxy web del VPS.

Cambiar solo registros web. Conservar sin modificaciones MX, DKIM, DMARC, SPF,
SES y los nombres usados por correo (`mail`, `imap`, `smtp`, `pop`, `pop3`). Si
alguno de ellos hereda la IP mediante un CNAME al apex o si SPF usa `a`, corregir
esa dependencia de forma explicita antes de considerar terminado el cambio.

Emitir o ampliar AutoSSL para:

- `cnscvaldivia.cl`;
- `www.cnscvaldivia.cl`;
- `cnscgestion.cl` y `www.cnscgestion.cl` mientras sigan aceptando conexiones;
- `ws.cnscvaldivia.cl`, solamente si se usa ese host para Reverb.

Validar cadena, SNI y fecha del certificado. La redireccion `308` debe activarse
solo cuando HTTPS del destino responda correctamente.

## 4. Entorno de produccion

Actualizar el `.env` productivo sin copiar secretos al repositorio:

```dotenv
APP_URL=https://cnscvaldivia.cl
APP_TRUSTED_HOSTS=www.cnscvaldivia.cl,cnscgestion.cl,www.cnscgestion.cl
ASSET_URL=https://cnscvaldivia.cl
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=cnscvaldivia.cl,www.cnscvaldivia.cl

# Solo si Reverb esta realmente publicado con DNS, proxy y TLS propios:
REVERB_HOST=ws.cnscvaldivia.cl
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=cnscvaldivia.cl,www.cnscvaldivia.cl
```

`SESSION_DOMAIN` debe permanecer vacio para emitir cookies host-only. Los
valores de `APP_TRUSTED_HOSTS` son nombres exactos: no usar `*` ni un patron que
admita todos los subdominios. Si Reverb se publica mediante el mismo apex en vez
de `ws`, ajustar `REVERB_HOST` al vhost realmente configurado.

Luego limpiar y reconstruir la configuracion Laravel, reiniciar PHP-FPM si
corresponde y reiniciar de forma controlada Reverb y los workers para que tomen
el nuevo entorno. No ejecutar migraciones ni seeders. El enlace de recuperacion
de contrasena se genera desde `config('app.url')`, por lo que despues de
`config:cache` debe apuntar a `https://cnscvaldivia.cl/reset-password/...`.

## 5. Verificacion sin modificar datos

Comprobar desde una red externa:

```bash
curl -I 'http://cnscvaldivia.cl/login?origen=prueba'
curl -I 'https://cnscvaldivia.cl/login?origen=prueba'
curl -I 'https://www.cnscvaldivia.cl/login?origen=prueba'
curl -I 'https://cnscgestion.cl/login?origen=prueba'
curl -I 'https://www.cnscgestion.cl/login?origen=prueba'
```

Resultados esperados:

- HTTP del apex responde `308` hacia el mismo path/query en HTTPS.
- HTTPS del apex sirve la aplicacion sin bucle de redireccion.
- Los otros tres hosts responden `308` a
  `https://cnscvaldivia.cl/login?origen=prueba`.
- `/login`, assets con hash y `manifest.json` responden correctamente bajo el
  dominio nuevo; una ruta protegida sin sesion conserva su `401` o redireccion
  de autenticacion esperada.
- Un `Host` ajeno a `APP_URL` y `APP_TRUSTED_HOSTS` es rechazado por Laravel.
- Inicio/cierre de sesion, CSRF, subida/descarga autenticada y Reverb funcionan
  con el dominio nuevo. Para no escribir produccion, validar recuperacion de
  contrasena con el transport de correo en staging o inspeccionando la URL
  configurada, no enviando una solicitud real.

Revisar ademas consola del navegador, contenido mixto, CORS, cookies `Secure`,
logs Laravel/Apache/PHP-FPM y estado de Reverb y colas.

## 6. Rollback

Si hay error TLS, bucle, respuesta 5xx, assets rotos o fallo de autenticacion:

1. Restaurar el `.env` respaldado y reconstruir la cache de configuracion.
2. Revertir el alias/include de cPanel con su copia previa, reconstruir Apache,
   validar sintaxis y recargarlo.
3. Restaurar la version anterior de la aplicacion si el problema proviene del
   release y reiniciar PHP-FPM, Reverb y workers.
4. Revertir solamente los registros DNS web que se hayan cambiado. No tocar los
   registros de correo.
5. Confirmar nuevamente login, assets y servicios antes de cerrar el incidente.

No restaurar la base de datos salvo que una verificacion demuestre una alteracion
independiente: esta transicion no ejecuta escrituras de datos y su rollback es de
configuracion.
