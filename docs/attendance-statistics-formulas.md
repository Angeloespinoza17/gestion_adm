# Fórmulas de asistencia

Todas las tasas usan únicamente registros esperados dentro de la vigencia de matrícula y días lectivos del periodo filtrado.

| Indicador | Fórmula |
| --- | --- |
| Asistencia | `presentes / registros esperados * 100` |
| Inasistencia | `ausentes / registros esperados * 100` |
| Ausencia justificada | `ausentes justificados / registros esperados * 100` |
| Ausencia injustificada | `ausentes no justificados / registros esperados * 100` |
| Atrasos | `registros con minutes_late > 0 / registros esperados * 100` |
| Retiros anticipados | `registros con early_departure = 1 / registros esperados * 100` |
| Brecha de meta | `asistencia actual - meta` en puntos porcentuales |
| Variación absoluta | `valor actual - valor del periodo comparable` |
| Variación relativa | `(actual - anterior) / anterior * 100`; nula si anterior es cero |
| Promedio | suma de tasas individuales / cantidad de tasas con datos |
| Mediana | valor central ordenado; promedio de los dos centrales cuando son pares |
| Desviación estándar | raíz del promedio de desviaciones cuadráticas poblacionales |
| Percentil | interpolación lineal entre posiciones ordenadas |
| Tendencia | pendiente de regresión lineal sobre tasas ordenadas en el tiempo |
| Racha de ausencia | máximo de ausencias consecutivas según días con registro |
| Proyección simple | `(presentes observados + esperados futuros * tasa escenario) / total esperado` |
| Asistencias para la meta | `ceil(meta * total esperado final - presentes observados)` acotado a los cupos restantes |
| Impacto financiero | `asistencias promedio * valor unidad * factor vigente` |

Los promedios de grupos ponderan por registros esperados para evitar que un curso pequeño tenga el mismo peso que uno grande. Las comparaciones muestran `sin base comparable` cuando no existe periodo anterior suficiente. Una correlación, cuando se habilite, nunca se presenta como causalidad.
