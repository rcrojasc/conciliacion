<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;use App\Models\Reconciliation;use App\Services\Reconciliation\ReversalService;use Illuminate\Http\Request;
class ReconciliationHistoryController extends Controller { public function index(){ $reconciliations=Reconciliation::query()->with('items')->latest()->paginate(25);return view('reconciliation.history',compact('reconciliations')); } public function reverse(Request $request,Reconciliation $reconciliation,ReversalService $service){$data=$request->validate(['reason'=>'required|string|min:5|max:500']);$service->reverse($reconciliation,$data['reason'],$request->user()->id);return back()->with('success','Conciliación reversada con trazabilidad.');} }
