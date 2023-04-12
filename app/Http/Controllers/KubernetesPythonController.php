<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\cluster;
use App\Models\log;
use App\Models\python_project;
use App\Models\python_ticket;
use Illuminate\Http\Request;

class KubernetesPythonController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function solicitar_python(Request $request)
    {
    //Recoger el usuario que hace la petición
    $user = auth('api')->user();

    //Antes de nada hay que verificar si la request contiene el fichero con el script
    if ($request->hasFile('file')){
        $file      = $request->file('file');
        $filename = "script.py";
        $extension = $file->getClientOriginalExtension();
        $picture   = $filename;
        $ruta='scripts/ejemplo1';
        $file->move(public_path($ruta), $picture);

        //CCrear la imagen y subirla a dockerhub
        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/manejar-scripts-python.py" '.$request->get('name')." ".$user->workgroup_id);
    }else{
        //Si no contiene el fichero, se devuelve el error
        return response()->json(["status" => "No file"]);
    }

        //Una vez se crea la imagen correctamente, pasamos a crear la solicitud

        //1 Buscar ese cluster que id tiene
        $cluster = cluster::where('name', '=', $request->get('cluster'))->
        where('workgroup_id', '=', $user->workgroup_id )->first();

        if($cluster==null){
            return response()->json([
                'status' => 'cluster not found',
            ]);
        }

        //2 Vemos si el proyecto ya existe
        $query = python_project::where('name', '=', $request->get('name') )->where('cluster_id', '=', $cluster->id )->first();

        if($query!=null){
            //Si existe se devuelve el error
            return response()->json([
                'status' => 'exists',
            ]);
        }

        //3 AÑADIR EN BBDD el nuevo proyecto
        python_project::create([
            'name'=>$request->get('name'),
            'description'=>$request->get('description'),
            'aproved'=>False,
            'replicas'=>$request->get('replicas'),
            'workgroup_id'=>$user->workgroup_id,
            'cluster_id'=>$cluster->id
        ]);

        //4 Ver cual es el id del proyecto que se acaba de crear
        $proyecto = python_project::where('name', '=', $request->get('name') )->where('cluster_id', '=', $cluster->id )->first();

        //5 Crear el ticket con la solicitud
        python_ticket::create([
            'action' => 0, //0 Crear //1 Replicas //2 Borrar
            'description' => "Create python project project ".$request->get('name'),
            'user_id' => $user->id,
            'python_project_id' => $proyecto->id,
        ]);

        //Guardar el log del proyecto creado
        log::create([
            'user_id' => $user->id,
            'description' => "Requested new python project '".$request->get('name')."'",
        ]);

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function get_python_tickets(Request $request)
    {
        $user = auth('api')->user();
        if($user->admin==1){
            $query = python_ticket::join('users', 'python_tickets.user_id', '=', 'users.id')
            ->select('python_tickets.*', 'users.name')
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

        //Crear proyecto
        if($python_ticket->action==0){
            //Tenemos la dirección del cluster
            $cluster_ip = $python_project->domain;

            //El nombre de la imagen es el nombre del proyecto - workgroup
            $nombre_imagen=$python_project->name."-".$user->workgroup_id;

            //Coger variables
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

            //Crear deployment
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-python.py" '.$cluster_ip." ".$python_project->name." ".$nombre_imagen);
            //Ver si hay error al crear el HPA
            if($result!="b'CreatedCreated'"){
                return response()->json([
                    'status'=>$result,
                ]);
            }
            //Crear HPA
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

            //Actualizar el web project
            python_project::where('id', $python_ticket->python_project_id)
            ->update(['aproved' => true]);

            //Crear log
            log::create([
                'user_id' => $user->id,
                'description' => "Ticket accepted: '".$python_ticket->description."'",
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
        /*
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
        */
        return response()->json([
            'status'=>'success',
        ]);

    }

    public function delete_python_tickets(Request $request)
    {
        $user = auth('api')->user();
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

        //Si es un ticket de crear un nuevo proyecto de BBDD
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

    public function get_python_project(Request $request)
    {
        $user = auth('api')->user();
        //recuperamos la entrada del proyecto
        $query=python_project::select('python_projects.*' , 'clusters.name AS cluster_name','clusters.domain')
        ->join('clusters', 'python_projects.cluster_id', '=', 'clusters.id')
        ->where('python_projects.id', '=', $request->python_project_id)
        ->where('python_projects.workgroup_id', '=', $user->workgroup_id)
        ->first();
        if($query->aproved==true){
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
        }

        return $query;
    }

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
    //aaaaaaaaaaaaaaaaaaaaaaaa
    public function crear_imagen(string $filename,string $ruta)
    {
        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/manejar-scripts-python.py" ');
        return response()->json([
            'status'=>$result,
        ]);

    }
}
