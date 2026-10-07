<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;use App\Services\Reconciliation\AutoReconciliationService;use Illuminate\Http\Request;
class AutoReconciliationController extends Controller { public function __invoke(Request $request,AutoReconciliationService $service){$run=$service->run($request->user()->id,$request->input('bank_account_id'));$m=$run->metrics;return redirect()->route('reconciliation.index')->with('success',"Ejecución automática finalizada: {$m['auto_approved']} conciliados, {$m['review']} a revisión, {$m['no_match']} sin coincidencia.");} }
