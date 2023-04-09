<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\bbdd_projects;
use App\Models\bbdd_tickets;
use App\Models\cluster;
use App\Models\log;
use Illuminate\Http\Request;
use Response;

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
            'provider'=>$request->provider,
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

    public function delete_bbdd_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin!=1){
            return response()->json([
                'forbidden',
            ]);
        }

        //si el usuario es admin puede hacer cosas
        //Recoger que ticket es
        $query = bbdd_tickets::where('id', $request->bbdd_ticket_id)
        ->first();

        //Si no se encuentra ese ticket, se devuelve el error
        if($query==null){
            return response()->json([
                'status'=>"BBDD ticket dont exist",
            ]);
        }

        //Si es un ticket de crear un nuevo proyecto de BBDD
        if($query->action==0){
            //Actualizar la peticion
            bbdd_tickets::where('id', $request->bbdd_ticket_id)
            ->update(['declined' => true]);

            //Actualizar el proyecto para eliminarlo pues no se ha llegado a ejecutar
            bbdd_projects::where('id', $query->bbdd_project_id)
            ->delete();

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Deleted ticket: '".$query->description."'",
            ]);
        }
        else{
            //Actualizar la peticion
            bbdd_tickets::where('id', $request->bbdd_ticket_id)
            ->update(['declined' => true]);

            //Crear log
           log::create([
                'user_id' => $user->id,
                'description' => "Deleted ticket: '".$query->description."'",
            ]);
        }
        return response()->json([
            'status'=>'success',
        ]);
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

        //recogemos el proyecto y el cluster
        $bbdd_projects = bbdd_projects::select("bbdd_projects.*","clusters.domain")->where('bbdd_projects.id',"=", $bbdd_ticket->bbdd_project_id)
        ->join("clusters","bbdd_projects.cluster_id","clusters.id")->first();

        //Crear proyecto
        if($bbdd_ticket->action==0){

            if($bbdd_projects->provider=="Google Cloud Platform"){
                $resultado=$this->deploy_bbdd_projects_gke($bbdd_ticket->bbdd_project_id);
            }
            else{
                $resultado=$this->deploy_bbdd_projects($bbdd_ticket->bbdd_project_id);
            }


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

            //Si el proyecto es on-premises, cambiamos la IP del proyecto a Localhost
            if($bbdd_projects->provider=="On-premises"){
               bbdd_projects::where('bbdd_projects.id', '=', $request->bbdd_project_id)->update(['ip' => 'Localhost']);
            }

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$bbdd_ticket->description."'",
            ]);
        }
        /*
        //UPDATE REPLICAS
        if($bbdd_ticket->action==1){

            //Tenemos la dirección del cluster
            $cluster_ip = $bbdd_projects->domain;

            //Actualizar las replicas
            #Coger variables
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/update-replicas.py" '.$cluster_ip." ".$bbdd_projects->name." ".$bbdd_ticket->replicas);
            if($result!="b'success'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }

            //Actualizar la peticion
            bbdd_tickets::where('id', $request->bbdd_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el web project
            bbdd_projects::where('id', $bbdd_ticket->bbdd_project_id)
            ->update(['replicas' => $bbdd_ticket->replicas]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$bbdd_ticket->description."'",
            ]);
        }
        */
        //Borrar
        if($bbdd_ticket->action==2){

            //Tenemos la dirección del cluster
            $cluster_ip = $bbdd_projects->domain;


            //Ahora que sabemos que proyecto es
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/delete-project.py" '.$cluster_ip." ".$bbdd_projects->name);
            if($result!="b'ok'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }
            //Crear log
            $log = log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$bbdd_ticket->description."'",
            ]);
            //Actualizar la peticion
            bbdd_tickets::where('id', $request->bbdd_ticket_id)
            ->update(['accepted' => true]);

        }
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

        /*
        #Crear HPA
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-hpa.py" ' . $query->domain." ".$query->name." ".$query->replicas);
        #if($result!="b'Created'"){Return "Error creating hpa";}
        if($result!="b'Created'"){Return $result;}
        */
        return "Successfull";


    }
    public function deploy_bbdd_projects_gke(int $bbdd_project_id){
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
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd-gke.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd);
        //return $RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd;
        //if($result!="b'Created'"){Return $result;}
        if($result!="b'CreatedCreatedCreatedCreatedCreated'"){Return $result;}

        return "Successfull";
    }
    public function get_bbdd_project(Request $request)
    {
        //recuperamos la entrada del proyecto
        $query=bbdd_projects::select('bbdd_projects.*' , 'clusters.name AS cluster_name','clusters.domain')
        ->join('clusters', 'bbdd_projects.cluster_id', '=', 'clusters.id')
        ->where('bbdd_projects.id', '=', $request->bbdd_project_id)
        ->first();

        //Si la ip es None quiere decir que es un servidor on premise que no cuenta con IP
        if($query->provider!="On-premises"){
            //recoger la IP del servidor BBDD
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

            $ip = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/get-services-ip.py" '. $query->domain." ". $query->name);
            $ip = substr($ip, 2);
            $ip = substr($ip, 0,-1);
            //Actualizamos la IP
            $query=bbdd_projects::where('bbdd_projects.id', '=', $request->bbdd_project_id)->update(['ip' => $ip]);

            //Volvemos a generar la llamada con la IP actualizada
            $query=bbdd_projects::select('bbdd_projects.*' , 'clusters.name AS cluster_name','clusters.domain')
            ->join('clusters', 'bbdd_projects.cluster_id', '=', 'clusters.id')
            ->where('bbdd_projects.id', '=', $request->bbdd_project_id)
            ->first();
        }


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

    //Esta función CREA UN TICKET para modificar el número de réplicas de un proyecto on premises
    /*
    public function apply_update_bbdd_replicas(Request $request)
    {
        $user = auth('api')->user();
        #Se crea el ticket
        bbdd_tickets::create([
            'action' => 1, //0 Crear //1 Replicas //2 Borrar
            'replicas' => $request->replicas,
            'description' => "Update replicas up to ".$request->replicas." for project '".$request->project_name."'",
            'user_id' => $user->id,
            'bbdd_project_id' => $request->bbdd_project_id,
        ]);
        //Se crea un log
        log::create([
            'user_id' => $user->id,
            'description' => "Requested Update replicas up to ".$request->replicas." for project '".$request->project_name."'",
        ]);
        //se responde con el mensaje de aceptación
        return response()->json([
            'status'=>'success',
        ]);
    }
    */

    //Se solicita con un ticket el borrado del proyecto BBDD
    public function apply_delete_bbdd_project(Request $request)
    {
    $user = auth('api')->user();
    $bbdd_projects=bbdd_projects::where('id', $request->bbdd_project_id)
    ->where('workgroup_id', $user->workgroup_id)
    ->first();

    bbdd_tickets::create([
        'action' => 2, //0 Crear //1 Replicas //2 Borrar
        'description' => "Delete project '".$bbdd_projects->name."'",
        'user_id' => $user->id,
        'bbdd_project_id' => $request->bbdd_project_id,
    ]);

    //Guardar el log del proyecto creado
    log::create([
        'user_id' => $user->id,
        'description' => "Requested to delete database project '".$bbdd_projects->name."'",
    ]);
    return response()->json([
        'status'=>'success',
    ]);
    }

    public function postgres_backup(Request $request)
    {
        //En base al usuario accedemos a la información del proyecto bbdd para extraer las credenciales
        $user = auth('api')->user();

        //recogemos el proyecto y el cluster
        $bbdd_projects = bbdd_projects::select("bbdd_projects.*","clusters.domain")
        ->where('bbdd_projects.id',"=", $request->bbdd_project_id)
        ->where('bbdd_projects.workgroup_id', $user->workgroup_id)
        ->join("clusters","bbdd_projects.cluster_id","clusters.id")->first();

        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/postgres-backup.py" '.$bbdd_projects->domain." ".$bbdd_projects->name." ".$bbdd_projects->dbuser." ".$bbdd_projects->dbname);
        //$ruta=exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/TEST.py');


        //$file= public_path(). "/download/info.pdf";

        $headers = array(
                  'Content-Type: application/sql',
                );

        return Response::download('../backup.sql', 'backup.sql', $headers);
    }
}
