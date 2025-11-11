<?php

use App\Http\Controllers\Api\ZKLibraryController;
use App\ZKService\ZKLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/', function () {
    return app()->version();
});

Route::get('users',[ZKLibraryController::class,'getUsers']);

Route::group(['prefix' => 'attendances'],function() {
    Route::get('/',[ZKLibraryController::class,'getAttendances']);
    Route::get('version',[ZKLibraryController::class,'getversion'])->name('version');
    Route::get('metodos',[ZKLibraryController::class,'obtenerMetodos']);
    Route::get('api-all',[ZKLibraryController::class,'obtenerMarcacionesApi']);
});
