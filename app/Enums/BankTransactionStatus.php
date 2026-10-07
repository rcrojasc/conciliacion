<?php
namespace App\Enums;
enum BankTransactionStatus: string { case Imported='imported'; case Normalized='normalized'; case Pending='pending'; case CandidateFound='candidate_found'; case Reconciled='reconciled'; case PartiallyReconciled='partially_reconciled'; case Observed='observed'; case Duplicate='duplicate'; case Ignored='ignored'; case Reversed='reversed'; }
