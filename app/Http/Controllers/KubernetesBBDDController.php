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

    /**
     * Este método permite a un usuario crear un ticket donde se solicita la creación de
     * un proyecto con una base de datos.
     * También se registra un nuevo proyecto de base de datos con sus características y un
     * log de la realización de la solicitud.
     */
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
        $query = bbdd_projects::where('name', '=', $request->name )
        ->where('cluster_id', '=', $cluster->id )
        ->where('deleted', '=', 0 )
        ->first();

        if($query!=null){
            return response()->json([
                'status' => 'exists',
            ]);
        }

        #AÑADIR EN BBDD
        $proyecto = bbdd_projects::create([
            'name'=>$request->name,
            'description'=>$request->description,
            'memory'=>$request->memory,
            'dbname'=>$request->dbname,
            'dbuser'=>$request->dbuser,
            'dbpwd'=>$request->dbpwd,
            'aproved'=>False,
            'port'=>$request->port,
            'workgroup_id'=>$user->workgroup_id,
            'cluster_id'=>$cluster->id
        ]);

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

    /**
     * Este método devuelve un array con todos los tickets de proyectos web que aún no han sido ni
     * aceptados ni rechazados.
     */
    public function get_bbdd_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin==1){
            $query = bbdd_tickets::join('users', 'bbdd_tickets.user_id', '=', 'users.id')
            ->select('bbdd_tickets.*', 'users.name')
            ->where('accepted', 0)
            ->where('declined', 0)
            ->where('users.workgroup_id', '=', $user->workgroup_id )
            ->orderBy('created_at','desc')->get();
            return $query;
        }
        else{
            return response()->json([
                'forbidden',
            ]);
        }
    }

    /**
     * Este método rechaza un ticket de un proyecto de bases de datos alterando el atributo “declined”
     * en la base de datos.
     * En caso de que la petición que se desea rechazar sea para crear un nuevo proyecto, a demás de
     * alterar este campo se elimina la entrada correspondiente de la tabla bbdd_projects.
     */
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

    /**
     * Este método se encarga de realizar las gestiones necesarias cuando el administrador acepta
     * un ticket de un proyecto de base de datos. Se fija en el campo “action” del ticket y según
     * su valor realiza la acción correspondiente:
     * 0.	Es un ticket para crear un nuevo proyecto. Según si es on-premises o en GCP ejecutará
     * respectivamente los métodos deploy_bbdd_projects o deploy_bbdd_projects_gke para ejecutar
     * las automatizaciones que levantan las cargas de trabajo
     * 1.	* Puesto que para este tipo de proyectos no se puede manejar el número de réplicas y
     * para mantener el mismo estándar en los tickets de los diferentes tipos de proyecto, no se controla este caso
     * 2.	Es un ticket para eliminar un proyecto. Se ejecuta la automatización que lo elimina
     * y se marca en la entrada del proyecto como no activo
     *
     * En cualquiera de los casos se crea también un log recogiendo el cambio realizado.
     */
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
        $bbdd_projects = bbdd_projects::select("bbdd_projects.*","clusters.domain","clusters.provider")
        ->where('bbdd_projects.id',"=", $bbdd_ticket->bbdd_project_id)
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

            //Actualizar proyecto como borrado
            bbdd_projects::where('bbdd_projects.id', '=', $bbdd_ticket->bbdd_project_id)
            ->update(['deleted' => 1]);

        }
        return response()->json([
            'status'=>'success',
        ]);

    }
    /**
     * Este método ejecuta las automatizaciones para levantar un proyecto de base de datos
     * on-premises en base al id del proyecto que recibe como parámetro.
     * Para hacer esto ejecuta el script create-bbdd.py
     */
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
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd." ".$query->port);
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

    /**
     * Este método ejecuta las automatizaciones para levantar un proyecto de base de
     * datos en Google Cloud Platform a partir del id del proyecto que recibe como parámetro.
     * Para hacer esto ejecuta el script create-bbdd-gke.py
     */
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
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd-gke.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd." ".$query->port);
        //return $RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-bbdd.py" ' .$query->domain." ".$query->name." ".$query->memory." ".$query->dbname." ".$query->dbuser." ".$query->dbpwd;
        //if($result!="b'Created'"){Return $result;}
        if($result!="b'CreatedCreatedCreatedCreatedCreated'"){Return $result;}

        return "Successfull";
    }

    /**
     * Este método devuelve la información almacenada en la base de datos para un proyecto
     * de base de datos y el clúster en el que se ejecuta. Si el proyecto no es on-premises
     * ejecuta el script get-services-ip.py que devuelve la ip pública en la que se está
     * ejecutando el servicio con la base de datos en la nube.
     */
    public function get_bbdd_project(Request $request)
    {
        $user = auth('api')->user();
        //recuperamos la entrada del proyecto
        $query=bbdd_projects::select('bbdd_projects.*' , 'clusters.name AS cluster_name', 'clusters.domain','clusters.provider')
        ->join('clusters', 'bbdd_projects.cluster_id', '=', 'clusters.id')
        ->where('bbdd_projects.id', '=', $request->bbdd_project_id)
        ->where('bbdd_projects.workgroup_id', '=', $user->workgroup_id)
        ->first();

        //Si la ip es None quiere decir que es un servidor on premise que no cuenta con IP
        if($query->provider!="On-premises"){
            //recoger la IP del servidor BBDD
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

            $ip = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/get-services-ip.py" '. $query->domain." ". $query->name);
            $ip = substr($ip, 2);
            $ip = substr($ip, 0,-1);

            if(strlen($ip)<25){
                //Actualizamos la IP
                $query=bbdd_projects::where('bbdd_projects.id', '=', $request->bbdd_project_id)->update(['ip' => $ip]);

                //Volvemos a generar la llamada con la IP actualizada
                $query=bbdd_projects::select('bbdd_projects.*' , 'clusters.name AS cluster_name','clusters.domain')
                ->join('clusters', 'bbdd_projects.cluster_id', '=', 'clusters.id')
                ->where('bbdd_projects.id', '=', $request->bbdd_project_id)
                ->first();
            }
            else{
                //Ha devuelto un error por que no se ha asignado la IP
            }

        }


        return $query;
    }

    /**
     * Devuelve la salud del id del proyecto que recibe como parámetro haciendo uso del
     * script project-health.py que ejecuta las automatizaciones para recuperar el número
     * de réplicas que se están ejecutando para dicho proyecto en el clúster, y el número
     * esperado de réplicas.
     */
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

    /**
     * Crea un ticket donde se solicita el borrado de un proyecto junto con un log
     * donde se registra la petición.
     */
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

    /**
     * Ejecuta las automatizaciones para crear un backup de una base de datos en el clúster,
     * guardarla en el servidor back-end mediante la ejecución del script postgres-backup.py
     * Posteriormente devuelve el fichero con el backup en formato sql como respuesta a la petición.
     */
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

        $headers = array(
                  'Content-Type: application/sql',
                );

        return Response::download('../backup.sql', 'backup.sql', $headers);
    }
}
