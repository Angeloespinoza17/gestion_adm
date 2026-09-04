# Reloj Control · GeoVictoria

## Alcance

`Reloj Control` es un módulo independiente de consulta, visible únicamente para usuarios activos con rol `super_admin`. La primera versión integra dos operaciones de sólo lectura:

- catálogo completo de funcionarios institucionales;
- vinculación segura con GeoVictoria por coincidencia exacta de RUT;
- libro de asistencia por funcionarios vinculados y rango de fechas.

El módulo no crea, edita ni elimina colaboradores, turnos, permisos o marcaciones.

## Seguridad

Las credenciales se leen exclusivamente en Laravel y nunca se incluyen en el bundle Vue ni en las respuestas del API interno. Deben configurarse en el `.env` de cada ambiente:

```dotenv
GEOVICTORIA_API_KEY=
GEOVICTORIA_API_SECRET=
GEOVICTORIA_CUSTOMER_API_URL=https://customerapi.geovictoria.com
```

Los endpoints internos están protegidos por `auth:sanctum`, el middleware `superadmin` y límites de frecuencia. La tabla local `staff` es la fuente autorizada del listado: una persona entregada únicamente por GeoVictoria nunca se muestra. El catálogo normalizado excluye dirección, teléfono, identificadores internos del proveedor y campos personalizados.

## Contrato utilizado

La autenticación se realiza con `POST /api/v1/Login`. El JWT se mantiene en caché durante 270 minutos, por debajo de las cinco horas de vigencia documentadas, y se renueva automáticamente ante una respuesta 401.

La consulta vigente de colaboradores usa `POST /api/v1/User/List`. El manual unificado de 2020 muestra `/api/User/List`, pero la ruta con versión fue verificada contra el servicio productivo el 30 de agosto de 2026; por eso se mantiene configurable mediante `GEOVICTORIA_USERS_PATH`.

El libro de asistencia usa `POST /api/v1/AttendanceBook` con `StartDate`, `EndDate` y `UserIds`. El frontend envía solamente `staff_ids`; Laravel resuelve los identificadores de GeoVictoria en el servidor. La aplicación limita cada consulta a 31 días y 50 funcionarios para mantener tiempos de respuesta predecibles.

La vinculación elimina puntos y guion exclusivamente para comparar el RUT y exige igualdad exacta, incluido el dígito verificador. No se realizan asociaciones por nombre o correo. Los funcionarios sin coincidencia continúan visibles con estado `Sin vincular a GeoVictoria`, pero no pueden seleccionarse. Las personas que GeoVictoria marca como `IsHiddenForReports` tampoco se consideran vinculables.

Si GeoVictoria no autentica o no está disponible, el catálogo institucional sigue visible y las consultas quedan bloqueadas hasta recuperar la conexión. La interfaz informa el motivo sin exponer respuestas privadas del proveedor.

Documentación oficial revisada: [API GeoVictoria · Conjunto de Endpoints](https://wiki.geovictoria.com/wp-content/uploads/2020/11/Conjunto-de-Endpoints-GV3-unificado.pdf).

## Endpoints internos

- `GET /api/superadmin/reloj-control/users`
- `POST /api/superadmin/reloj-control/attendance`

Las respuestas usan `Cache-Control: no-store` y entregan mensajes seguros ante errores del proveedor, sin retornar credenciales, tokens ni cuerpos de error externos.

## Navegación y RBAC

La ruta Vue es `/superadmin/reloj-control`, con `meta.superAdminOnly`. El módulo `superadmin_reloj_control` se registra de forma aditiva e idempotente bajo el padre `superadmin` y se asigna exclusivamente al rol `super_admin`.
