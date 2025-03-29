<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marcacion;
use Illuminate\Http\Request;
use App\ZKService\ZKLib;
use App\ZKService\ZKLibrary;
use Rats\Zkteco\Lib\ZKTeco;

class ZKLibraryController extends Controller
{
    private $zklib_main;

    private $marcacion_model;

    public function __construct() {
        $this->marcacion_model = new Marcacion();
    }    

    public function getUsers() {
        $users = $this->marcacion_model->getUsers();

        $usuarios = array();

        if(count($users))
        {

            foreach($users as $user)
            {
                if(config('zkteco.establecimiento_master')){
                    array_push($usuarios,[
                        'id' =>json_decode(utf8_encode($user['userid']),JSON_UNESCAPED_UNICODE),
                        'nombre' => $user['name'],
                    ]);
                }else{
                    array_push($usuarios,[
                        'id' =>json_decode(utf8_encode($user[0]),JSON_UNESCAPED_UNICODE),
                        'nombre' => $user[1],
                        'huella' => $user[2],
                        'pin' => $user[3],
                    ]);
                }

            }
        }
        //return  json_encode($usuarios,JSON_UNESCAPED_UNICODE);
        //dd($usuarios);
        // if($users == 404)
        // {
        //     abort(404);
        // }
        
        return response()->json($usuarios,200, array('Content-Type'=>'application/json; charset=utf-8' ));

    }

    public function getAttendances() {

        $attendances = $this->marcacion_model->getAttedances();
        
        if($attendances == 404)
        {
            abort(404);
        }
        
        return response()->json($attendances,200);
    }

    public function saveAttendandes() {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        $estado_save = $this->marcacion_model->saveAttendancesByAsc();

        if($estado_save == 404){
            abort(404);
        }

        if($estado_save == 1) {
            return "Marcaciones guardadas satisfactoriamente";
        }
    }

    public function obtenerMarcacionesApi()
    {
        $marcaciones_api= $this->marcacion_model->getAllAttendacesApi();
        return response()->json($marcaciones_api,200);
    }

    public function verificarDniPersonal(Request $request)
    {
        $marcaciones_api= $this->marcacion_model->verificarDniPersonal($request);

        return response()->json($marcaciones_api,200);
    }
}
