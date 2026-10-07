<?php
namespace App\Enums;
enum ReconciliationStatus: string { case Proposed='proposed'; case Automatic='automatic'; case PendingReview='pending_review'; case Approved='approved'; case Rejected='rejected'; case Reversed='reversed'; }
