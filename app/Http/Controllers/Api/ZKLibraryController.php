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
    public function obtenerMetodos(){
        return $this->marcacion_model->getMethods();
    }
    public function saveAttendancesOtros($desde, $hasta){
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $hasta = Carbon::parse($hasta)->endOfDay();
        $desde = Carbon::parse($desde);
        $res = $this->zklib->connect();
        $i=0;
        if($res)
        {
            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = 'potraca';
            //$serialSub = substr($this->zklib->serialNumber(), 14);
            $serial = substr($serialSub, 0, -1);
            $this->zklib->disconnect();

            if(count($attendances) > 0) 
            {
                foreach ($attendances as $attItem) {
                    $attendanceDate = Carbon::parse($attItem[3])->toDateTimeString();
                    if ($attendanceDate >= $desde->toDateTimeString() &&  $attendanceDate <= $hasta->toDateTimeString()) {
                        $numeroDocumento = str_pad($attItem[1], 8, '0', STR_PAD_LEFT);
                        $exists = Marcacion::where('numero_documento', $numeroDocumento)
                            ->where('fecha', $attendanceDate)
                            ->exists();
                        if (!$exists) {
                            $marcacion = new Marcacion();
                            $marcacion->uid = $attItem[0]; // ID
                            $marcacion->numero_documento = $numeroDocumento;
                            $marcacion->tipo = $attItem[2]; // Tipo
                            $marcacion->fecha = $attendanceDate;
                            $marcacion->estado = $attItem[4]; // Estado
                            $marcacion->serial = $serial;
                            $marcacion->ip = config('zkteco.ip');
                            $marcacion->save();
                            $i++;
                            $payload[] = [
                                'dni' => $numeroDocumento,
                                'uid' => $attItem[0],
                                'estado' => $attItem[4],
                                'fecha' => $attendanceDate,
                                'tipo' => $attItem[2],
                                'serial' => $serial,
                                'ip' => config('zkteco.ip'),
                            ];
                        }
                    }
                }
                if (!empty($payload)) { 
                    $ruta = config('app.api_url') . '/api/guardar-marcaciones-lote';

                    try {
                        $response = Http::timeout(120)->post($ruta, [
                            'marcaciones' => $payload
                        ]);

                        if (!$response->successful()) {
                            // Log o manejo de error si la API responde con error
                            \Log::error('Error al enviar marcaciones', ['response' => $response->body()]);
                        }
                    } catch (\Exception $e) {
                        // Capturar errores de conexión u otros
                        \Log::error('Excepción al enviar marcaciones: ' . $e->getMessage());
                    }
                }
            }
            return $i;         
        }
        return -1;
    }
    public function getUsers() {
        $users = $this->marcacion_model->getUsers();

        return $users;


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
        return response()->json($usuarios,200, array('Content-Type'=>'application/json; charset=utf-8' ));
    }
    public function getversion(){
        $response = $this->marcacion_model->getversion();
        $data = $response->getData(true);
        return view('info', compact('data'));

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
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
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

        $marcaciones = Marcacion::whereBetween('fecha', ["$desde 00:00:00", "$hasta 23:59:59"])
            ->whereNotNull('numero_documento')
            ->get(['numero_documento','uid','estado','fecha','tipo','serial','ip']);
        $rutaLote = config('app.api_url').'/api/guardar-marcaciones-lote';
        $insertadasTotal = 0;
        // Enviar por lotes para no reventar el payload
        foreach ($marcaciones->chunk(1000) as $chunk) {
            $payload = [
                'marcaciones' => $chunk->map(function ($m) {
                    return [
                        'dni'    => $m->numero_documento,
                        'uid'    => $m->uid,
                        'estado' => $m->estado,
                        'fecha'  => $m->fecha,   // asegúrate del formato que espera el API
                        'tipo'   => $m->tipo,
                        'serial' => $m->serial,
                        'ip'     => $m->ip,
                    ];
                })->values()->all()
            ];

            $response = Http::timeout(120)->retry(3, 1000)->post($rutaLote, $payload);

            if ($response->successful()) {
                $insertadasTotal += (int) $response->json('insertadas', 0);
            } else {
                Log::error('Error sync marcaciones', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        }

        return response()->json([
            'success' => 1,
            'mensaje' => "Guardado satisfactoriamente, {$insertadasTotal} registros procesados"
        ], 200);
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
    public function eliminarDatosBiometrico(){
        $cantRegistos = $this->marcacion_model->deleteAttendances();
        return response()->json([
            'success' => 1,
            'mensaje' => "$cantRegistos Registros Eliminados",
        ], 200);
    }
}
