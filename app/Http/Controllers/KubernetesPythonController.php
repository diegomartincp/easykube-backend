<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KubernetesPythonController extends Controller
{


    //File Upload Function
    public function upload_files(Request $request)
    {
    if ($request->hasFile('file'))
    {
            $file      = $request->file('file');
            $filename  = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            //$picture   = date('His').'-'.$filename;
            $picture   = $filename;
            //Se guarda el script en la carpeta /public/scripts
            $ruta='scripts/ejemplo1';
            $file->move(public_path($ruta), $picture);

            //Crear requirements
            $RUTA_PYTHON='"'.env('RUTA_PYTHON').'"';
            $RUTA_CARPETA_LARAVEL=env('RUTA_CARPETA_LARAVEL');
            $result = exec($RUTA_PYTHON.' '.'"'.$RUTA_CARPETA_LARAVEL.'/Scripts/manejar-scripts-python.py" ');
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
