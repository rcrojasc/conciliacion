# Modelo de datos inicial

## Core
organizations(id, name, tax_id, status, base_currency, timezone)
organization_user(id, organization_id, user_id, role_id, status)
roles, permissions, role_permissions

## Banking
banks(id, code, name, country)
bank_accounts(id, organization_id, bank_id, external_id, account_type, currency, masked_number, status)
bank_connections(id, organization_id, bank_id, provider, encrypted_credentials, consent_expires_at, status)
bank_imports(id, organization_id, bank_account_id, source, file_hash, status, totals...)
bank_statements(id, organization_id, bank_account_id, period_from, period_to, opening_balance, closing_balance)
bank_transactions(id, organization_id, bank_account_id, statement_id, external_id, booking_date, value_date, amount, currency, direction, description_raw, description_normalized, reference, operation_number, counterparty_tax_id, counterparty_name, balance_after, fingerprint, status)
transaction_raw_data(id, bank_transaction_id, payload jsonb)
transaction_duplicates(id, transaction_id, duplicate_of_id, reason)

## Commercial
counterparties(id, organization_id, type, tax_id, legal_name)
financial_documents(id, organization_id, counterparty_id, document_type, folio, issue_date, due_date, currency, total_amount, open_amount, status, external_reference)
receivables/payables (document_id, open_amount, status)
payments(id, organization_id, counterparty_id, amount, currency, paid_at, reference)

## Reconciliation
reconciliation_rules(id, organization_id, name, priority, rule_type, config jsonb, auto_approve_threshold, enabled)
reconciliation_runs(id, organization_id, bank_account_id, started_at, finished_at, status, metrics jsonb)
reconciliation_candidates(id, run_id, bank_transaction_id, candidate_type, score, score_breakdown jsonb, reasons jsonb, status)
reconciliations(id, organization_id, status, method, confidence_score, proposed_by, approved_by, approved_at, reversed_at)
reconciliation_items(id, reconciliation_id, bank_transaction_id nullable, financial_document_id nullable, payment_id nullable, applied_amount, currency)
reconciliation_differences(id, reconciliation_id, amount, reason, accounting_category_id nullable)
reconciliation_exceptions(id, organization_id, bank_transaction_id, exception_type, details jsonb, status, assigned_to)

## AI/Audit
ai_suggestions(id, organization_id, entity_type, entity_id, provider, model, schema_version, response jsonb, confidence, status)
ai_feedback(id, suggestion_id, user_id, decision, corrected_data jsonb)
audit_logs(id, organization_id, user_id, action, entity_type, entity_id, before jsonb, after jsonb, metadata jsonb, created_at)

## Índices esenciales
- bank_transactions(organization_id, bank_account_id, booking_date)
- bank_transactions(organization_id, fingerprint) UNIQUE cuando fingerprint sea confiable
- financial_documents(organization_id, counterparty_id, status, due_date)
- financial_documents(organization_id, document_type, folio, counterparty_id) UNIQUE según regla de negocio
- reconciliation_candidates(run_id, score DESC)
- reconciliation_items(bank_transaction_id), reconciliation_items(financial_document_id)
- audit_logs(organization_id, created_at DESC)
