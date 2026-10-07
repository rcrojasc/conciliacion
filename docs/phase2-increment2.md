# Fase 2 — Incremento 2

Se agregó el esqueleto ejecutable de Laravel 13, bootstrap, rutas API v1, resolución de organización activa, policy inicial y primer motor determinístico de scoring/matching 1:1.

## Seguridad multiempresa
La API exige `X-Organization-Id` y valida que el usuario autenticado pertenezca a esa organización antes de fijar `TenantContext`. Los modelos con `BelongsToOrganization` deben aplicar el scope de tenant.

## Próximo incremento
Autenticación con Sanctum, RBAC granular, importador CSV/XLSX, staging/idempotencia y pruebas Feature/Unit.
