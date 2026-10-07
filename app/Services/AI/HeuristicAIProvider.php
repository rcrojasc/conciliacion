<?php
namespace App\Services\AI;
use App\Contracts\AIProviderInterface;
final class HeuristicAIProvider implements AIProviderInterface {
 public function analyzeTransaction(array $transaction,array $schema):array{$text=strtoupper(trim(($transaction['description_raw']??'').' '.($transaction['reference']??'')));$type='unknown';$category=null;$e=[];$confidence=.45;if(str_contains($text,'COMISION')||str_contains($text,'COMISIÓN')){$type='bank_fee';$category='bank_fees';$confidence=.96;$e[]='Descripción contiene comisión bancaria';}elseif(str_contains($text,'IMPUESTO')||str_contains($text,'TESORERIA')){$type='tax';$category='taxes';$confidence=.90;$e[]='Descripción contiene indicador tributario';}elseif(($transaction['direction']??'')==='credit'){$type='customer_payment';$confidence=.65;$e[]='Movimiento de abono';}elseif(($transaction['direction']??'')==='debit'){$type='supplier_payment';$confidence=.60;$e[]='Movimiento de cargo';}
  preg_match('/\b(\d{7,8})[- ]?([0-9K])\b/i',$text,$rut);preg_match('/\b(?:F|FAC|FACTURA)[- #:]*(\d{1,12})\b/i',$text,$folio);
  return ['transaction_type'=>$type,'tax_id'=>$rut?($rut[1].'-'.strtoupper($rut[2])):null,'counterparty'=>null,'document_reference'=>$folio?($folio[1]):null,'category'=>$category,'confidence'=>$confidence,'evidence'=>$e];}
}
