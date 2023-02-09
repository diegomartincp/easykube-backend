<?php

namespace App\Http\Controllers;

//use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
//use Illuminate\Foundation\Bus\DispatchesJobs;
//use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    //use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function validate_code(Request $request)
    {
        $query = DB::table('invitation_codes')->where('code', $request->code)->first();
        return $query;
    }

    public function existe_usuario(Request $request)
    {
        $query = DB::table('users')->where('email', $request->email)->first();
        if(!empty($query)){
            return response()->json([
                'Exists' => 'True',
            ]);
        }else{
            return response()->json([
                'Exists' => 'False',
            ]);
        }
    }
}
