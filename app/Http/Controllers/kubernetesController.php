<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\cluster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class kubernetesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function add_cluster(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string',
                'description' => 'required|string',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'Arguments missing',
            ]);
        }

        $user = auth('api')->user();
        if($user->admin==1){
            //Primero recoger la ip del endpoint
            $response = Http::get($request->domain.'/info');
            //Ahora hay que validarlo
            $result=preg_match("/EasyKube_v\d.\d+/", $response);
            if($result==1){
                try {
                    cluster::create([
                        'workgroup_id' => $user->workgroup_id,
                        'domain' => $request->domain,
                        'description' => "Validated user ".$request->description,
                    ]);
                    return response()->json([
                        'Created',
                    ]);
                } catch (\Throwable $th) {
                    return response()->json([
                        'Already exists',
                    ]);
                }

            }else{
                return response()->json([
                    'Error',
                ]);
            }
        }
        else{
            return response()->json([
                'Forbidden',
            ]);
        }
    }
}
