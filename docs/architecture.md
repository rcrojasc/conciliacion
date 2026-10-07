# Arquitectura — ARIONS FINANCE

## Decisión principal
Monolito modular Laravel, preparado para extraer workers/servicios de alto consumo en el futuro.

## Módulos
1. Core/Organization: organizaciones, usuarios, membresías, configuración, RBAC.
2. Banking: bancos, cuentas, conexiones, importaciones, cartolas, movimientos RAW y normalizados.
3. Commercial: contrapartes, documentos financieros, cuentas por cobrar/pagar y pagos.
4. Reconciliation: reglas, ejecuciones, candidatos, conciliaciones, asignaciones, diferencias, excepciones y reversas.
5. AI: proveedores, sugerencias estructuradas, explicaciones y feedback.
6. Audit: bitácora append-only para operaciones sensibles.
7. Reporting/Integration: dashboards, API v1 y webhooks.

## Flujo
Fuente bancaria -> Importación -> Staging/RAW -> Normalización -> Dedupe -> Persistencia -> Matching determinístico -> Scoring -> IA asistida para excepciones -> Revisión/aprobación -> Auditoría/ERP.

## Multiempresa
Todas las entidades de negocio contienen organization_id. El aislamiento se aplica en consultas, policies, Form Requests y API. Las restricciones únicas críticas incluyen organization_id. Se probará explícitamente el acceso cruzado entre tenants.

## Dinero
NUMERIC(20,4) en PostgreSQL para importes generales y código ISO-4217 para moneda. Nunca FLOAT/DOUBLE. Las reglas de redondeo serán configurables por moneda/organización y la conciliación conservará monto original, aplicado y diferencia.

## Concurrencia
Transacciones DB + SELECT FOR UPDATE/locks + constraints únicas. Ningún movimiento puede aplicarse dos veces por carreras concurrentes.

## Seguridad
Least privilege, cifrado de secretos, MFA preparado, rate limiting, auditoría, secretos solo servidor, no passwords bancarias.
