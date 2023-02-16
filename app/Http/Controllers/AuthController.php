<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

use Session;

class AuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','register']]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        $credentials = $request->only('email', 'password');

        $token = Auth::attempt($credentials);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = Auth::user();

        //Si no está validado no puedes hacer login
        if(!$user->validado==1){
            return response()->json([
                'validated' => false,
                ]
            );
        }

        return response()->json([
                'status' => 'success',
                'user' => $user,
                'authorisation' => [
                    'token' => $token,
                    'type' => 'bearer',
                ]
            ]);

    }

    public function register(Request $request){
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'workgroup_id' => 'required|integer' //Necesitamos que haya workgroup
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'workgroup_id'=> $request->workgroup_id,

        ]);
        //Guardar el log
        log::create([
            'user_id' => $user->id,
            'description' => "Applyed for registration",
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
        ]);

        //El registro ya no hace login, hay que validarlo por el admin

        /*
        //Si no está validado no puedes hacer login
        if(!$user->validado==1){
            return response()->json([
                'validated' => false,
                ]
            );
        }

        $token = Auth::login($user);
        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => $user,
            'authorisation' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
        */
    }

    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }
    public function session_exists(){
        if ($user = auth('api')->user()){
            // do some thing if the key is exist
            return response()->json([
                'exists' => 'true',
            ]);
          }else{
            //the key does not exist in the session
            return response()->json([
                'exists' => 'false',
            ]);
          }
    }

}
