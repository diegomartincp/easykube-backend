<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\cluster;
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
                        'description' => $request->description,
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
                ->select('id', 'domain', 'description')
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
        $health_json = new stdClass();

        for ($i=0; $i < $elementCount; $i++) {
            //Hacer algo con cada cluster
            $response = Http::get($clusters[$i]->domain.'/get_pods_health');

            //$response_json = json_encode(str($response));


            $health_json->$i = json_decode(str($response));

        }
        //$health_json = json_encode($health_json);

        return $health_json ;
    }

    public function deploy_web_project(Request $request)
    {
        #Coger variables
        $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
        $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');

        #Crear secreto
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/empty-secret.py" ' . $request->domain." ".$request->name);

        //if($result!="b'Created'"){Return $result;}

        if($result!="b'Created'"){Return "Error creating secret";}
        #return $result;

        #Crear issuer
        if($request->env=="prod"){
            #Crear issuer producción
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/issuer-prod.py" ' . $request->domain." ".$request->name." ".$request->email);
            if($result!="b'Created'"){Return "Error creating prod-issuer";}
        }
        else{
            #Crear issuer stagging
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/issuer-stagging.py" ' . $request->domain." ".$request->name." ".$request->email);
            if($result!="b'Created'"){Return "Error creating stagging-issuer";}
        }

        #Crear proyecto web
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-web.py" ' . $request->domain." ".$request->name." ".$request->url." ".$request->token);
        if($result!="b'CreatedCreated'"){Return "Error creating project";}

        #Crear ingress
        $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/ingress-ssl.py" ' . $request->domain." ".$request->name." ".$request->ipname." ".$request->dns);
        if($result!="b'Created'"){Return "Error creating ingress";}

        #FIN
        return "Successfull";
    }



}
