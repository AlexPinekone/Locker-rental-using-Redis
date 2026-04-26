<?php
header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');
require('../comun/verificar_sistema.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/panel_lockers/redis/comun/utils.php');

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
       
        // Verificar en base de datos si el alumno ya tiene una reserva activa en el ciclo actual
        require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');
        $ciclo = $redis->get('config:ciclo') ?: 'sin-ciclo';
        
        $query = "SELECT estado FROM plantilla.loc_reserva WHERE clave_unica = ? AND ciclo = ? LIMIT 1";
        $stmt = mysqli_prepare($dbh, $query);
        mysqli_stmt_bind_param($stmt, "ss", $clvuni_seguro, $ciclo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $registroBD = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        mysqli_close($dbh);
        
        // Si existe registro en BD y estado es 1 o 3 (reserva activa o pagada), no permitir unirse
        if ($registroBD && in_array($registroBD['estado'], [1, 3])) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Ya tienes una reserva activa en este ciclo. No puedes unirte nuevamente.',
                'clvuni'  => $clvuni_seguro,
                'estado'  => $registroBD['estado']
            ]);
            exit;
        }
        
        // Verificar si ya existe un registro en el archivo fila JSON
        $registroExistenteJSON = null;
        if (file_exists($nombreArchivoFila)) 
        {
            $contenido = file_get_contents($nombreArchivoFila);
            $datosFila = json_decode($contenido, true) ?: [];
            $resultado = buscarRegistroAlumno($datosFila, $clvuni_seguro);
            if ($resultado['registro']) 
            {
                $registroExistenteJSON = $resultado['registro'];
            }
        }
        
        if ($registroExistenteJSON && in_array($registroExistenteJSON['estado'], [1, 3, 4, 5])) {
            // Crear registro completamente nuevo
            $estadoExistente = 0;
        } elseif ($registroExistenteJSON) {
            // Usar el estado del registro existente
            $estadoExistente = $registroExistenteJSON['estado'];
        } else {
            $estadoExistente = 0;
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

                $turnoAnteriorEnCola = false;

                // Buscar en la cola (lista)
                $lista = $redis->lRange($nombreCola, 0, -1);
                foreach ($lista as $item) {
                    $itemDecodificado = json_decode($item, true);
                    if (isset($itemDecodificado['turno']) && $itemDecodificado['turno'] === $turnoAnterior) {
                        $turnoAnteriorEnCola = true;
                        break;
                    }
                }

                // Si no está en cola, buscar en "seleccionando"
                if (!$turnoAnteriorEnCola) {
                    $seleccionando = $redis->hGetAll('locker:seleccionando');

                    foreach ($seleccionando as $clv => $jsonData) {
                        $datos = json_decode($jsonData, true);

                        if (isset($datos['turno']) && $datos['turno'] === $turnoAnterior) {
                            $turnoAnteriorEnCola = true;
                            break;
                        }
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
    $nombreArchivoTurnosPerdidos = $carpetaRegistro . "/turnos_perdidos_{$fechaHoy}.jsonl";
    
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