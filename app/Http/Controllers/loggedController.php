<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\log;
use App\Models\project;
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

    public function get_workgroup(Request $request)
    {
        $user = auth('api')->user();
        $query = DB::table('workgroups')->where('id', $user->workgroup_id)->first();
        return $query;
    }

    public function create_web_project(Request $request){
        $user = auth('api')->user();
        project::create([
            'name' => $request->name,
            'description' => $request->description,
            'production' => $request->production,
            'deployment' => $request->deployment,
            'port' => $request->port,
            'replicas' => $request->replicas,
            'url' => $request->url,
            'workgroup_id' => $user->workgroup_id,
        ]);

        //Guardar el log del proyecto creado
        $log = log::create([
            'user_id' => $user->id,
            'description' => "Created new web project '".$request->name."'",
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

        //Guardar el log del proyecto creado
        $user = auth('api')->user();
        $log = log::create([
            'user_id' => $user->id,
            'description' => "Updated password",
        ]);
        return response()->json([
            'status' => "success",
        ]);
}

    //devolver todos los logs del usuario que hace la petición
    public function get_logs()
    {
        $user = auth('api')->user();
        $query = DB::table('logs')->where('user_id', $user->id)->orderBy('created_at','desc')->get();
        //$query = DB::table('workgroups')->where('id', '1')->first();
        return $query;

    }

}
