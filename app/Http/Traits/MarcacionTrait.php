<?php

namespace App\Http\Traits;

use App\Models\Marcacion;
use App\ZKService\ZKLibrary;
use Carbon\Carbon;
//use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Rats\Zkteco\Lib\ZKTeco;
trait MarcacionTrait
{
    private $zklib;
    private $tipo_marcacion;
    private $tipoestablecimiento;
    public function __construct()
    {
        if(config('zkteco.establecimiento_master')){
            $this->zklib = new ZKTeco(config('zkteco.ip'));
            $this->tipoestablecimiento='master'; // son los que tienen su indice de arreglo numero
        }else{
            $this->tipoestablecimiento='otros'; // son los que tienen en su indice de arreglo numero
            $this->zklib = new ZKLibrary(
                config('zkteco.ip'),
                config('zkteco.port'),
                config('zkteco.protocol')
            );
        }
        $this->tipo_marcacion = [
            0 => 'ENTRADA',
            1 => 'SALIDA',
            2 => 'ENTRADA TE',
            3 => 'ENTRADA TE',
            4 => 'ENTRADA TE',
            5 => 'SALIDA TE'
        ];
    }
    public function getMethods(){
        dd(get_class_methods($this->zklib));
        return 0;        
    }


    public function getVersionMaster()
    {
        $res = $this->zklib->connect();

        if (!$res) {
            return response()->json([
                'ok' => 0,
                'mensaje' => 'No se pudo conectar con el dispositivo biométrico.'
            ], 500);
        }

        $info = [
            'version'        => trim($this->zklib->version(), "\0"),
            'os_version'     => trim($this->zklib->osVersion(), "\0"),
            'platform'       => trim($this->zklib->platform(), "\0"),
            'fm_version'     => trim($this->zklib->fmVersion(), "\0"),
            'work_code'      => trim($this->zklib->workCode(), "\0"),
            'ssr'            => trim($this->zklib->ssr(), "\0"),
            'pin_width'      => trim($this->zklib->pinWidth(), "\0"),
            'face_function'  => trim($this->zklib->faceFunctionOn(), "\0"),
            'serial_number'  => trim($this->zklib->serialNumber(), "\0"),
            'device_name'    => trim($this->zklib->deviceName(), "\0"),
            'current_time'   => $this->zklib->getTime(),
            'ip_address'     => $this->zklib->_ip ?? 'No disponible',
            'port'           => $this->zklib->_port ?? null,
        ];

        try {
            if (method_exists($this->zklib, 'getMac')) {
                $info['mac_address'] = trim($this->zklib->getMac(), "\0");
            } else {
                $info['mac_address'] = 'No disponible (no soportado por SDK)';
            }
        } catch (\Throwable $e) {
            $info['mac_address'] = 'Error al obtener MAC: ' . $e->getMessage();
        }

        $this->zklib->disconnect();

        return response()->json([
            'ok' => 1,
            'mensaje' => 'Información general del dispositivo obtenida correctamente.',
            'info' => $info
        ]);
    }
    public function getVersionOtros(){
        $res = $this->zklib->connect();

        if (!$res) {
            return response()->json([
                'ok' => 0,
                'mensaje' => 'No se pudo conectar con el dispositivo biométrico.'
            ], 500);
        }

        try {
            $info = [
                'version'        => trim($this->zklib->getVersion(), "\0"),
                'os_version'     => '',
                'platform'       => '',
                'fm_version'     => '',
                'work_code'      => '',
                'ssr'            => '',
                'pin_width'      => '',
                'face_function'  => '',
                'serial_number'  => trim($this->zklib->getSerialNumber(), "\0"),
                'device_name'    => trim($this->zklib->getDeviceName(), "\0"),
                'current_time'   => $this->zklib->getTime(),
                'ip_address'     => $this->zklib->_ip ?? 'No disponible',
                'port'           => '',
            ];

            if (method_exists($this->zklib, 'getMac')) {
                $info['mac_address'] = trim($this->zklib->getMac(), "\0");
            } else {
                $info['mac_address'] = 'No disponible (no soportado por SDK)';
            }

        } catch (\Throwable $e) {
            $this->zklib->disconnect();

            return response()->json([
                'ok' => 0,
                'mensaje' => 'Error al obtener información del dispositivo: ' . $e->getMessage()
            ], 500);
        }

        $this->zklib->disconnect();

        return response()->json([
            'ok' => 1,
            'mensaje' => 'Información general del dispositivo obtenida correctamente.',
            'info' => $info
        ]);
    }
    public function getVersion(){
        if($this->tipoestablecimiento=='master'){
            return $this->getVersionMaster();
        }else{
            return $this->getVersionOtros();
        }
    }
    public function getUsers()
    {
        $res = $this->zklib->connect();
        $users = array();
        if($res)
        {
            $users = $this->zklib->getUser();
            $this->zklib->disconnect();
            return $users;
        }
        return array();        
    }
    public function getAttedances()
    {
        set_time_limit(0);
        $res = $this->zklib->connect();
        if($res)
        {
            $attendances = array_reverse($this->zklib->getAttendance());
            $this->zklib->disconnect();
            return $attendances;
        }
        return 404;
    }
    public function cantidadAttendances(){
        $res = $this->zklib->connect();
        if($res)
        {
            $attendances = $this->zklib->getAttendance();
            $this->zklib->disconnect();
            return count($attendances);
        }
        return 0;
    }
    public function getAttedancesByAsc()
    {
        $res = $this->zklib->connect();
        if($res)
        {
            $attendances = $this->zklib->getAttendance();
            
            $this->zklib->disconnect();
            
            return $attendances;
        }

        return 404;
    }
    public function saveAttendances()
    {
        $res = $this->zklib->connect();
        if($res)
        {

            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = substr($this->zklib->getSerialNumber(false), 14);
            $serial = substr($serialSub, 0, -1);

            if(count($attendances) > 0) 
            {
                foreach ($attendances as $attItem) {    

                    if($this->attendanceUserVerify($attItem[0],$attItem[4])===false)
                    {
                        $marcacion = new Marcacion();
                        $marcacion->uid = $attItem[0];
                        $marcacion->numero_documento = $attItem[1];
                        $marcacion->estado = $attItem[2];
                        $marcacion->fecha = $attItem[3];
                        $marcacion->tipo = $attItem[4];
                        $marcacion->serial = $serial;
                        $marcacion->ip = config('zkteco.ip');
                        $marcacion->save();
                    }
                }

            }

            $this->zklib->disconnect();

            return 1;
            //$this->zklib->clearAttendance(); // Remove attendance log only if not empty            
        }
        return 404;
    }

