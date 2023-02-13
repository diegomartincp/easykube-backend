<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
