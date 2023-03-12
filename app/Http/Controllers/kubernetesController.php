<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\cluster;
use App\Models\web_project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use stdClass;

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
                        'name' => $request->name,
                        'domain' => $request->domain,
                        'description' => $request->description,
                        'type' => $request->type,
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

    public function get_clusters(Request $request)
    {
        $user = auth('api')->user();
        $query = DB::table('clusters')
                ->select('id', 'name' ,'domain','type', 'description')
                ->where('workgroup_id', $user->workgroup_id)
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

                //Verificar si la carga de trabajo es un proyecto
                $nombre = explode("-deployment", $deployment->Name)[0];
                $query = web_project::where('name', '=', $nombre )->first();

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
                }

                $escritura->$flag = $temp;
                $flag=$flag+1;

            }


        }

        return json_decode(json_encode($escritura), true);
    }

    public function solicitar_web_project(Request $request)
    {
        #AÑADIR EN BBDD
        $user = auth('api')->user();
        web_project::create([
            'name'=>$request->name,
            'description'=>$request->description,
            'email'=>$request->email,
            'prod'=>$request->prod,
            'token'=>$request->token,
            'url'=>$request->url,
            'ipname'=>$request->ipname,
            'cluster_ip'=>$request->cluster_ip,
            'dns'=>$request->dns,
            'aproved'=>False,
            'workgroup_id'=>$user->workgroup_id,
            'cluster_id'=>$request->cluster_id
        ]);
        return "Successfull";

    }
    public function ver_web_solicitados(Request $request)
    {
        $user = auth('api')->user();
        $query = web_project::where('aproved', '=', False )->where('workgroup_id', '=', $user->workgroup_id )->get();;
        return $query;
    }

    public function deploy_web_project(Request $request)
    {
        //Ver que proyecto es en la base de datos
        $user = auth('api')->user();
        $query = web_project::where('aproved', '=', False )
        ->where('workgroup_id', '=', $user->workgroup_id )
        ->where('id', '=', $request->id )
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
        #return $result;

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

        #Crear proyecto web
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-web.py" ' . $cluster_ip." ".$query->name." ".$query->url." ".$query->token);
        if($result!="b'CreatedCreated'"){Return "Error creating project";}

        #Crear ingress
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/ingress-ssl.py" ' . $cluster_ip." ".$query->name." ".$query->ipname." ".$query->dns);
        if($result!="b'Created'"){Return "Error creating ingress";}


        //Validar
        DB::table('web_projects')
        ->where('id', $request->id)
        ->update(['aproved' => true]);

        #FIN
        return "Successfull";
    }

}
