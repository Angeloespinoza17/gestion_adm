# Pronóstico diario de Valdivia

La vista Inicio obtiene su pronóstico exclusivamente desde la base de datos. La clave de WeatherAPI permanece en el servidor y nunca se entrega al navegador.

La primera carga de Inicio de cada día intenta sincronizar el pronóstico. Antes de llamar al proveedor se crea un registro único en `weather_daily_syncs`; por ello, aunque entren varios usuarios al mismo tiempo, solo uno puede consultar WeatherAPI y todos los demás leen `weather_daily_records`.

## Configuración

Agregar al archivo `.env` del entorno:

```dotenv
WEATHERAPI_KEY=clave_del_entorno
WEATHERAPI_FORECAST_DAYS=7
WEATHERAPI_DISPLAY_DAYS=7
```

La ubicación está fijada por coordenadas en Valdivia y se registra con la región canónica `Región de Los Ríos`. También se conserva en `provider_region` la región devuelta por WeatherAPI para auditoría de la fuente.

## Sincronización

Ejecutar manualmente:

```bash
php artisan weather:sync-valdivia --days=7
```

El programador de Laravel intenta la misma sincronización una vez al día, a las 06:15. Si el programador ya consultó el proveedor, Inicio no vuelve a hacerlo; si no se ejecutó, la primera carga de Inicio actúa como respaldo. El comando y la vista comparten el registro diario, de modo que existe como máximo un intento externo por día.

Para una contingencia operativa se puede forzar conscientemente una consulta adicional:

```bash
php artisan weather:sync-valdivia --days=7 --force
```

El pronóstico usa `upsert` sobre proveedor, ubicación y fecha: una nueva consulta actualiza el día existente y no crea duplicados. Por ejemplo, si el 1 de septiembre se guarda inicialmente el pronóstico del 6 de septiembre, cada sincronización diaria vuelve a actualizar esa misma fila; el 6 de septiembre contendrá la versión obtenida ese día, que es más cercana y precisa. `created_at` conserva cuándo apareció la fecha por primera vez y `fetched_at` indica la actualización más reciente.

Los intentos completados o fallidos quedan trazados en `weather_daily_syncs`; un fallo no interrumpe Inicio ni provoca una llamada por cada usuario.

Los datos diarios estructurados quedan en `weather_daily_records`, ordenables e indexados por fecha para permitir un futuro cruce con asistencia sin depender del JSON de la API.
