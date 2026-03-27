<?php
header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');
require('../comun/verificar_sistema.php');
require('../comun/utils.php');

$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;
$nombres = isset($_SESSION['nombres']) ? $_SESSION['nombres'] : null;
$ape_pat = isset($_SESSION['ape_pat']) ? $_SESSION['ape_pat'] : null;
$ape_mat = isset($_SESSION['ape_mat']) ? $_SESSION['ape_mat'] : null;

if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

if ($clvuni) 
{
    try 
    {
        $clvuni_seguro = htmlspecialchars($clvuni);
        $fechaHoy = date('Y-m-d');
        
        $nombreArchivoFila = getNombreArchivoFila($redis, $fechaHoy);
        $nombreArchivoTemporal      = LOCKER_TEMP . "/temporal_{$fechaHoy}.jsonl";
        $nombreArchivoSalidas = LOCKER_TEMP . "/salidas_{$fechaHoy}.json";

        // Procesar salidas en fila
        procesarSalidasEnFila($nombreArchivoFila, $nombreArchivoSalidas);
       
        $estadoExistente   = 0;
        $registroExistente = buscarRegistroAlumnoCicloCompleto($redis, $clvuni_seguro);

        // Si existe y tiene locker, que no entre
        if ($registroExistente && isset($registroExistente['locker']) && $registroExistente['locker'] !== '-') 
        {
            echo json_encode([
                'status'  => 'error', 
                'message' => 'Ya has recibido un locker. No puedes entrar nuevamente.',
                'clvuni'  => $clvuni_seguro,
                'locker'  => $registroExistente['locker']
            ]);
            exit;
        }

        // Si el estado es diferente de 0, crear registro completamente nuevo
        if ($registroExistente && isset($registroExistente['estado']) && $registroExistente['estado'] !== '0') 
        {
            // El usuario tiene un estado diferente (salida, expulsion, etc)
            // Crear un registro completamente nuevo
            $registroExistente = null;
            $estadoExistente = 0;
        }
        else if ($registroExistente && isset($registroExistente['estado'])) 
        {
            // Recuperar estado si existe y es 0
            $estadoExistente = $registroExistente['estado'];
        }
        
        $lista = $redis->lRange($nombreCola, 0, -1);
        
        // Verificar si el usuario ya está en la cola de Redis
        $usuarioEnCola = false;
        $turnoExistente = null;
        $posicionExistente = null;
        
        foreach ($lista as $idx => $item) 
        {
            $itemDecodificado = json_decode($item, true);
            if ($itemDecodificado['clvuni'] === $clvuni_seguro) 
            {
                $usuarioEnCola = true;
                $turnoExistente = $itemDecodificado['turno'];
                $posicionExistente = $idx;
                break;
            }
        }
        
        // Si ya está en la cola de Redis, devolverle su turno
        if ($usuarioEnCola)
        {
            echo json_encode([
                'status'  => 'exists', 
                'message' => 'Ya estás en la cola',
                'clvuni'      => $clvuni_seguro,
                'turno'       => $turnoExistente,
                'posicion'    => $posicionExistente
            ]);
            exit;
        }
        
        // Usuario NO está en la cola, crear turno nuevo
        $numeroTurno = $redis->incr('contador:turno');
        
        // Esperar turno anterior con timeout flexible 
        $maxReintentos = 30;           // 30 intentos × 500ms = 15 segundos
        $tiempoEsperaMs = 500;         // Esperar 500ms entre reintentos
        $intento = 0;
        $turnoAnteriorEnCola = true;
        $turnoAnteriorPerdido = false; // Flag para detectar turno perdido
        
        if ($numeroTurno > 1) 
        {
            $turnoAnterior = $numeroTurno - 1;
            $turnoAnteriorEnCola = false;
            
            while ($intento < $maxReintentos && !$turnoAnteriorEnCola) 
            {
                $lista = $redis->lRange($nombreCola, 0, -1);
                foreach ($lista as $item) 
                {
                    $itemDecodificado = json_decode($item, true);
                    if ($itemDecodificado['turno'] === $turnoAnterior) 
                    {
                        $turnoAnteriorEnCola = true;
                        break;
                    }
                }
                
                if (!$turnoAnteriorEnCola) 
                {
                    $intento++;
                    
                    // Si alcanzamos 50% de reintentos y aún no aparece, probablemente se perdió
                    if ($intento === intval($maxReintentos / 2)) 
                    {
                        $turnoAnteriorPerdido = true;
                    }
                    
                    if ($intento < $maxReintentos) 
                    {
                        usleep($tiempoEsperaMs * 1000);
                    }
                }
            }
            
            // Turno anterior NO aparecerá -> Permitir entrada de todas formas
            if (!$turnoAnteriorEnCola && $turnoAnteriorPerdido) 
            {
                // Registrar que el turno anterior se perdió
                registrarTurnoPerdido($turnoAnterior, $fechaHoy);
                
                // Permitir que este turno entre sin esperar más
                // El flujo continúa normalmente
            }
        }
        
        // El turno anterior está en la cola (o se asume perdido), permitir Push
        $datosAlumno = json_encode([
            'clvuni' => $clvuni_seguro,
            'turno' => $numeroTurno,
            'nombres' => $nombres,
            'ape_pat' => $ape_pat,
            'ape_mat' => $ape_mat
        ]);
        $redis->rPush($nombreCola, $datosAlumno);
        
        // Obtener posición exacta del usuario en la cola tras insertarlo con redis
        $listaActualizada = $redis->lRange($nombreCola, 0, -1);
        $posicion = null;
        foreach ($listaActualizada as $idx => $item) 
        {
            $itemDecodificado = json_decode($item, true);
            if ($itemDecodificado['clvuni'] === $clvuni_seguro) 
            {
                $posicion = $idx;
                break;
            }
        }

        if (!is_dir(LOCKER_TEMP)) 
        {
            mkdir(LOCKER_TEMP, 0755, true);
        }

        $nuevoRegistro = [
            'clvuni'        => $clvuni_seguro,
            'turno'         => $numeroTurno,
            'nombres'       => $nombres,
            'ape_pat'       => $ape_pat,
            'ape_mat'       => $ape_mat,
            'estado'        => $estadoExistente,
            'locker'        => '-',
            'posicion_entrada'  => $posicion,
            'fecha_hora_entrada'=> date('Y-m-d H:i:s'),
            'fecha_hora_asignacion' => '-',
        ];

        $jsonLinea = json_encode($nuevoRegistro, JSON_UNESCAPED_UNICODE) . "\n";
        $resultado = file_put_contents($nombreArchivoTemporal, $jsonLinea, FILE_APPEND);

        if ($resultado === false) 
        {
            throw new Exception("No se pudo escribir en el archivo: " . $nombreArchivoTemporal);
        }

        echo json_encode([
            'status'   => 'success', 
            'message'  => 'Unido a la cola. Log generado correctamente.',
            'clvuni'       => $clvuni_seguro,
            'nombres'      => $nombres,
            'ape_pat'      => $ape_pat,
            'ape_mat'      => $ape_mat,
            'turno'        => $numeroTurno,
            'posicion'     => $posicion,
            'estado'       => $estadoExistente,
            'turno_anterior_presente' => $turnoAnteriorEnCola,
            'reintentos'   => $intento,
            'path'         => $nombreArchivoTemporal
        ]);
    } 
    catch (Exception $e) 
    {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} 
else 
{
    echo json_encode(['status' => 'error', 'message' => 'No hay una sesión activa de alumno']);
}

/**
 * Función auxiliar para registrar turnos perdidos
 */
function registrarTurnoPerdido($numeroTurno, $fechaHoy)
{
    $carpetaRegistro = LOCKER_LOGS . '/registro';
    $nombreArchivoTurnosPerdidos = $carpetaTemp . "/turnos_perdidos_{$fechaHoy}.jsonl";
    
    if (!is_dir($carpetaRegistro)) 
    {
        mkdir($carpetaRegistro, 0755, true);
    }
    
    $datosTurnoPerdido = [
        'turno' => $numeroTurno,
        'fecha_hora_deteccion' => date('Y-m-d H:i:s'),
        'razon' => 'Usuario no se incorporó dentro del tiempo límite'
    ];
    
    $jsonTurnoPerdido = json_encode($datosTurnoPerdido, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($nombreArchivoTurnosPerdidos, $jsonTurnoPerdido, FILE_APPEND);
}
?>