<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class loggedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function is_admin(Request $request)
    {
        $user = auth('api')->user();
        if($admin_value=$user['admin']==1){
            return response()->json([
                'is_admin' => 'True',
            ]);
        }else{
            return response()->json([
                'is_admin' => 'False',
            ]);
        }
    }
    public function get_user(Request $request)
    {
        $user = auth('api')->user();
        return response()->json([
            'user' => $user,
        ]);
    }

    public function create_web_project(Request $request){
        /*$request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'production' => 'required|boolean',
            'deployment' => 'required|integer',
            'port' => 'required|integer',
            'replicas' => 'required|integer',
            'url' => 'required|string',
            'workgroup_id' => 'required|integer',
        ]);*/
        $user = auth('api')->user();
        DB::table('projects')->insert([
            'name' => $request->name,
            'description' => $request->description,
            'production' => $request->production,
            'deployment' => $request->deployment,
            'port' => $request->port,
            'replicas' => $request->replicas,
            'url' => $request->url,
            'workgroup_id' => $user->workgroup_id,
        ]);
        return response()->json([
            'Status' => "success",
        ]);
    }

    //Change password
    public function update_password(Request $request)
{
        # Validation
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required',
        ]);

        #Match The Old Password
        if(!Hash::check($request->old_password, auth()->user()->password)){
            return response()->json([
                'error' => "Old Password Doesn't match!",
            ]);
        }

        #Update the new Password
        DB::table('users')->whereId(auth()->user()->id)->update([
            'password' => Hash::make($request->new_password)
        ]);
        return response()->json([
            'status' => "success",
        ]);
}



}
