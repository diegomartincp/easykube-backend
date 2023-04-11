<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KubernetesPythonController extends Controller
{


    //File Upload Function
    public function uploadimage(Request $request)
    {
    //check file
    if ($request->hasFile('file'))
    {
            $file      = $request->file('file');
            $filename  = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $picture   = date('His').'-'.$filename;
            //move image to public/img folder
            $file->move(public_path('scripts'), $picture);
            return response()->json(["message" => "Image Uploaded Succesfully"]);
    }
    else
    {
            return response()->json(["message" => "Select image first."]);
    }
    }
}
