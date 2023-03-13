<?php

use App\Http\Controllers\kubernetesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController; //Para autenticar
use App\Http\Controllers\Controller;
use App\Http\Controllers\loggedController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
    //Route::post('refresh', 'refresh');
    //Route::post('session_exists', 'session_exists');
});

Route::controller(Controller::class)->group(function () {
    Route::post('validate_code', 'validate_code');
    Route::post('user_exists', 'existe_usuario');
});

Route::controller(loggedController::class)->group(function () {
    Route::get('is_admin', 'is_admin');
    Route::get('get_user', 'get_user');
    Route::post('create_web_project', 'create_web_project');
    Route::post('update_password', 'update_password');
    Route::get('get_workgroup', 'get_workgroup');
    Route::get('get_logs', 'get_logs');
    Route::get('get_all_logs', 'get_all_logs');
    Route::get('get_all_users', 'get_all_users');
    Route::get('generate_code', 'generate_code');
    Route::post('validate_user', 'validate_user');
    Route::get('get_not_validated_users', 'get_not_validated_users');
});

Route::controller(kubernetesController::class)->group(function () {
    Route::get('add_cluster', 'add_cluster');
    Route::get('get_clusters', 'get_clusters');
    Route::get('deploy_web_project', 'deploy_web_project');
    Route::get('get_health', 'get_health');
    Route::post('solicitar_web_project', 'solicitar_web_project');
    Route::get('ver_web_solicitados', 'ver_web_solicitados');
    Route::get('get_web_tickets', 'get_web_tickets');
    Route::post('accept_web_tickets', 'accept_web_tickets');
});
