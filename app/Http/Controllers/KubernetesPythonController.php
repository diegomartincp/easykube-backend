<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\cluster;
use App\Models\log;
use App\Models\python_project;
use App\Models\python_ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class KubernetesPythonController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Este método permite a un usuario crear un ticket donde se solicita la creación de un proyecto
     * de microservicios con Python
     * También se registra un nuevo proyecto de base de datos con sus características
     * y un log de la realización de la solicitud.
     */
    public function solicitar_python(Request $request)
    {
    //Recoger el usuario que hace la petición
    $user = auth('api')->user();

    //1 Buscar el id del cluster
    $cluster = cluster::where('name', '=', $request->get('cluster'))->
    where('workgroup_id', '=', $user->workgroup_id )->first();

    if($cluster==null){
        return response()->json([
            'status' => 'cluster not found',
        ]);
    }

    //2 Vemos si el proyecto ya existe
    $query = python_project::where('name', '=', $request->get('name') )
    ->where('cluster_id', '=', $cluster->id )
    ->where('deleted', '=', 0 )
    ->first();

    if($query!=null){
        //Si existe se devuelve el error
        return response()->json([
            'status' => 'exists',
        ]);
    }

    //3 Antes de nada hay que verificar si la request contiene el fichero con el script
    if ($request->hasFile('file')){
        //Guardar el fichero
        $file      = $request->file('file');
        $filename = "script.py";
        $ruta='scripts/ejemplo1';
        $file->move(public_path($ruta), $filename);

        //CCrear la imagen y subirla a dockerhub
        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/manejar-scripts-python.py" '.$request->get('name')." ".$user->workgroup_id." script.py");
    }else{
        //Si no contiene el fichero, se devuelve el error
        return response()->json(["status" => "No file"]);
    }

        //Una vez se crea la imagen correctamente, pasamos a crear la solicitud

        //3 Añadir en la bbdd el proyecto el nuevo proyecto
        python_project::create([
            'name'=>$request->get('name'),
            'description'=>$request->get('description'),
            'aproved'=>False,
            'replicas'=>$request->get('replicas'),
            'port'=>$request->get('port'),
            'workgroup_id'=>$user->workgroup_id,
            'cluster_id'=>$cluster->id
        ]);

        //4 Ver cual es el id del proyecto que se acaba de crear
        $proyecto = python_project::where('name', '=', $request->get('name') )->where('cluster_id', '=', $cluster->id )->first();

        //5 Crear el ticket con la solicitud
        python_ticket::create([
            'action' => 0, //0 Crear //1 Replicas //2 Borrar
            'description' => "Create python project ".$request->get('name'),
            'user_id' => $user->id,
            'python_project_id' => $proyecto->id,
        ]);

        //6 Guardar el log del proyecto creado
        log::create([
            'user_id' => $user->id,
            'description' => "Requested new python project '".$request->get('name')."'",
        ]);
        //7 Devolver el mensaje indicando que se ha completado
        return response()->json([
            'status' => 'success',
        ]);
    }
