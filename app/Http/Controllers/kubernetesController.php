<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\cluster;
use App\Models\web_project;
use App\Models\web_ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use stdClass;
use App\Models\log;

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
                'name' => 'required|string',
                'type' => 'required|string',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'Arguments missing',
            ]);
        }
        //Primero comprobamos si el cluster ya existe y únicamente fue desactivado el seguimiento
        $user = auth('api')->user();
        $existe = cluster::where('domain', $request->domain)
        ->where('workgroup_id', $user->workgroup_id)
        ->where('active', false)->first();

        //Si ya existia
        if($existe!=null){
            cluster::where('domain', $request->domain)->where('workgroup_id', $user->workgroup_id)->update(['active' => true]);;
                    //Guardar el log del proyecto creado
            log::create([
                'user_id' => $user->id,
                'description' => "Added agin cluster '".$existe->name."'",
            ]);
            return response()->json([
                'status'=>'validated',
            ]);
        }



        if($user->admin==1){
            //Primero recoger la ip del endpoint
            $response = Http::get($request->domain.'/info');
            //Ahora hay que validarlo
            $result=preg_match("/EasyKube_v\d.\d+/", $response);
            if($result==1){
                try {
                    cluster::create([
                        'workgroup_id' => $user->workgroup_id,
                        'name' => $request->name,
                        'domain' => $request->domain,
                        'description' => $request->description,
                        'type' => $request->type,
                    ]);
                    log::create([
                        'user_id' => $user->id,
                        'description' => "Added cluster '".$request->name."'",
                    ]);
                    return response()->json([
                        'status'=>'created',
                    ]);
                } catch (\Throwable $th) {
                    return response()->json([
                        'status'=>'Already exists',
                    ]);
                }

            }else{
                return response()->json([
                    'status'=>'Error',
                ]);
            }
        }
        else{
            return response()->json([
                'status'=>'Forbidden',
            ]);
        }
    }

    public function delete_cluster(Request $request)
    {

        $user = auth('api')->user();
        if($user->admin==1){
                    //Validar
        cluster::where('id', $request->cluster_id)
        ->update(['active' => false]);
        $cluster=cluster::where('id', $request->cluster_id)
        ->first();

        log::create([
            'user_id' => $user->id,
            'description' => "Deleted cluster '".$cluster->name."'",
        ]);

        return response()->json([
            'status'=>'success',
        ]);
        }
    }

    public function get_clusters(Request $request)
    {
        $user = auth('api')->user();
        $query = DB::table('clusters')
                ->select('id', 'name' ,'domain','type', 'description')
                ->where('workgroup_id', $user->workgroup_id)
                ->where('active', true)
                ->get();
        return $query;
    }

    public function get_health(Request $request)
    {
        $clusters = $this->get_clusters($request);
        //Decode json
        $json_array  = json_decode($clusters, true);
        $elementCount  = count($json_array);

        // Crear un objeto JSON vacío
        $lectura = new stdClass();

        $escritura = new stdClass();
        $flag=0;
        //Para cada cluster
        for ($i=0; $i < $elementCount; $i++) {

            //Pedir todas sus cargas de trabajo
            $response = Http::get($clusters[$i]->domain.'/get_pods_health');

            //Para cada carga de trabajo del cluster i
            foreach(json_decode(str($response)) as $deployment){
                $temp = new stdClass();
                $temp->Name = $deployment->Name;
                $temp->Replicas = $deployment->Replicas;
                $temp->Avalaibable = $deployment->Avalaibable;
                $temp->cluster_id = $clusters[$i]->id;

                //Verificar si la carga de trabajo es un proyecto web
                $nombre = explode("-deployment", $deployment->Name)[0];
                $query = web_project::where('web_projects.name', '=', $nombre )
                ->select('clusters.*', 'web_projects.*')
                ->join('clusters', 'web_projects.cluster_id', '=', 'clusters.id')
                ->where('active', 1)
                ->first();



                //!!!!!!!!VERIFICAR AQUI SI ES UN PROYECTO DE BBDD O FLASK DE LA TABLA QUE CREE EN SU MOMENTO IGUAL QUE ARRIBA

                if($nombre=="easykube-controlplane"){
                    $temp->from_app = True;
                }
                else if ($query === null) {
                    //No es una carga de trabajo de easykube
                    $temp->from_app = False;
                }
                else{
                    //Si es
                    $temp->from_app = True;
                    $temp->web_project_id = $query->id;
                    $temp->query = $query;
                }

                $escritura->$flag = $temp;
                $flag=$flag+1;

            }


        }

        return json_decode(json_encode($escritura), true);
    }

    public function solicitar_web_project(Request $request)
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
        $query = web_project::where('name', '=', $request->name )->where('cluster_id', '=', $cluster->id )->first();

        if($query!=null){
            return response()->json([
                'status' => 'exists',
            ]);
        }


        #AÑADIR EN BBDD
        web_project::create([
            'name'=>$request->name,
            'description'=>$request->description,
            'email'=>$request->email,
            'prod'=>$request->type,
            'token'=>$request->token,
            'url'=>$request->url,
            'ipname'=>$request->ipname,
            'cluster_ip'=>$request->cluster_ip,
            'dns'=>$request->dns,
            'aproved'=>False,
            'replicas'=>$request->replicas,
            'workgroup_id'=>$user->workgroup_id,
            'cluster_id'=>$cluster->id
        ]);




        //Ver cual es el id del proyecto que se acaba de crear
        $proyecto = web_project::where('name', '=', $request->name )->where('cluster_id', '=', $cluster->id )->first();



        web_ticket::create([
            'action' => 0, //0 Crear //1 Replicas //2 Borrar
            'description' => "Create project ".$request->name,
            'user_id' => $user->id,
            'web_project_id' => $proyecto->id,
        ]);

        //Guardar el log del proyecto creado
        $log = log::create([
            'user_id' => $user->id,
            'description' => "Requested create web project '".$request->name."'",
        ]);

        return response()->json([
            'status' => 'success',
        ]);

    }
    //NO SE UTILIZA. SOLO PARA DEBUG
    public function ver_web_solicitados(Request $request)
    {
        $user = auth('api')->user();
        $query = web_project::where('aproved', '=', False )->where('workgroup_id', '=', $user->workgroup_id )->get();;
        return $query;
    }

    public function deploy_web_project(int $web_project_id)
    {
        //Ver que proyecto es en la base de datos
        $user = auth('api')->user();
        $query = web_project::where('aproved', '=', False )
        ->where('workgroup_id', '=', $user->workgroup_id )
        ->where('id', '=', $web_project_id )
        ->first();;

        if($query==null){
            return "ERROR";
        }



        //Si llegamos aquí es que el proyect existe así que lo creamos

        //Primero recogemos la ip del cluster
        $cluster = cluster::where('workgroup_id', '=', $user->workgroup_id )
        ->where('id', '=', $query->cluster_id )
        ->first();
        $cluster_ip = $cluster->domain;

        #Coger variables
        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        #Crear secreto
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/empty-secret.py" ' .$cluster_ip." ".$query->name);

        //if($result!="b'Created'"){Return $result;}
        if($result!="b'Created'"){Return "Error creating secret";}

        #Crear issuer
        if($query->prod==1){
            #Crear issuer producción
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/issuer-prod.py" ' . $cluster_ip." ".$query->name." ".$query->email);
            if($result!="b'Created'"){Return "Error creating prod-issuer";}
        }
        else{
            #Crear issuer stagging
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/issuer-stagging.py" ' . $cluster_ip." ".$query->name." ".$query->email);
            if($result!="b'Created'"){Return "Error creating stagging-issuer";}
        }

        #Crear proyecto web y el servicio
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-web.py" ' . $cluster_ip." ".$query->name." ".$query->url." ".$query->token." ".$query->replicas);
        if($result!="b'CreatedCreated'"){Return "Error creating project";}

        #Crear HPA
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-hpa.py" ' . $cluster_ip." ".$query->name." ".$query->replicas);
        #if($result!="b'Created'"){Return "Error creating hpa";}
        if($result!="b'Created'"){Return $result;}

        #Crear ingress
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/ingress-ssl.py" ' . $cluster_ip." ".$query->name." ".$query->ipname." ".$query->dns);
        if($result!="b'Created'"){Return "Error creating ingress";}


        //Validar
        DB::table('web_projects')
        ->where('id', $web_project_id)
        ->update(['aproved' => true]);

        #FIN
        return "Successfull";
    }

    public function get_web_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin==1){
            $query = DB::table('web_tickets')
            ->join('users', 'web_tickets.user_id', '=', 'users.id')
            ->select('web_tickets.*', 'users.name')
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
    public function accept_web_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin!=1){
            return response()->json([
                'forbidden',
            ]);
        }

        //si el usuario es admin puede hacer cosas

        //Recoger el web ticket
        $web_ticket = DB::table('web_tickets')
        ->where('id', $request->web_ticket_id)
        ->first();

        if($web_ticket==null){
            return response()->json([
                'status'=>"Web ticket dont exist",
            ]);
        }


        //Crear proyecto
        if($web_ticket->action==0){
            //Deploy del web project de la query web_tickets
            $resultado=$this->deploy_web_project($web_ticket->web_project_id);

            if($resultado!="Successfull"){
                return response()->json([
                    'status'=>$resultado,
                ]);
            }
            //Actualizar la peticion
            DB::table('web_tickets')
            ->where('id', $request->web_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el web project
            DB::table('web_projects')
            ->where('id', $web_ticket->web_project_id)
            ->update(['aproved' => true]);

            //Crear log
            $log = log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$web_ticket->description."'",
            ]);
        }

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
        return response()->json([
            'status'=>'success',
        ]);

    }
    public function delete_web_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin!=1){
            return response()->json([
                'forbidden',
            ]);
        }

        //si el usuario es admin puede hacer cosas
        //Recoger que ticket es
        $query = DB::table('web_tickets')
        ->where('id', $request->web_ticket_id)
        ->first();

        if($query==null){
            return response()->json([
                'status'=>"Web ticket dont exist",
            ]);
        }

        //Si es un ticket de crear web
        if($query->action==0){
            //Actualizar la peticion
            DB::table('web_tickets')
            ->where('id', $request->web_ticket_id)
            ->update(['declined' => true]);

            //Actualizar el web project
            DB::table('web_projects')
            ->where('id', $query->web_project_id)
            ->delete();

            //Crear log
            $log = log::create([
                'user_id' => $user->id,
                'description' => "Deleted ticket: '".$query->description."'",
            ]);
        }
        else{
            //Actualizar la peticion
            DB::table('web_tickets')
            ->where('id', $request->web_ticket_id)
            ->update(['declined' => true]);

            //Crear log
            $log = log::create([
                'user_id' => $user->id,
                'description' => "Deleted ticket: '".$query->description."'",
            ]);
        }
        return response()->json([
            'status'=>'success',
        ]);
    }
    public function get_web_project(Request $request)
    {
        $query=web_project::select('web_projects.*' , 'clusters.name AS cluster_name')
        ->join('clusters', 'web_projects.cluster_id', '=', 'clusters.id')
        ->where('web_projects.id', '=', $request->web_project_id)
        ->first();
        return $query;
    }

    //Esta función CREA UN TICKET para modificar el número de réplicas
    public function apply_update_web_replicas(Request $request)
    {
    $user = auth('api')->user();
    web_ticket::create([
        'action' => 1, //0 Crear //1 Replicas //2 Borrar
        'replicas' => $request->replicas,
        'description' => "Update replicas up to ".$request->replicas." for project '".$request->project_name."'",
        'user_id' => $user->id,
        'web_project_id' => $request->web_project_id,
    ]);
    return response()->json([
        'status'=>'success',
    ]);
    }
    public function apply_delete_web_project(Request $request)
    {
    $user = auth('api')->user();
    $web_project=DB::table('web_projects')
    ->where('id', $request->web_project_id)
    ->where('workgroup_id', $user->workgroup_id)
    ->first();

    web_ticket::create([
        'action' => 2, //0 Crear //1 Replicas //2 Borrar
        'description' => "Delete project '".$web_project->name."'",
        'user_id' => $user->id,
        'web_project_id' => $request->web_project_id,
    ]);

    //Guardar el log del proyecto creado
    $log = log::create([
        'user_id' => $user->id,
        'description' => "Requested delete web project '".$web_project->name."'",
    ]);
    return response()->json([
        'status'=>'success',
    ]);
    }


    /**
     * Esta función solo recibe un dominio y el nombre del deployment a reiniciar
     */
    public function restart_pods(string $domain,string $name)
    {

        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/restart-pods.py" '.$domain." ".$name);

        if($result!="b'success'"){
            return $result;
        }

        $user = auth('api')->user();
        log::create([
            'user_id' => $user->id,
            'description' => "Restarted ".$name." project pods",
        ]);

        return "ok";
    }
    public function restart_web_pods(Request $request)
    {
        $user = auth('api')->user();

        //Recogemos la información del proyecto
        $web_project=DB::table('web_projects')
        ->select('web_projects.*','clusters.domain')
        ->join('clusters', 'web_projects.cluster_id', '=', 'clusters.id')
        ->where('web_projects.id', $request->web_project_id)
        ->where('web_projects.workgroup_id', $user->workgroup_id)
        ->first();


        $result=$this->restart_pods($web_project->domain,$web_project->name);

        if($result!= "ok"){
            return response()->json([
                'status'=>$result,
                ]);
        }else{
            return response()->json([
            'status'=>"success",
            ]);
        }

    }


    //Esta función devuelve la salud de un proyecto web haciendo uso de project_health()
    public function web_project_health(Request $request)
    {
        $user = auth('api')->user();
        //Recogemos la información del proyecto
        $web_project=DB::table('web_projects')
        ->select('web_projects.*','clusters.domain')
        ->join('clusters', 'web_projects.cluster_id', '=', 'clusters.id')
        ->where('web_projects.id', $request->web_project_id)
        ->where('web_projects.workgroup_id', $user->workgroup_id)
        ->first();

        return $this->project_health($web_project->domain, $web_project->name);
    }
    //esta función llama al cluster de kubernetes para que le devuelva la salud de un poyecto en concreto
    public function project_health(string $domain,string $name)
    {

        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/project-health.py" '.$domain." ".$name);
        $result = substr($result, 2);
        $result = substr($result, 0,-1);

        return $result;
    }






}
