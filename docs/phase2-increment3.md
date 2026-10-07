# Fase 2 — Incremento 3

## Incorporado
- Laravel Sanctum para autenticación API por token.
- Rate limit de inicio de sesión.
- RBAC granular por organización y middleware `permission`.
- Seeder inicial de permisos y SUPER_ADMIN.
- Importación asíncrona de cartolas CSV/XLSX.
- Mapeo configurable de columnas por banco/formato.
- Separación RAW/normalizado.
- Fingerprint SHA-256 e idempotencia de archivo/transacción.
- Estadísticas de importación y estados queued/processing/completed/failed.
- Queue Job para procesamiento de cartolas.

## Dependencias nuevas
- `laravel/sanctum`
- `phpoffice/phpspreadsheet`

## Siguiente incremento
1. staging y registro detallado de errores por fila;
2. perfiles de mapeo reutilizables por banco;
3. UI de login/dashboard/importación;
4. ejecutar matching después de importación;
5. tests de aislamiento, idempotencia y RBAC.
