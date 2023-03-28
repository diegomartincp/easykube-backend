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

    public function accept_bbdd_tickets(Request $request)
    {
        //Cromprobar si es administrador
        $user = auth('api')->user();
        if($user->admin!=1){
            return response()->json([
                'forbidden',
            ]);
        }
        //si el usuario es admin puede hacer cosas

        //Recoger el web ticket en base al id que se aporta en la petición
        $bbdd_ticket = bbdd_tickets::where('id', $request->bbdd_ticket_id)
        ->first();

        if($bbdd_ticket==null){
            return response()->json([
                'status'=>"Web ticket dont exist",
            ]);
        }


        //Crear proyecto
        if($bbdd_ticket->action==0){

            $resultado=$this->deploy_bbdd_projects($bbdd_ticket->bbdd_project_id);

            if($resultado!="Successfull"){
                return response()->json([
                    'status'=>$resultado,
                ]);
            }


            //Actualizar la peticion
            bbdd_tickets::where('id', $request->bbdd_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el web project
            bbdd_projects::where('id', $bbdd_ticket->bbdd_project_id)
            ->update(['aproved' => true]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$bbdd_ticket->description."'",
            ]);
        }
        /*
        //UPDATE REPLICAS
        if($web_ticket->action==1){
            //Necesitamos saber primero que proyecto es
            $user = auth('api')->user();
            $web_project = web_project::where('workgroup_id', '=', $user->workgroup_id )
            ->where('id', '=', $web_ticket->web_project_id )
            ->first();


            //Sabiendo el proyecto sacamos el cluster en el que se ejecuta
            $cluster = cluster::where('workgroup_id', '=', $user->workgroup_id )
            ->where('id', '=', $web_project->cluster_id )
            ->first();

            //Tenemos la dirección del cluster
            $cluster_ip = $cluster->domain;

            //Actualizar las replicas
            #Coger variables
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/update-replicas.py" '.$cluster_ip." ".$web_project->name." ".$web_ticket->replicas);
            if($result!="b'success'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }

            //Actualizar la peticion
            DB::table('web_tickets')
            ->where('id', $request->web_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el web project
            DB::table('web_projects')
            ->where('id', $web_ticket->web_project_id)
            ->update(['replicas' => $web_ticket->replicas]);

            //Crear log
            $log = log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$web_ticket->description."'",
            ]);
        }
        //Borrar
        if($web_ticket->action==2){
            //Necesitamos saber primero que proyecto es
            $user = auth('api')->user();
            $web_project = web_project::where('workgroup_id', '=', $user->workgroup_id )
            ->where('id', '=', $web_ticket->web_project_id )
            ->first();


            //Sabiendo el proyecto sacamos el cluster en el que se ejecuta
            $cluster = cluster::where('workgroup_id', '=', $user->workgroup_id )
            ->where('id', '=', $web_project->cluster_id )
            ->first();

            //Tenemos la dirección del cluster
            $cluster_ip = $cluster->domain;


            //Ahora que sabemos que proyecto es
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/delete-project.py" '.$cluster_ip." ".$web_project->name);
            if($result!="b'ok'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }
            //Crear log
            $log = log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$web_ticket->description."'",
            ]);
            //Actualizar la peticion
            DB::table('web_tickets')
            ->where('id', $request->web_ticket_id)
            ->update(['accepted' => true]);

        }
        */
        return response()->json([
            'status'=>'success',
        ]);

    }
    public function deploy_bbdd_projects(int $bbdd_project_id){
        //Ver que proyecto es en la base de datos
        $user = auth('api')->user();
        $query = bbdd_projects::select('bbdd_projects.*',"clusters.domain")
        ->where('aproved', '=', False )
        ->where('bbdd_projects.workgroup_id', '=', $user->workgroup_id )
        ->where('bbdd_projects.id', '=', $bbdd_project_id )
        ->join("clusters",'bbdd_projects.cluster_id', '=','clusters.id')
        ->first();;

        if($query==null){
            return "ERROR";
        }

        //Tenemos toda la información necesaria para desplegar la bbdd
        #Coger variables
        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        #Crear secreto
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd);
        //return $RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd;
        //if($result!="b'Created'"){Return $result;}
        if($result!="b'CreatedCreatedCreatedCreatedCreated'"){Return $result;}

        #Crear HPA
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-hpa.py" ' . $query->domain." ".$query->name." ".$query->replicas);
        #if($result!="b'Created'"){Return "Error creating hpa";}
        if($result!="b'Created'"){Return $result;}

        return "Successfull";


    }

    public function get_bbdd_project(Request $request)
    {
        $query=bbdd_projects::select('bbdd_projects.*' , 'clusters.name AS cluster_name')
        ->join('clusters', 'bbdd_projects.cluster_id', '=', 'clusters.id')
        ->where('bbdd_projects.id', '=', $request->bbdd_project_id)
        ->first();
        return $query;
    }

    public function bbdd_project_health(Request $request)
    {
        $user = auth('api')->user();
        //Recogemos la información del proyecto
        $web_project=bbdd_projects::select('bbdd_projects.*','clusters.domain')
        ->join('clusters', 'bbdd_projects.cluster_id', '=', 'clusters.id')
        ->where('bbdd_projects.id', $request->bbdd_project_id)
        ->where('bbdd_projects.workgroup_id', $user->workgroup_id)
        ->first();

        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/project-health.py" '. $web_project->domain." ". $web_project->name);
        $result = substr($result, 2);
        $result = substr($result, 0,-1);

        return $result;
    }
}
