<?php
namespace App\Http\Controllers\Api\V1\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function login(Request $r){ $data=$r->validate(['email'=>['required','email'],'password'=>['required','string'],'device_name'=>['nullable','string','max:100']]); $u=User::where('email',$data['email'])->first(); if(!$u||!Hash::check($data['password'],$u->password)) throw ValidationException::withMessages(['email'=>['Credenciales inválidas.']]); $token=$u->createToken($data['device_name']??'arions-finance')->plainTextToken; return response()->json(['token'=>$token,'user'=>$u->only('id','name','email')]); }
 public function me(Request $r){ return response()->json($r->user()->load('organizations:id,name,status,base_currency')); }
 public function logout(Request $r){ $r->user()->currentAccessToken()?->delete(); return response()->noContent(); }
}
