# Respaldos de base de datos en producción

La aplicación ejecuta `php artisan backup:database` cada domingo a las 03:00, únicamente cuando `APP_ENV=production`.

## Configuración

Variables opcionales del `.env` de producción:

```dotenv
BACKUP_DISK=local
BACKUP_PATH=backups/database
BACKUP_RETENTION_DAYS=35
BACKUP_SCHEDULE="0 3 * * 0"
BACKUP_TIMEOUT=3600
```

Con el disco `local`, los archivos quedan en `storage/app/backups/database`. Para tolerar la pérdida completa del servidor, configure `BACKUP_DISK` con un disco remoto disponible y pruebe una restauración periódicamente.

El servidor debe tener `mysqldump` para MySQL/MariaDB o `pg_dump` para PostgreSQL. SQLite no requiere una herramienta adicional.

## Activar el scheduler

Debe existir una sola entrada cron para el usuario que ejecuta PHP:

```cron
* * * * * cd /ruta/de/la/aplicacion && php artisan schedule:run >> /dev/null 2>&1
```

## Verificación

```bash
php artisan backup:database
php artisan schedule:list
```

Una copia no debe considerarse válida hasta comprobar que puede restaurarse en una base separada.

## Protección de datos de producción

Fuera de `local`, `development` y `testing`, la aplicación bloquea globalmente los comandos que pueden vaciar o resembrar la base: `db:seed`, `db:wipe`, `migrate:fresh`, `migrate:refresh`, `migrate:reset` y `migrate:rollback`.

El script `scripts/deploy.sh` aplica además estas condiciones antes de migrar:

1. cancela el despliegue si el árbol Git local contiene cambios sin confirmar;
2. compila el release local sin tocar producción;
3. como primera operación remota, comprueba que el entorno sea exactamente `production` y muestra `migrate:status`;
4. registra conteos de tablas críticas, ejecuta `backup:database --no-prune`, verifica la integridad del archivo con `gzip -t` o `PRAGMA integrity_check`, calcula su SHA-256 y se detiene si cualquiera de esas comprobaciones falla;
5. solo después del respaldo sincroniza los archivos e instala dependencias;
6. ejecuta exclusivamente `migrate --force --no-interaction`, nunca seeders ni comandos de reinicio de base de datos;
7. vuelve a medir las tablas críticas y cancela la validación si cualquier conteo disminuyó.

El respaldo previo al despliegue no sustituye las copias externas ni las pruebas periódicas de restauración.