    public function saveAttendancesMaster($desde, $hasta)
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->endOfDay();

        $i = 0;
        $payload = [];
        $lineas = [];

        if ($this->zklib->connect()) {

            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = substr($this->zklib->serialNumber(), 14);
            $serial = substr($serialSub, 0, -1);
            $ip = config('zkteco.ip');
            $this->zklib->disconnect();

            if (!empty($attendances)) {

                foreach ($attendances as $attItem) {
                    $ts = Carbon::parse($attItem['timestamp']);

                    if ($ts->betweenIncluded($desde, $hasta)) {

                        $numeroDocumento = str_pad($attItem['id'], 8, '0', STR_PAD_LEFT);

                        $marcacion = Marcacion::firstOrCreate([
                            'numero_documento' => $numeroDocumento,
                            'fecha'            => $ts->toDateTimeString()
                        ], [
                            'uid'    => $attItem['uid'],
                            'estado' => $attItem['state'],
                            'tipo'   => $attItem['type'] ?? 1,
                            'serial' => $serial,
                            'ip'     => $ip
                        ]);

                        if ($marcacion->wasRecentlyCreated) {
                            $dt = Carbon::parse($marcacion->fecha);
                            $lineas[] = implode(' ', [
                                $marcacion->numero_documento,
                                $dt->format('Y-m-d'),
                                $dt->format('H:i:s'),
                                $marcacion->tipo,
                                $marcacion->estado,
                                0
                            ]);

                            $payload[] = [
                                'dni'     => $numeroDocumento,
                                'uid'     => $marcacion->uid,
                                'estado'  => $marcacion->estado,
                                'fecha'   => $marcacion->fecha,
                                'tipo'    => $marcacion->tipo,
                                'serial'  => $marcacion->serial,
                                'ip'      => $marcacion->ip,
                            ];

                            $i++;
                        }
                    }
                }

                if (!empty($lineas)) {
                    $nombre = "{$serial}_attlog_" . now()->format('Ymd_His') . ".dat";
                    $contenido = implode("\r\n", $lineas) . "\r\n";
                    Storage::disk('marcaciones')->put($nombre, $contenido);
                }

                Log::info('Marcaciones procesadas', [
                    'total_leidas'     => count($attendances),
                    'nuevas_insertadas'=> count($lineas),
                    'archivo'          => $nombre ?? 'no generado'
                ]);
                if (!empty($payload)) {
                    $ruta = config('app.api_url') . '/api/guardar-marcaciones-lote';
                    try {
                        $response = Http::timeout(120)->post($ruta, [
                            'marcaciones' => $payload
                        ]);

                        if (!$response->successful()) {
                            Log::error('Error al enviar marcaciones', ['response' => $response->body()]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Excepción al enviar marcaciones: ' . $e->getMessage());
                    }
                }
            }

            return $i;
        }

        return -1;
    }

    public function deleteAttendances(){
        $res = $this->zklib->connect();
        $registros = 0;
        if($res)
        {
            $registros = count($this->zklib->getAttendance());
            $this->zklib->clearAttendance();
            $this->zklib->disconnect();
        }else{
            return 0;
        }
        return $registros;
    }
    public function saveAttendancesByAsc($desde, $hasta)
    {
        if($this->tipoestablecimiento=='master'){
            return $this->saveAttendancesMaster($desde, $hasta);
        }else{
            return $this->saveAttendancesOtros($desde, $hasta);
        }
    }
    public function saveAttendancesOtros($desde, $hasta)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        // Fechas: parse una sola vez
        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->endOfDay();

        $i = 0;
        $payload = [];
        $lineas  = [];

        if ($this->zklib->connect()) {

            $attendances = array_reverse($this->zklib->getAttendance());

            // Serial de respaldo (si tu equipo no devuelve serialNumber)
            $serialSub = 'SERIAL GENERAL EN CASO NO FUNC';
            // Si más adelante activas serialNumber(), solo reemplaza la línea anterior por:
            // $serialSub = substr($this->zklib->serialNumber(), 14);
            // y mantén este rtrim para limpiar:
            $serial = rtrim(substr($serialSub, 0, -1));

            $ip = config('zkteco.ip');
            $this->zklib->disconnect();

            if (!empty($attendances)) {
                foreach ($attendances as $attItem) {
                    // Mapeo de índices: [0]=uid, [1]=dni, [2]=tipo, [3]=timestamp, [4]=estado
                    $ts = Carbon::parse($attItem[3]);

                    if ($ts->betweenIncluded($desde, $hasta)) {
                        $numeroDocumento = str_pad($attItem[1], 8, '0', STR_PAD_LEFT);
                        $marcacion = Marcacion::firstOrCreate([
                            'numero_documento' => $numeroDocumento,
                            'fecha'            => $ts->toDateTimeString(),
                        ], [
                            'uid'    => $attItem[0],
                            'estado' => $attItem[4],
                            'tipo'   => $attItem[2] ?? 1,
                            'serial' => $serial,
                            'ip'     => $ip,
                        ]);

                        // Solo si realmente se insertó una nueva fila
                        if ($marcacion->wasRecentlyCreated) {
                            $dt = Carbon::parse($marcacion->fecha);

                            // Línea para .dat (dni yyyy-mm-dd hh:mm:ss tipo estado 0)
                            $lineas[] = implode(' ', [
                                $marcacion->numero_documento,
                                $dt->format('Y-m-d'),
                                $dt->format('H:i:s'),
                                $marcacion->tipo,
                                $marcacion->estado,
                                0
                            ]);

                            // Payload para envío remoto
                            $payload[] = [
                                'dni'    => $numeroDocumento,
                                'uid'    => $marcacion->uid,
                                'estado' => $marcacion->estado,
                                'fecha'  => $marcacion->fecha,
                                'tipo'   => $marcacion->tipo,
                                'serial' => $marcacion->serial,
                                'ip'     => $marcacion->ip,
                            ];

                            $i++;
                        }
                    }
                }

                // Guardar .dat solo si hay nuevas líneas
                if (!empty($lineas)) {
                    $nombre = "{$serial}_attlog_" . now()->format('Ymd_His') . ".dat";
                    $contenido = implode("\r\n", $lineas) . "\r\n";
                    Storage::disk('marcaciones')->put($nombre, $contenido);
                }

                Log::info('Otros - Marcaciones procesadas', [
                    'total_leidas'      => count($attendances),
                    'nuevas_insertadas' => count($lineas),
                    'archivo'           => $nombre ?? 'no generado'
                ]);

                // Envío remoto (si hubo nuevas)
                if (!empty($payload)) {
                    $ruta = config('app.api_url') . '/api/guardar-marcaciones-lote';
                    try {
                        $response = Http::timeout(120)->post($ruta, ['marcaciones' => $payload]);
                        if (!$response->successful()) {
                            Log::error('Otros - Error al enviar marcaciones', ['response' => $response->body()]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Otros - Excepción al enviar marcaciones: ' . $e->getMessage());
                    }
                }
            }

            return $i;
        }

        return -1;
    }

    public function saveAttendancesCronJob(){
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $res = $this->zklib->connect();
        if($res)
        {
            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = substr($this->zklib->getSerialNumber(false), 14);
            $serial = substr($serialSub, 0, -1);
            $this->zklib->disconnect();

            if(count($attendances) > 0) 
            {
                //sleep(1);                
                foreach ($attendances as $attItem) {                    
                    if(($this->attendanceUserVerify($attItem[0],$attItem[4])===false ) && (
                        $attItem[3] >= date('Y-m-d')." 00:00:00" && $attItem[3] <= date('Y-m-d H:i:s')))
                    {
                        // if($this->getVerificarDniPersonalApp($attItem[1]) != 0)
                        // {
                            $marcacion = new Marcacion();
                            $marcacion->uid = $attItem[0];
                            $marcacion->numero_documento = $attItem[1];
                            $marcacion->estado = $attItem[2];
                            $marcacion->fecha = $attItem[3];
                            $marcacion->tipo = $attItem[4];
                            $marcacion->serial = $serial;
                            $marcacion->ip = config('zkteco.ip');
                            $marcacion->save();
                            
                            if($marcacion->numero_documento != null)
                            {
                                $estado = $this->saveAttendanceInApp($marcacion);
                                // if($estado['ok'] == 1)
                                // {

                                // }
                            }
                        //}
                    }
                    
                }

            }

            

            return 1;
            //$this->zklib->clearAttendance(); // Remove attendance log only if not empty            
        }
        return 404;
    }
    public function getVerificarDniPersonalApp(string $dni)
    {
        //$client = new Client();

        try {
            $ruta = config('app.api_url').'/api/attendances/verificar-dni';
            $response = Http::get($ruta,[
                'dni' => $dni
            ]);

            //return $ruta;
            // Verificar si la respuesta tiene un código 200 (éxito)
            if ($response->getStatusCode() == 200) {

               return $response->json();
                 //json_decode($response->getBody(), true);
                // Aquí puedes trabajar con los datos de respuesta
            } else {
                // Manejar el caso en que la respuesta no sea un código 200
            }
        } catch (\Exception $e) {
            // Manejar errores de excepción, como problemas de conexión
        }
    }
    public function saveAttendanceInApp($marcacion) {
        set_time_limit(0);
        try {
            $ruta = config('app.api_url').'/api/guardar-marcaciones';
            $response = Http::timeout(0)->post($ruta,[
                'dni' => $marcacion->numero_documento,
                'uid' => $marcacion->uid,
                'estado' => $marcacion->estado,
                'fecha' => $marcacion->fecha,
                'tipo' => $this->tipo_marcacion[$marcacion->tipo],
                'serial' => $marcacion->serial,
                'ip' => $marcacion->ip,
            ]);
            if ($response->getStatusCode() == 200) {
               return $response->json();
                 //json_decode($response->getBody(), true);
                // Aquí puedes trabajar con los datos de respuesta
            } else {
                // Manejar el caso en que la respuesta no sea un código 200
            }
        } catch (\Exception $e) {
            return  "Ocurrió un error: " . $e->getMessage();
        }
    }
}