<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\bbdd_projects;
use App\Models\bbdd_tickets;
use App\Models\cluster;
use App\Models\log;
use Illuminate\Http\Request;

class KubernetesBBDDController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function solicitar_bbdd(Request $request)
    {
        $user = auth('api')->user();

        //Buscar ese cluster que id tiene
        $cluster = cluster::where('name', '=', $request->cluster )->
        where('workgroup_id', '=', $user->workgroup_id )->first();

        if($cluster==null){
            return response()->json([
                'status' => 'cluster not found',
            ]);
        }

        //Vemos si el proyecto ya existe
        $query = bbdd_projects::where('name', '=', $request->name )->where('cluster_id', '=', $cluster->id )->first();

        if($query!=null){
            return response()->json([
                'status' => 'exists',
            ]);
        }

        #AÑADIR EN BBDD
        bbdd_projects::create([
            'name'=>$request->name,
            'description'=>$request->description,
            'memory'=>$request->memory,
            'dbname'=>$request->dbname,
            'dbuser'=>$request->dbuser,
            'dbpwd'=>$request->dbpwd,
            'aproved'=>False,
            'replicas'=>$request->replicas,
            'workgroup_id'=>$user->workgroup_id,
            'cluster_id'=>$cluster->id
        ]);

        //Ver cual es el id del proyecto que se acaba de crear
        $proyecto = bbdd_projects::where('name', '=', $request->name )->where('cluster_id', '=', $cluster->id )->first();

        bbdd_tickets::create([
            'action' => 0, //0 Crear //1 Replicas //2 Borrar
            'description' => "Create BBDD project ".$request->name,
            'user_id' => $user->id,
            'bbdd_project_id' => $proyecto->id,
        ]);

        //Guardar el log del proyecto creado
        log::create([
            'user_id' => $user->id,
            'description' => "Requested create web project '".$request->name."'",
        ]);

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function get_bbdd_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin==1){
            $query = bbdd_tickets::join('users', 'bbdd_tickets.user_id', '=', 'users.id')
            ->select('bbdd_tickets.*', 'users.name')
            ->where('accepted', 0)
            ->where('declined', 0)
            ->orderBy('created_at','desc')->get();
            return $query;
        }
        else{
            return response()->json([
                'forbidden',
            ]);
        }
    }
}
