<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarSubirMarcaciones;
use App\Models\Marcacion;
use Illuminate\Http\Request;
use App\ZKService\ZKLib;
use App\ZKService\ZKLibrary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
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
    public function sincronizarLocalNube(Request $request){
        $reglas = [
            'desde' => 'required|date|before_or_equal:' . now()->toDateString(),
            'hasta' => 'required|date|after_or_equal:desde|before_or_equal:' . now()->toDateString(),
        ];
            $mensajes = [
            'desde.required' => 'El campo de fecha de inicio es obligatorio.',
            'desde.date' => 'La fecha de inicio debe ser una fecha válida.',
            'desde.before_or_equal' => 'La fecha de inicio no puede ser posterior a hoy.',
            
            'hasta.required' => 'El campo de fecha de fin es obligatorio.',
            'hasta.date' => 'La fecha de fin debe ser una fecha válida.',
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'hasta.before_or_equal' => 'La fecha de fin no puede ser posterior a hoy.',
        ];
        $validator = Validator::make($request->all(), $reglas, $mensajes);
        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'mensaje' => 'Error en los datos enviados.',
                'errores' => $validator->errors(),
            ], 422);
        }
        $desde = $request->desde;
        $hasta = $request->hasta;
        $marcaciones = Marcacion::where('fecha', '>', "$desde 00:00:00")
        ->where('fecha', '<=', "$hasta 23:59:59")
        ->get();
        $i=0;;
        foreach($marcaciones as $row){
            $ruta = config('app.api_url').'/api/guardar-marcaciones';
            if($row->numero_documento!= null){
                $response = Http::timeout(20)->post($ruta,[
                    'dni' => $row->numero_documento,
                    'uid' => $row->uid,
                    'estado' => $row->estado,
                    'fecha' => $row->fecha,
                    'tipo' => $row->tipo,
                    'serial' => $row->serial,
                    'ip' => $row->ip
                ]);            
                $i++;
            }
        }
        return response()->json([
            'success' => 1,
            'mensaje' => "Guardado satisfactoriamente, $i registros procesados"
        ],200);
    }
    public function saveAttendandes(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $reglas = [
            'desde' => 'required|date|before_or_equal:' . now()->toDateString(),
            'hasta' => 'required|date|after_or_equal:desde|before_or_equal:' . now()->toDateString(),
        ];
            $mensajes = [
            'desde.required' => 'El campo de fecha de inicio es obligatorio.',
            'desde.date' => 'La fecha de inicio debe ser una fecha válida.',
            'desde.before_or_equal' => 'La fecha de inicio no puede ser posterior a hoy.',
            
            'hasta.required' => 'El campo de fecha de fin es obligatorio.',
            'hasta.date' => 'La fecha de fin debe ser una fecha válida.',
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'hasta.before_or_equal' => 'La fecha de fin no puede ser posterior a hoy.',
        ];
        $validator = Validator::make($request->all(), $reglas, $mensajes);
        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'mensaje' => 'Error en los datos enviados.',
                'errores' => $validator->errors(),
            ], 422);
        }
        $desde = $request->desde;
        $hasta = $request->hasta;
        $estado_save = $this->marcacion_model->saveAttendancesByAsc($desde, $hasta);
        if($estado_save == -1){
            abort(404);
        }
        if($estado_save >= 0) {
            return response()->json([
                'success' => 1,
                'mensaje' => "Guardado satisfactoriamente, $estado_save registros procesados"
            ],200);
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
