<?php
namespace App\Services\AI;
use Illuminate\Validation\ValidationException;
final class StructuredTransactionSchema {
 public const VERSION='1.0';
 public function definition():array{return ['transaction_type'=>['customer_payment','supplier_payment','bank_fee','tax','transfer','unknown'],'tax_id'=>'nullable string','counterparty'=>'nullable string','document_reference'=>'nullable string','category'=>'nullable string','confidence'=>'number 0..1','evidence'=>'array of strings'];}
 public function validate(array $data):array{$allowed=['customer_payment','supplier_payment','bank_fee','tax','transfer','unknown'];if(!isset($data['transaction_type'])||!in_array($data['transaction_type'],$allowed,true))throw ValidationException::withMessages(['ai'=>'Tipo de transacción IA inválido.']);$confidence=(float)($data['confidence']??0);if($confidence<0||$confidence>1)throw ValidationException::withMessages(['ai'=>'Confianza IA fuera de rango.']);return ['transaction_type'=>$data['transaction_type'],'tax_id'=>isset($data['tax_id'])?(string)$data['tax_id']:null,'counterparty'=>isset($data['counterparty'])?(string)$data['counterparty']:null,'document_reference'=>isset($data['document_reference'])?(string)$data['document_reference']:null,'category'=>isset($data['category'])?(string)$data['category']:null,'confidence'=>$confidence,'evidence'=>array_values(array_map('strval',$data['evidence']??[]))];}
}
