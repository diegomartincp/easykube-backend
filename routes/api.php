<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController; //Para autenticar
use App\Http\Controllers\TodoController;
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

//Añado desde aquí

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');

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
});

Route::controller(TodoController::class)->group(function () {
    Route::get('todos', 'index');
    Route::post('todo', 'store');
    Route::get('todo/{id}', 'show');
    Route::put('todo/{id}', 'update');
    Route::delete('todo/{id}', 'destroy');

    Route::get('test', 'test_roles');
    Route::post('ejemplo', 'ejemplo');
});
