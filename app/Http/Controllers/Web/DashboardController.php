<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;use App\Models\BankImport;use App\Models\BankTransaction;use App\Models\FinancialDocument;use App\Models\Reconciliation;
class DashboardController extends Controller { public function __invoke(){
 $total=BankTransaction::count();$reconciled=BankTransaction::whereIn('status',['reconciled','partially_reconciled'])->count();
 return view('dashboard',['accounts'=>BankAccount::with('bank')->count(),'transactions'=>$total,'reconciled'=>$reconciled,'pending'=>BankTransaction::whereIn('status',['normalized','pending','candidate_found'])->count(),'documents'=>FinancialDocument::count(),'reconciliations'=>Reconciliation::count(),'imports'=>BankImport::latest()->limit(5)->get(),'recent'=>BankTransaction::latest('booking_date')->limit(8)->get()]);
 }}
