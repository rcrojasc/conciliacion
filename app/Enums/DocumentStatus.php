<?php
namespace App\Enums;
enum DocumentStatus: string { case Open='open'; case Partial='partial'; case Paid='paid'; case Cancelled='cancelled'; case Overdue='overdue'; }
