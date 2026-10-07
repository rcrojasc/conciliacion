# ARIONS FINANCE

Sistema inteligente de conciliación bancaria multiempresa.

## Estado
Fase 2 iniciada: bootstrap Laravel 13, esquema PostgreSQL inicial, modelos de dominio y base multiempresa.

## Stack objetivo
- PHP 8.4+
- Laravel (versión estable compatible al iniciar bootstrap)
- PostgreSQL
- Redis
- Queue workers / Scheduler
- IA desacoplada mediante AIProviderInterface

## Principio de conciliación
Reglas determinísticas -> scoring -> búsqueda 1:1 / 1:N / N:1 / N:N -> IA asistida -> revisión/aprobación humana.

## Próximo hito
Instalar dependencias Laravel, ejecutar migraciones/pruebas y completar RBAC + tenant isolation antes del módulo Banking.

## Fase 2 — Incremento 4: interfaz operativa
Se agregó frontend Blade sin dependencia de build para login, dashboard ejecutivo, selector de empresa, cuentas, movimientos e importación CSV/XLSX. El formulario web dispara el mismo Job de ingestión bancaria y mantiene la protección multiempresa. Para desarrollo local puede usarse `QUEUE_CONNECTION=sync`; en producción debe usarse Redis/worker.

## Incremento 6 — conciliación avanzada
Se añadieron pagos parciales acumulables, matching combinatorio acotado 1:N y N:1, registro formal de excepciones y reversa auditable de conciliaciones. El algoritmo combinatorio limita candidatos y profundidad para evitar explosión exponencial. Antes de producción deben ejecutarse pruebas de integración sobre PostgreSQL y escenarios monetarios reales controlados.

## Incremento 8 — Gobierno financiero
Se agregó auditoría persistente, controles configurables por organización, clasificación de riesgo y bandeja Maker/Checker. Las conciliaciones que se creen como `pending_review` pueden ser aprobadas o rechazadas por un usuario distinto del proponente, con bloqueo transaccional y bitácora. El siguiente paso es integrar esta política directamente en todos los writers automáticos/manuales y ejecutar pruebas E2E sobre PostgreSQL/Redis.
