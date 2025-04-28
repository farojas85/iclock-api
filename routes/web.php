<?php

use App\Http\Controllers\Api\ZKLibraryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('deleyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
function (){
    return view('eliminardata');
})->name('eliminardata');
Route::post('/guardar-marcaciones',[ZKLibraryController::class,'saveAttendandes']);
Route::post('/sincronizar-marcaciones',[ZKLibraryController::class,'sincronizarLocalNube']);
Route::post('/eliminar-datos-biometrico', [ZKLibraryController::class, 'eliminarDatosBiometrico']);