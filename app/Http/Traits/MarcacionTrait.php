<?php

namespace App\Http\Traits;

use App\Models\Marcacion;
use App\ZKService\ZKLibrary;
use Carbon\Carbon;
//use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
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
            $this->tipoestablecimiento='master';
        }else{
            $this->tipoestablecimiento='otros';
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
    public function saveAttendancesMaster($desde, $hasta){
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $hasta = Carbon::parse($hasta)->endOfDay();
        $desde = Carbon::parse($desde);
        $res = $this->zklib->connect();
        $i=0;
        if($res)
        {
            $attendances = array_reverse($this->zklib->getAttendance());
            $serialSub = substr($this->zklib->serialNumber(), 14);
            $serial = substr($serialSub, 0, -1);
            $this->zklib->disconnect();
            if(count($attendances) > 0) 
            {
                foreach ($attendances as $attItem) {
                    $attendanceDate = Carbon::parse($attItem['timestamp'])->toDateTimeString();
                    if(($this->attendanceUserVerify($attItem['uid'],$attItem['type'],
                    Carbon::parse($attendanceDate)->toDateString())===false ) && (
                        $attendanceDate >= $desde." 00:00:00" && $attendanceDate <= $hasta->toDateTimeString()))
                    {
                        $marcacion = new Marcacion();
                        $marcacion->uid = $attItem['uid'];
                        $marcacion->numero_documento = str_pad($attItem['id'], 8, '0', STR_PAD_LEFT);
                        $marcacion->estado = $attItem['state'];
                        $marcacion->fecha = $attendanceDate;
                        $marcacion->tipo = isset($attItem['type']) ? $attItem['type'] : 1;
                        $marcacion->serial = $serial;
                        $marcacion->ip = config('zkteco.ip');
                        $marcacion->save();
                        $i++;
                        if($marcacion->numero_documento != null)
                        {
                            $this->saveAttendanceInApp($marcacion);
                        }
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

        }
    }

    public function saveAttendancesCronJob()
    {
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

    public function attendanceUserVerify($user_id,$tipo, $fecha)
    {
        $marcacion_count =  Marcacion::where('uid',$user_id)
                                ->whereDate('fecha',$fecha)
                                ->where('tipo',$tipo)
                                ->count()
        ;
        if($marcacion_count > 0)
        {
            return true;
        }
        return false;
    }

    public function getAllAttendacesApi()
    {
        try {
            $ruta = config('app.api_url').'/api/attendances';
            $response = Http::get($ruta);



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
            return false;
            // Manejar errores de excepción, como problemas de conexión
        }
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
            return  "Ocurrió un error: " . $e->getMessage();
        }
    }
}