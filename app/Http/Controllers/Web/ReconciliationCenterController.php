<?php
namespace App\Http\Controllers\Web;
use App\Enums\BankTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\FinancialDocument;
use App\Services\Reconciliation\ReconciliationEngine;
use App\Services\Reconciliation\ReconciliationWriter;
use App\Services\Reconciliation\Governance\GovernedReconciliationService;
use Illuminate\Http\Request;
class ReconciliationCenterController extends Controller {
 public function index(Request $request){
  $query=BankTransaction::query()->with('bankAccount')->whereIn('status',[BankTransactionStatus::Imported->value,BankTransactionStatus::Normalized->value,BankTransactionStatus::Pending->value,BankTransactionStatus::CandidateFound->value,BankTransactionStatus::PartiallyReconciled->value]);
  if($request->filled('q')){$q=$request->string('q');$query->where(fn($x)=>$x->where('description_raw','ilike',"%{$q}%")->orWhere('reference','ilike',"%{$q}%"));}
  $transactions=$query->orderByDesc('booking_date')->paginate(25)->withQueryString();
  return view('reconciliation.index',compact('transactions'));
 }
 public function show(BankTransaction $transaction, ReconciliationEngine $engine){
  $transaction->load('bankAccount');$candidates=$engine->suggest($transaction)->take(10);return view('reconciliation.show',compact('transaction','candidates'));
 }
 public function approve(Request $request,BankTransaction $transaction,GovernedReconciliationService $writer){
  $data=$request->validate(['financial_document_id'=>'required|string','score'=>'required|numeric|min:0|max:100']);
  $doc=FinancialDocument::query()->findOrFail($data['financial_document_id']);$writer->reconcile($transaction,$doc,(float)$data['score'],$request->user()->id,'manual');
  return redirect()->route('reconciliation.index')->with('success','Conciliación aprobada y aplicada correctamente.');
 }
 public function reject(BankTransaction $transaction,ReconciliationWriter $writer){$writer->ignore($transaction);return redirect()->route('reconciliation.index')->with('warning','Movimiento marcado como ignorado. La decisión quedó registrada en su estado.');}
}
