<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller {
 public function create(){return view('auth.login');}
 public function store(Request $request){$credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]); if(!Auth::attempt($credentials,$request->boolean('remember'))){return back()->withErrors(['email'=>'Las credenciales no son válidas.'])->onlyInput('email');} $request->session()->regenerate(); $org=$request->user()->organizations()->wherePivot('status','active')->first(); if($org)$request->session()->put('organization_id',$org->id); return redirect()->intended(route('dashboard'));}
 public function destroy(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
}
