<?php
namespace App\Http\Controllers\Web;use App\Http\Controllers\Controller;use App\Models\AuditLog;use Illuminate\Http\Request;
class AuditController extends Controller {public function __invoke(Request $r){$q=AuditLog::query()->latest('created_at');if($r->filled('action'))$q->where('action','ilike','%'.$r->string('action').'%');return view('audit.index',['logs'=>$q->paginate(30)->withQueryString()]);}}
