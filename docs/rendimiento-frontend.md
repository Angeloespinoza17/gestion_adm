# Rendimiento del frontend

## Estrategia aplicada

La optimización conserva los mismos componentes, estilos y funciones. Cambia el momento en que se descargan dependencias pesadas:

- PDFMake y sus fuentes se importan al solicitar una exportación PDF.
- CKEditor se importa al abrir el formulario de noticias o eventos.
- FullCalendar del backlog se importa al seleccionar la vista Calendario.
- ApexCharts se registra como componente asíncrono y se carga únicamente en vistas con gráficos.
- El chat flotante y la vista completa de mensajería son chunks separados.
- XLSX ya se carga desde los helpers de exportación que lo requieren; no debe volver a importarse globalmente.

Vite conserva el `import()` como límites de chunks y genera nombres con hash. `public/.htaccess` agrega compresión Brotli/Gzip cuando el servidor dispone de los módulos y caché inmutable para assets versionados. HTML y respuestas de API no reciben caché inmutable.

## Reglas para nuevas vistas

- No importar PDFMake, CKEditor, FullCalendar, ApexCharts o XLSX desde `app.js`.
- Usar componentes asíncronos para módulos que no forman parte de la primera pantalla.
- Cargar exportadores dentro de la acción del usuario y mostrar el estado de carga correspondiente.
- No reducir resolución, tipografías, opciones del editor ni capacidades de exportación para optimizar el bundle.
- Después de agregar una dependencia, comparar el chunk principal y verificar que la librería aparezca en un chunk separado.
- Mantener imágenes responsivas; antes de reemplazar video o imágenes por versiones comprimidas, realizar comparación visual y conservar los originales.

## Validación

```bash
npm run prod
npm run test:unit
```

Validación manual representativa:

1. Abrir `/tasks/backlog` y cambiar a Calendario.
2. Abrir `/tasks/all` y probar filtros, prioridad, orden y paginación.
3. Abrir `/admin/noticias`, crear/editar sin guardar y confirmar que CKEditor aparece.
4. Abrir `/admin/dashboard` y confirmar que los gráficos se renderizan.
5. Abrir mensajería y confirmar conexión en tiempo real.
6. Revisar la consola y la pestaña Network para errores y cargas prematuras.

Los archivos multimedia grandes requieren una etapa posterior con codificación alternativa y comparación de calidad. No se deben recomprimir automáticamente sin una referencia visual aprobada.
