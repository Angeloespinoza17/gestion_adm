# Rendimiento de la primera carga en producción

## Diagnóstico del 23 de agosto de 2026

La ruta SPA (`/login` y `/home`) entregaba un HTML pequeño y assets comprimidos con caché, pero el tiempo hasta el primer byte era cercano a 0,7 segundos. El dominio `cnscgestion.cl` estaba configurado con PHP 8.2 y `php_fpm: 0`; además, el paquete `ea-php82-php-fpm` no estaba instalado. Cada solicitud dinámica debía iniciar PHP y Laravel mediante el handler CGI.

El `composer.lock` del release requiere PHP de 64 bits 8.3 por medio de `maennchen/zipstream-php` 3.2.2. Por ello, el vhost productivo quedó en PHP 8.3.33 y no en 8.2.

## Configuración operativa

El servidor cumple el mínimo recomendado para PHP-FPM: 3,6 GB de RAM, más de 2 GB disponibles durante el diagnóstico y una carga inferior a 0,2. La corrección consiste en:

1. crear y verificar un respaldo de base de datos antes de cualquier cambio;
2. instalar PHP 8.3, `ea-php83-php-fpm` y las extensiones requeridas por el lock de Composer;
3. habilitar PHP-FPM 8.3 solo para `cnscgestion.cl` mediante `php_set_vhost_versions`;
4. configurar el pool del dominio en modo `dynamic`, con un worker mínimo disponible, máximo de 5 y reciclaje cada 100 solicitudes;
5. verificar que el dominio informe `php_fpm: 1`, que el servicio esté activo y que `/login` responda correctamente;
6. comparar tiempos públicos y de loopback antes y después.

La configuración de Laravel se mantiene optimizada en cada deploy con `composer install --no-dev --optimize-autoloader`, `config:cache`, `route:cache` y `view:cache`. Los assets versionados conservan compresión y caché inmutable.

El pool se administra desde `/var/cpanel/userdata/gestioncnsc/cnscgestion.cl.php-fpm.yaml` y se regenera con `/scripts/php_fpm_config --rebuild --domain=cnscgestion.cl`. No se debe editar directamente el archivo generado bajo `/opt/cpanel/ea-php83/root/etc/php-fpm.d/`.

Los deploys deben declarar explícitamente:

```bash
DEPLOY_PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php
DEPLOY_COMPOSER_BIN='/opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/composer'
```

La mediana pública de seis solicitudes finales a `/login` fue de aproximadamente 0,15 segundos, frente a aproximadamente 0,70 segundos antes de PHP-FPM. El worker permaneció disponible después de más de 15 segundos sin solicitudes.

## Verificaciones obligatorias

- `whmapi1 php_get_vhost_versions vhost=cnscgestion.cl` debe devolver `php_fpm: 1`.
- Debe existir el pool del dominio bajo EasyApache 4 y su servicio debe estar activo.
- `/login`, `/home` y el JavaScript principal deben responder HTTP 200.
- La consola del navegador no debe mostrar errores.
- No se deben ejecutar seeders ni comandos destructivos durante la optimización o el deploy.