/**
 * Este método devuelve un array con todos los tickets de proyectos de microservicios
 * con Python que aún no han sido ni aceptados ni rechazados.
 */
    public function get_python_tickets(Request $request)
    {
        //recuperar el usuario que realiza la petición
        $user = auth('api')->user();
        //Controlar si as administrador
        if($user->admin==1){
            //Petición a la base de datos para recuperar todos los tickets no manejados
            $query = python_ticket::join('users', 'python_tickets.user_id', '=', 'users.id')
            ->select('python_tickets.*', 'users.name')
            ->where('accepted', 0)
            ->where('declined', 0)
            ->where('users.workgroup_id', '=', $user->workgroup_id )
            ->orderBy('created_at','desc')->get();
            return $query;
        }
        else{
            //Si no es administrador devolver el error
            return response()->json([
                'forbidden',
            ]);
        }
    }

    /**
     * Este método se encarga de realizar las gestiones necesarias cuando el administrador
     * acepta un ticket de un proyecto de microservicios con Python. Se fija en el campo “action”
     * del ticket y según su valor realiza la acción correspondiente:
     * 0.	Es un ticket para crear un nuevo proyecto. Ejecuta las automatizaciones que levantan las cargas de trabajo.
     * 1.	Es un ticket para actualizar el número de réplicas mínimo para el proyecto
     * 2.	Es un ticket para eliminar un proyecto. Se ejecuta la automatización que lo elimina y se marca en la entrada del proyecto como no activo
     * 3.	Es un ticket para actualizar el script Python que se ejecuta en el microservicio. Ejecuta las automatizaciones que actualizan la imagen con la nueva versión, la sube a Docker Hub y reinicia los pods para que se vuelvan a crear con la nueva versión.
     * En cualquiera de los casos se crea también un log recogiendo el cambio realizado.
     */
    public function accept_python_tickets(Request $request)
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
        $python_ticket = python_ticket::where('id', $request->python_ticket_id)
        ->first();

        if($python_ticket==null){
            return response()->json([
                'status'=>"Python ticket dont exist",
            ]);
        }

        //recogemos el proyecto y el cluster
        $python_project = python_project::select("python_projects.*","clusters.domain")->where('python_projects.id',"=", $python_ticket->python_project_id)
        ->join("clusters","python_projects.cluster_id","clusters.id")->first();

        //ACTION 0 - Crear proyecto
        if($python_ticket->action==0){
            //Tenemos la dirección del cluster
            $cluster_ip = $python_project->domain;

            //El nombre de la imagen es el nombre del proyecto - workgroup
            $nombre_imagen=$python_project->name."-".$user->workgroup_id;

            //Coger variables
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

            //Crear deployment
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-python.py" '.$cluster_ip." ".$python_project->name." ".$nombre_imagen." ".$python_project->port);
            //Ver si hay error al crear el deployment
            if($result!="b'CreatedCreated'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }
            //Crear HPA para el autoescalado
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-hpa.py" ' . $cluster_ip." ".$python_project->name." ".$python_project->replicas);
            //Ver si hay error al crear el HPA
            if($result!="b'Created'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }

            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el proyecto a aprovado
            python_project::where('id', $python_ticket->python_project_id)
            ->update(['aproved' => true]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$python_ticket->description."'",
            ]);
        }

        //ACTION 1 - Actualizar réplicas
        if($python_ticket->action==1){
            //Tenemos la dirección del cluster
            $cluster_ip = $python_project->domain;

            //Actualizar las replicas
            #Coger variables
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/update-replicas.py" '.$cluster_ip." ".$python_project->name." ".$python_ticket->replicas);
            if($result!="b'success'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }

            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el web project
            python_project::where('id', $python_ticket->python_project_id)
            ->update(['replicas' => $python_ticket->replicas]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$python_ticket->description."'",
            ]);
        }

        //ACTION 0 - Borrar el proyecto
        if($python_ticket->action==2){

            //Tenemos la dirección del cluster
            $cluster_ip = $python_project->domain;

            //Ahora que sabemos que proyecto es
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/delete-project.py" '.$cluster_ip." ".$python_project->name);

            //Controlar si el script ha funcionado correctamente
            if($result!="b'ok'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$python_ticket->description."'",
            ]);

            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
            ->update(['accepted' => true]);

            //Actualizar el proyecto como borrado
            python_project::where('id', $python_ticket->python_project_id)
            ->update(['deleted' => true]);

        }

        //ACTION 3 - Actualizar la imagen del miscroservicio
        if($python_ticket->action==3){
            //Crear la imagen y subirla a dockerhub
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/manejar-scripts-python.py" '.$python_project->name." ".$user->workgroup_id." ".$python_ticket->id."-script.py");

            //Achora hay que reiniciar los pods para que carguen la nueva imagen
            $cluster_ip = $python_project->domain;
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/restart-pods.py" '.$cluster_ip." ".$python_project->name);

            if($result!="b'success'"){
                return $result;
            }

            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
            ->update(['accepted' => true]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$python_ticket->description."'",
            ]);
        }

        //Tras terminar cualquiera de los casos se indica al usuario
        return response()->json([
            'status'=>'success',
        ]);

    }

    /**
     * Este método rechaza un ticket de un proyecto de microservicios con Python
     * alterando el atributo “declined” en la base de datos.
     * Si la petición es de crear un proyecto, al eliminarse se elimina también
     * la entrada en la tabla python_projects
     * Si la petición es para actualizar el script del microservicio, se elimina
     * también el fichero subido con dicho script
     */
    public function delete_python_tickets(Request $request)
    {
        //Recoger el usuario que realiza la petición
        $user = auth('api')->user();

        //Verificar si el usuario es o no administrador
        if($user->admin!=1){
            return response()->json([
                'forbidden',
            ]);
        }

        //si el usuario es admin puede hacer cosas
        //Recoger que ticket es
        $query = python_ticket::where('id', $request->python_ticket_id)
        ->first();

        //Si no se encuentra ese ticket, se devuelve el error
        if($query==null){
            return response()->json([
                'status'=>"Python ticket dont exist",
            ]);
        }

        //Si es un ticket de crear el proyecto
        if($query->action==0){
            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
            ->update(['declined' => true]);

            //Actualizar el proyecto para eliminarlo pues no se ha llegado a ejecutar
            python_project::where('id', $query->python_project_id)
            ->delete();

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Deleted ticket: '".$query->description."'",
            ]);
        }
        //Si es la actualiazción del script hay que borrar el fichero del servidor
        else if($query->action==3){
            //Eliminar el fichero
            File::delete("scripts/ejemplo1/".$query->id."-script.py");

            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
            ->update(['declined' => true]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Deleted ticket: '".$query->description."'",
            ]);
        }
        //Si es otro caso, solo se elimina el ticket
        else{
            //Actualizar la peticion
            python_ticket::where('id', $request->python_ticket_id)
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
     * Este método devuelve la información almacenada en la base de datos para un proyecto
     * de micro servicios Python y el clúster en el que se ejecuta.
     * Si el proyecto ha sido aprobado, ejecuta el script get-services-ip.py que devuelve
     * la ip pública en la que se está ejecutando el servicio con la base de datos en la nube. Se hace con una estructura try-catch pues justo tras crearse hay un periodo de 30 segundos en el que Google asigna la IP y aún no está disponible.

     */
    public function get_python_project(Request $request)
    {
        //Recuperar el usuario que realiza la petición
        $user = auth('api')->user();

        //recuperamos la entrada del proyecto
        $query=python_project::select('python_projects.*' , 'clusters.name AS cluster_name','clusters.domain')
        ->join('clusters', 'python_projects.cluster_id', '=', 'clusters.id')
        ->where('python_projects.id', '=', $request->python_project_id)
        ->where('python_projects.workgroup_id', '=', $user->workgroup_id)
        ->first();

        //Si el proyecto ha sido aprovado se recupera la IP
        if($query->aproved==true){
            //Intentamos recuperar la ip
            try {
                //recoger la IP del servidor python
                $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
                $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

                $ip = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/get-services-ip.py" '. $query->domain." ". $query->name);
                $ip = substr($ip, 2);
                $ip = substr($ip, 0,-1);
                //Actualizamos la IP
                $query=python_project::where('python_projects.id', '=', $request->python_project_id)->update(['ip' => $ip]);

                //Volvemos a generar la llamada con la IP actualizada
                $query=python_project::select('python_projects.*' , 'clusters.name AS cluster_name','clusters.domain')
                ->join('clusters', 'python_projects.cluster_id', '=', 'clusters.id')
                ->where('python_projects.id', '=', $request->python_project_id)
                ->first();
            } catch (\Throwable $th) {
                //En caso de no poderse recuperar la IP, por que Google aún no la ha asignado, se devuelve el siguiente mensaje
                return response()->json([
                    'status'=>'pending',
                ]);
            }
        }
        //Devuelve los datos del proyecto
        return $query;
    }

    /**
     * Devuelve la salud del id del proyecto que recibe como parámetro haciendo uso del script project-health.py que ejecuta las
     * automatizaciones para recuperar el número de réplicas que se están ejecutando para dicho proyecto en el clúster, junto con
     * el número esperado de réplicas.
     */
    public function python_project_health(Request $request)
    {
        $user = auth('api')->user();
        //Recogemos la información del proyecto
        $python_project=python_project::select('python_projects.*','clusters.domain')
        ->join('clusters', 'python_projects.cluster_id', '=', 'clusters.id')
        ->where('python_projects.id', $request->python_project_id)
        ->where('python_projects.workgroup_id', $user->workgroup_id)
        ->first();

        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/project-health.py" '. $python_project->domain." ". $python_project->name);
        $result = substr($result, 2);
        $result = substr($result, 0,-1);

        return $result;
    }

    //Esta función CREA UN TICKET para modificar el número de réplicas
    public function apply_update_python_replicas(Request $request)
    {
        //Recuperar el usuario que realiza la petición
        $user = auth('api')->user();
        #Se crea el ticket
        python_ticket::create([
            'action' => 1, //0 Crear //1 Replicas //2 Borrar //3 Actualizar imagen
            'replicas' => $request->replicas,
            'description' => "Update replicas up to ".$request->replicas." for project '".$request->project_name."'",
            'user_id' => $user->id,
            'python_project_id' => $request->python_project_id,
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

    /**
     * Crea un ticket para solicitar la actualización del script del microservicio que se ofrece en un proyecto y sube al
     * servidor back-end el fichero con el script nuevo que se desea utilizar
     */
    public function solicitar_actualizacion_imagen(Request $request){
        $user = auth('api')->user();

        //Recoger el proyecto en base a su id
        $python_project=python_project::select('*')
        ->where('python_projects.id', $request->python_project_id)
        ->where('python_projects.workgroup_id', $user->workgroup_id)
        ->first();

        //Si contiene imagen
        if ($request->hasFile('file')){
            //Recuperar el usuario que realiza la petición
            $user = auth('api')->user();
            //Crear nuevo ticket solicitando la actualización
            $ticket_creado=python_ticket::create([
                'action' => 3, //0 Crear //1 Replicas //2 Borrar //3 Actualizar imagen
                'replicas' => 0,
                'description' => "Update python sript to new version for project '".$python_project->name."'",
                'user_id' => $user->id,
                'python_project_id' => $request->python_project_id,
            ]);
            //Se crea un log
            log::create([
                'user_id' => $user->id,
                'description' => "Requested update python script to a new version for project '".$python_project->name."'",
            ]);

            //Guardar el fichero
            $file      = $request->file('file');
            $filename = $ticket_creado->id."-script.py";
            $extension = $file->getClientOriginalExtension();
            $picture   = $filename;
            $ruta='scripts/ejemplo1';
            $file->move(public_path($ruta), $picture);

            return response()->json(["status" => 'success']);

        }else{
            //Si no contiene el fichero, se devuelve el error
            return response()->json(["status" => "No file"]);
        }
    }

/**
 * Crea un ticket donde se solicita el borrado de un proyecto, junto con un log donde se registra la petición.
 */
    public function apply_delete_python_project(Request $request)
    {
    //Recoger el usuario que realiza la petición
    $user = auth('api')->user();

    //Recoger el proyecto sobre el que se desea hacer el cambio
    $python_project=python_project::where('id', $request->python_project_id)
    ->where('workgroup_id', $user->workgroup_id)
    ->first();

    //Crear el nuevo ticket
    python_ticket::create([
        'action' => 2, //0 Crear //1 Replicas //2 Borrar //3 Actualizar imagen
        'description' => "Delete project '".$python_project->name."'",
        'user_id' => $user->id,
        'python_project_id' => $request->python_project_id,
    ]);

    //Guardar el log del proyecto creado
    log::create([
        'user_id' => $user->id,
        'description' => "Requested to delete python project '".$python_project->name."'",
    ]);

    //Devolver el ok
    return response()->json([
        'status'=>'success',
    ]);
    }
}
