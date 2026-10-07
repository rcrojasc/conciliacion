<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;
class OrganizationSwitchController extends Controller { public function __invoke(Request $request){$id=$request->validate(['organization_id'=>'required|string'])['organization_id'];abort_unless($request->user()->organizations()->whereKey($id)->wherePivot('status','active')->exists(),403);$request->session()->put('organization_id',$id);return back();} }
