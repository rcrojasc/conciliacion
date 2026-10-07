# Fase 2 — Incremento 7

Se incorporó el primer motor de reglas configurable: pesos de scoring, tolerancia absoluta/porcentual, ventanas de fecha y umbral de auto-aprobación. `RuleResolver` usa la regla activa de mayor prioridad y mantiene un perfil seguro por defecto cuando no hay reglas persistidas.

Se añadió `AutoReconciliationService`, que crea una ejecución auditable, persiste candidatos y auto-aprueba únicamente cuando el mejor candidato supera el umbral y no existe ambigüedad cercana (segundo candidato a menos de 5 puntos). Los demás casos quedan para revisión humana.

Pendiente de validación de integración: ejecutar Composer, migraciones y suite sobre PostgreSQL/Redis reales.
