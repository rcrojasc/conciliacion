<?php
namespace App\Http\Controllers\Api\V1\Imports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\StoreBankImportRequest;
use App\Jobs\Banking\ProcessBankImport;
use App\Models\BankAccount;
use App\Models\BankImport;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
class BankImportController extends Controller {
 public function index(){return BankImport::query()->latest()->paginate(25);}
 public function store(StoreBankImportRequest $r,TenantContext $tenant){$account=BankAccount::query()->findOrFail($r->string('bank_account_id'));$file=$r->file('file');$hash=hash_file('sha256',$file->getRealPath());$existing=BankImport::query()->where('bank_account_id',$account->id)->where('file_hash',$hash)->first();if($existing)return response()->json(['message'=>'La cartola ya fue cargada.','import'=>$existing],409);$path=$file->store('bank-imports/'.$tenant->organization()->id,'local');$import=BankImport::create(['organization_id'=>$tenant->organization()->id,'bank_account_id'=>$account->id,'source'=>$file->getClientOriginalExtension(),'file_hash'=>$hash,'status'=>'queued','metadata'=>['original_name'=>$file->getClientOriginalName()]]);ProcessBankImport::dispatch($import->id,Storage::disk('local')->path($path),$file->getClientOriginalExtension(),$r->validated('column_map'));return response()->json($import,202);}
 public function show(BankImport $bankImport){return $bankImport;}
}
