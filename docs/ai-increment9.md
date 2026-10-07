# IA asistida — Incremento 9

La IA queda detrás de `AIProviderInterface`. El proveedor inicial `HeuristicAIProvider` es local y determinístico para permitir pruebas sin credenciales externas. `TransactionAIService` valida siempre la respuesta con `StructuredTransactionSchema`, persiste la sugerencia y genera auditoría.

Regla de seguridad: una sugerencia IA jamás modifica saldos, documentos, movimientos ni conciliaciones. Para aplicar una conciliación se utiliza el motor determinístico y, cuando corresponde, Maker/Checker.

Para conectar un LLM real se debe crear otro adapter que implemente `AIProviderInterface`, usar salida estructurada y validar de nuevo en servidor. Las claves deben residir exclusivamente en secretos/variables de entorno.
