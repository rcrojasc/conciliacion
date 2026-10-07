<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;use App\Models\AIFeedback;use App\Models\AISuggestion;use App\Models\BankTransaction;use App\Services\AI\TransactionAIService;use Illuminate\Http\Request;
final class AITransactionController extends Controller {public function suggest(BankTransaction $transaction,TransactionAIService $ai){$s=$ai->suggest($transaction);return back()->with('ai_suggestion',$s->response)->with('success','La IA asistida generó una sugerencia. No se aplicó ningún cambio financiero.');}
 public function feedback(Request $request,AISuggestion $suggestion){$d=$request->validate(['decision'=>'required|in:accepted,rejected,corrected','corrected_data'=>'nullable|array']);AIFeedback::create(['suggestion_id'=>$suggestion->id,'user_id'=>$request->user()->id,'decision'=>$d['decision'],'corrected_data'=>$d['corrected_data']??null]);$suggestion->update(['status'=>$d['decision']]);return back()->with('success','Feedback registrado.');}}
