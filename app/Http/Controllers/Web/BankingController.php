<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;use App\Models\BankAccount;use App\Models\BankImport;use App\Models\BankTransaction;
class BankingController extends Controller { public function accounts(){return view('banking.accounts',['accounts'=>BankAccount::with('bank')->withCount('transactions')->get()]);} public function transactions(){return view('banking.transactions',['transactions'=>BankTransaction::with('bankAccount.bank')->latest('booking_date')->paginate(30)]);} public function imports(){return view('banking.imports',['accounts'=>BankAccount::with('bank')->get(),'imports'=>BankImport::with('account.bank')->latest()->paginate(20)]);} }
