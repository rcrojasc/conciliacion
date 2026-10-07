# Fase 2 — Bootstrap técnico

## Decisión de versión
Laravel 13 + PHP 8.4 + PostgreSQL + Redis. Laravel 13 es la rama estable vigente en septiembre de 2026 y soporta PHP 8.3–8.5.

## Entregado en esta fase
- Manifiesto Composer para Laravel 13.
- `.env.example` orientado a PostgreSQL/Redis.
- Enums financieros iniciales.
- Modelos base de Organization, User, Bank, BankAccount, BankTransaction, Counterparty, FinancialDocument y Reconciliation.
- Cinco migraciones por dominio: Core, Banking, Commercial, Reconciliation, AI/Audit.
- Contexto de tenant para aislamiento multiempresa.
- Seeder demo inicial.

## Bloqueo del entorno de construcción
El runtime disponible tiene PHP 8.4.23 y Node 22.16, pero no Composer, PostgreSQL ni Redis instalados. Además, el contenedor no resuelve `getcomposer.org`, por lo que no fue posible descargar dependencias ni ejecutar `artisan migrate` en esta sesión.

## Validación que debe ejecutarse al disponer de Composer/PostgreSQL
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
```

## Próximo incremento
Completar bootstrap oficial de Laravel, middleware/resolución de tenant, RBAC, policies, pruebas de aislamiento, y luego núcleo Banking/importación.
