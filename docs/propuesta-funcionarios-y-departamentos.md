# Propuesta: funcionarios y departamentos

## Criterio funcional

Un funcionario debe distinguirse por su **categoría de usuario** (`staff`), no por un rol.

- **Categoría:** identifica qué tipo de persona es: funcionario, estudiante o apoderado.
- **Rol:** define qué puede hacer dentro del sistema. Es opcional y una cuenta puede tener ninguno, uno o varios.
- **Ficha de funcionario:** conserva información laboral, contractual, profesional y organizacional.
- **Cuenta de acceso:** permite autenticarse. Se vincula uno a uno con la ficha de funcionario.

Por lo tanto, asignar o quitar un rol nunca debe convertir a una persona en funcionario, estudiante o apoderado.

## Modelo recomendado

```text
Usuario
├── categoría: funcionario
├── cuenta y estado de acceso
├── 0..n roles de permisos
└── 1 ficha laboral de funcionario
    ├── cargo
    ├── estado laboral
    ├── contratos y documentos
    └── 0..n departamentos
```

Los departamentos son agrupaciones organizacionales. Un funcionario puede participar en más de uno y cada departamento puede tener un encargado.

**Responsabilidad y pertenencia son relaciones independientes:**

- Una persona puede estar a cargo de uno o varios departamentos.
- Esa misma persona puede pertenecer a otro equipo diferente.
- Estar a cargo no incorpora automáticamente a la persona como integrante.
- Pertenecer a un departamento no entrega permisos.

Ejemplo: el administrador puede estar a cargo de Mantención, Recursos Humanos y Contabilidad, pero pertenecer únicamente al Equipo Directivo.

## Experiencia propuesta

### Directorio de funcionarios

- Mostrar por separado la ficha laboral y la cuenta de acceso.
- Identificar explícitamente a la persona como `Funcionario`.
- Mostrar cargo y departamentos en una misma columna organizacional.
- Informar claramente las fichas con cuenta vinculada y las pendientes de cuenta.
- Permitir filtrar por departamento, estado laboral y disponibilidad de cuenta.
- Mantener roles fuera de esta vista principal.

### Constructor de departamentos

- Crear el departamento con nombre, propósito, estado y color.
- Seleccionar encargado y equipo en el mismo flujo.
- Mantener encargado e integrantes como selecciones independientes.
- Mantener la pertenencia al departamento independiente de roles y permisos.
- Dejar el orden de aparición como opción avanzada.

### Administración de usuarios

- Reemplazar el tipo de usuario escrito a mano por categorías controladas.
- Reservar la categoría `Funcionario` al flujo de Funcionarios, que crea o vincula su ficha laboral.
- Mantener la asignación de roles como una sección separada y opcional.

## Implementación por etapas

### Etapa 1 — aplicada

- Categorías controladas en Administración de usuarios.
- Protección contra asociaciones entre funcionarios y cuentas de estudiantes o apoderados.
- Directorio con estado de cuenta, métricas globales y filtro de acceso.
- Constructor de departamentos con encargado y selección de integrantes.
- Encargado y pertenencia gestionados como relaciones independientes.

### Etapa 2 — recomendada

Actualmente existen fichas históricas de funcionarios sin cuenta de acceso. Para cumplir la regla estricta de que todo funcionario sea también usuario:

1. Mostrar una bandeja de “Funcionarios sin cuenta”.
2. Completar o confirmar el correo institucional de cada persona.
3. Crear o vincular su cuenta y marcarla con categoría `staff`.
4. Bloquear, después de regularizar los datos, la creación de nuevas fichas sin cuenta.
5. Incorporar una restricción de integridad que garantice la relación uno a uno.

Esta transición evita inventar correos o sobrescribir cuentas existentes.

## Criterios de aceptación

- Una cuenta de estudiante o apoderado no puede vincularse como funcionario.
- Un usuario vinculado a una ficha laboral no puede cambiarse a otra categoría desde Administración de usuarios.
- Crear un departamento permite seleccionar su equipo.
- Una persona puede liderar varios departamentos sin pertenecer a sus equipos.
- Una persona puede pertenecer a uno o varios equipos, independientemente de los departamentos que lidera.
- La pertenencia a un departamento no cambia roles ni permisos.
- El directorio permite identificar y filtrar funcionarios sin cuenta de acceso.
