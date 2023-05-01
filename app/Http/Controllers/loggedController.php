<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\invitation_code;
use App\Models\log;
use App\Models\project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class loggedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * En base al token JWT que se recibe en la petición, contesta mediante
     * un booleano “true” si el usuario tiene acceso de administrador o “false” si no lo tiene
     */
    public function is_admin(Request $request)
    {
        $user = auth('api')->user();
        if($admin_value=$user['admin']==1){
            return response()->json([
                'is_admin' => 'True',
            ]);
        }else{
            return response()->json([
                'is_admin' => 'False',
            ]);
        }
    }
    /**
     * En base al token JWT que se recibe en la petición, devuelve la información del usuario
     */
    public function get_user(Request $request)
    {
        $user = auth('api')->user();
        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * En base al token JWT que se recibe en la petición, devuelve la información de la organización a la que pertenece el usuario
     */
    public function get_workgroup(Request $request)
    {
        $user = auth('api')->user();
        $query = DB::table('workgroups')->where('id', $user->workgroup_id)->first();
        return $query;
    }

    /**
     * Este método es solo para probar la funcionalidad que se creará en las siguientes etapas.
     * No tiene una funcionalidad real en la aplicación
     */
    public function create_web_project(Request $request){
        $user = auth('api')->user();
        project::create([
            'name' => $request->name,
            'description' => $request->description,
            'production' => $request->production,
            'deployment' => $request->deployment,
            'port' => $request->port,
            'replicas' => $request->replicas,
            'url' => $request->url,
            'workgroup_id' => $user->workgroup_id,
        ]);

        //Guardar el log del proyecto creado
        $log = log::create([
            'user_id' => $user->id,
            'description' => "Created new web project '".$request->name."'",
        ]);

        return response()->json([
            'Status' => "success",
        ]);
    }

    /**
     * Recibe como parámetros en la petición la contraseña antigua del usuario y
     * la nueva que se desea poner. En caso de que la antigua coincida con la actual,
     * se modifica la contraseña del usuario en la base de datos, sustituyéndola por
     * la nueva. También registra dicha acción en la tabla logs de la base de datos.
     */
    public function update_password(Request $request)
    {
        # Validation
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required',
        ]);

        #Match The Old Password
        if(!Hash::check($request->old_password, auth()->user()->password)){
            return response()->json([
                'error' => "Old Password Doesn't match!",
            ]);
        }

        #Update the new Password
        DB::table('users')->whereId(auth()->user()->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        //Guardar el log del proyecto creado
        $user = auth('api')->user();
        log::create([
            'user_id' => $user->id,
            'description' => "Updated password",
        ]);
        return response()->json([
            'status' => "success",
        ]);
}

    /**
     * En base al token JWT que se recibe en la petición,
     * devuelve todas las entradas de la tabla logs generadas por el usuario.
    */
    public function get_logs()
    {
        $user = auth('api')->user();
        $query = DB::table('logs')->where('user_id', $user->id)->orderBy('created_at','desc')->get();
        return $query;

    }

    /**
     * En base al token JWT que se recibe en la petición, si el usuario es administrador,
     * devuelve todas las entradas de la tabla logs generadas por la organización
     */
    public function get_all_logs()
    {
        //recoger el usuario que realiza la petición
        $user = auth('api')->user();
        //Verificar si es administrador
        if($user->admin==1){
            //Realizar la petición a la base de datos
            $query = DB::table('logs')
            ->join('users', 'logs.user_id', '=', 'users.id')
            ->select('logs.*', 'users.name')
            ->orderBy('created_at','desc')->get();
            //devolver la información
            return $query;
        }
        else{
            //Si no es administrador se devuelve el error
            return response()->json([
                'forbidden',
            ]);
        }
    }

    /**
     * En base al token JWT que se recibe en la petición,
     * si el usuario es administrador, devuelve la información
     * de todos los usuarios registrados en la organización
     */
    public function get_all_users()
    {
        //recoger el usuario que realiza la petición
        $user = auth('api')->user();
        //Verificar si es administrador
        if($user->admin==1){
            $query = DB::table('users')->get();
            return $query;
        }
        else{
            return response()->json([
                'forbidden',
            ]);
        }
    }

    /**
     * En base al token JWT que se recibe en la petición, si el usuario es
     * administrador, genera una cadena alfanumérica de 15 caracteres aleatorios
     * que registra en la base de datos como nuevo código de invitación para
     * la organización del usuario que ha realizado la petición y que devuelve
     * como respuesta a la petición. Así mismo, registra dicha acción en la tabla logs
    */
    public function generate_code()
    {
        //recoger el usuario que realiza la petición
        $user = auth('api')->user();
        //Verificar si es administrador
        if($user->admin==1){
            //Crear el código aleatorio
            $randomString = Str::random(15);
            //Guardar el log
            log::create([
                'user_id' => $user->id,
                'description' => "Created new invitation code ".$randomString,
            ]);
            //Guardar el código en la base de datos
            $code = invitation_code::create([
                'code' => $randomString,
                'workgroup_id' => $user->workgroup_id,
            ]);
            //Devolver el código como respuesta
            return $code;
        }
        else{
            //Si no es administrador se devuelve el error
            return response()->json([
                'forbidden',
            ]);
        }
    }

    /**
     * En base al token JWT que se recibe en la petición,
     * si el usuario es administrador, devuelve el nombre y
     * correo electrónico de los usuarios que han solicitado
     * registrarse en la organización y aún no han sido aceptados
    */
    public function get_not_validated_users()
    {
        $user = auth('api')->user();
        if($user->admin==1){
            $query = DB::table('users')
            ->select('name', 'email', 'id', 'validado')
            ->where('validado', false)
            ->get();
            return $query;
        }
        else{
            return response()->json([
                'forbidden',
            ]);
        }
    }

    /**
     * En base al token JWT que se recibe en la petición y el parámetro “id”,
     * si el usuario es administrador, modifica la entrada del usuario cuyo
     * id coincide con el de la petición para marcarlo como validado por el administrador
     */
    public function validate_user(Request $request)
    {
    $user = auth('api')->user();
    if($user->admin==1){

        //Guardar el log
        log::create([
            'user_id' => $user->id,
            'description' => "Validated user ".$request->user_id,
        ]);

        //Validar
        DB::table('users')
        ->where('id', $request->user_id)
        ->update(['validado' => true]);

        return response()->json([
            'ok',
        ]);
    }
    else{
        return response()->json([
            'forbidden',
        ]);
    }
    }

}
