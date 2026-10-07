<?php
namespace App\Services\Banking;
class TransactionFingerprint { public function make(string $accountId,array $t): string { return hash('sha256',implode('|',[$accountId,$t['booking_date'],$t['value_date']??'',number_format((float)$t['amount'],4,'.',''),$t['currency'],$t['direction'],$t['operation_number']??'',$t['reference']??'',$t['description_normalized']??''])); } }
