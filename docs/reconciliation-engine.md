# Motor de conciliación

## Pipeline
1. Seleccionar movimientos pendientes.
2. Generar candidatos dentro de organización, moneda, contraparte y ventana temporal.
3. Exact match 1:1.
4. Reglas configurables.
5. Scoring explicable.
6. Búsqueda combinatoria acotada para 1:N y N:1.
7. N:N solo bajo reglas explícitas/revisión.
8. IA únicamente para enriquecer/ordenar excepciones; no altera saldos por sí sola.
9. Auto-conciliar solo sobre umbral y reglas permitidas.
10. Persistir asignaciones atómicamente y auditar.

## Scoring inicial configurable
Monto, tax_id, folio/referencia, fecha, número de operación y similitud de contraparte. El score guarda breakdown y razones.

## Tolerancias
Absoluta/porcentual por organización, moneda o regla. Toda diferencia se persiste.

## Combinatoria
Aplicar filtros antes de subset-sum: misma contraparte/moneda, ventana temporal, máximo N documentos, máximo candidatos y timeout. No hacer búsqueda exponencial sin límites.
