<?php

use App\Http\Controllers\Api\ZKLibraryController;
use App\ZKService\ZKLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

Route::get('test', function () {
    $nombre = "h.dat";

    // 🔹 Array de prueba
    $marcaciones = [
        ['uid' => 1, 'numero_documento' => '12345678', 'fecha' => '2025-11-11 08:00:00', 'tipo' => 'E'],
        ['uid' => 1, 'numero_documento' => '12345678', 'fecha' => '2025-11-11 13:00:00', 'tipo' => 'S'],
        ['uid' => 2, 'numero_documento' => '87654321', 'fecha' => '2025-11-11 08:15:00', 'tipo' => 'E'],
        ['uid' => 2, 'numero_documento' => '87654321', 'fecha' => '2025-11-11 12:55:00', 'tipo' => 'S'],
    ];

    // 🔹 Convertir array a texto (una línea por registro)
    $lineas = array_map(function ($m) {
        return implode(';', $m); // uid;numero_documento;fecha;tipo
    }, $marcaciones);

    $contenido = implode("\r\n", $lineas) . "\r\n";

    // 🔹 Guardar en el disco configurado como 'marcaciones'
    Storage::disk('marcaciones')->put($nombre, $contenido);

    return "Archivo '$nombre' creado correctamente en el disco 'marcaciones'.";
});

Route::get('metodos',[ZKLibraryController::class,'obtenerMetodos']);

Route::group(['prefix' => 'attendances'],function() {
    Route::get('/',[ZKLibraryController::class,'getAttendances']);
    Route::get('version',[ZKLibraryController::class,'getversion'])->name('version');
    Route::get('api-all',[ZKLibraryController::class,'obtenerMarcacionesApi']);
});
