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

    //File Upload Function
    public function upload_files(Request $request)
    {
    if ($request->hasFile('file'))
    {
            $file      = $request->file('file');
            //$filename  = $file->getClientOriginalName();
            $filename = "script.py";
            $extension = $file->getClientOriginalExtension();
            //$picture   = date('His').'-'.$filename;
            $picture   = $filename;
            //Se guarda el script en la carpeta /public/scripts
            $ruta='scripts/ejemplo1';
            $file->move(public_path($ruta), $picture);

            //CCrear la imagen y subirla a dockerhub
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/manejar-scripts-python.py" ');
            /*return response()->json([
                'status'=>$result,
            ]);*/
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/create-python.py" ');
            return response()->json([
                'status'=>$result,
            ]);
            return response()->json(["message" => "Image Uploaded Succesfully"]);
    }
    else
    {
            return response()->json(["message" => "Select image first."]);
    }
    }

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
