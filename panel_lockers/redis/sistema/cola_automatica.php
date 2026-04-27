<?php
// cola_automatica.php - Proceso en background para manejar la cola automáticamente
require('../comun/conexion_redis.php');
require_once('../comun/utils.php');
require('../comun/procesos.php');
require('/var/www/html/comun/conectar.php'); //Tal vez un try catch

if (isset($error_redis) && $error_redis) 
{
    error_log("Error de Redis en cola_automatica: $error_redis");
    exit(1);
}

// Función para limpiar el PID cuando el proceso termine
function limpiarPID() 
{
    $rutaPID = obtenerRutaPIDColaaAutomatica();
    if (file_exists($rutaPID)) 
    {
        @unlink($rutaPID);
        error_log("PID limpiado al terminar el proceso");
    }
}

// Configurar manejo de señales para limpiar PID
if (function_exists('pcntl_signal')) 
{
    pcntl_signal(SIGTERM, 'limpiarPID');
    pcntl_signal(SIGINT, 'limpiarPID');
}

// Escribir el PID del proceso al archivo
$rutaPID = obtenerRutaPIDColaaAutomatica();
file_put_contents($rutaPID, getmypid());

// Obtener horarios
$horarios = obtenerHorariosConfig($dbh);
$ultimaActualizacionHorarios = time();
$intervaloRefresh = 60; 
$ultimaVerificacionLockers = time();
$intervaloVerificacionLockers = 10;

if (!$horarios) 
{
    error_log("Error: No hay configuración de horarios establecida");
    exit(1);
}

while (true) 
{
    // Procesar señales pendientes
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    
    try 
    {
        //Refescar horarios
        if (time() - $ultimaActualizacionHorarios >= $intervaloRefresh) 
        {
            $horarios = obtenerHorariosConfig($dbh);
            $ultimaActualizacionHorarios = time();
            error_log("Horarios refrescados: " . json_encode($horarios));
        }

        //Verificar disponibilidad de lockers
        if (time() - $ultimaVerificacionLockers >= $intervaloVerificacionLockers) 
        {
            $infoLockers = verificarYManejarDisponibilidadLockers($redis, $dbh);
            error_log("Estado Lockers - Disponibles: {$infoLockers['disponibles']}/{$infoLockers['total']} | Cola congelada: " . 
                      ($redis->get('cola:congelada') === '1' ? 'SÍ' : 'NO'));
            $ultimaVerificacionLockers = time();
        }

        $debioCerrar = yaDebioCerrar($horarios);
        error_log("Verificación de cierre - Resultado: " . ($debioCerrar ? 'SÍ' : 'NO') . 
                  " | Horarios: " . json_encode($horarios) . 
                  " | Hora actual: " . date('Y-m-d H:i:s'));

        // Verificar cierre
        if ($debioCerrar) {
            error_log("Hora de cierre alcanzada. Cerrando sistema automáticamente...");
            
            // Marcar sistema como cerrado
            $redis->set('config:estado_sistema', 'cerrado');
            $redis->set('config:fecha_cierre', date('Y-m-d H:i:s'));
            
            // Limpiar cola
            $redis->del($nombreCola);
            $redis->del('contador:turno');
            
            error_log("Sistema cerrado automáticamente");
            
            // Limpiar el archivo PID
            @unlink(obtenerRutaPIDColaaAutomatica());
            
            mysqli_close($dbh);
            exit(0); // Terminar el proceso correctamente
        }
        
        // Verificar si el sistema está abierto
        if ($redis->get('config:estado_sistema') !== 'abierto') {
            sleep(5);
            continue;
        }

        // Si la cola está congelada, no hacer nada más que esperar
        if ($redis->get('cola:congelada') === '1') {
            error_log("Cola está congelada, esperando disponibilidad de lockers...");
            sleep(5);
            continue;
        }

        $claveSeleccionando = 'locker:seleccionando';
        $tiempoLimite = 120;

        // Obtener todos los alumnos en selección
        $seleccionando = $redis->hGetAll($claveSeleccionando);

        $alguienExpirado = false;

        foreach ($seleccionando as $clvuni => $jsonData) 
        {
            $datos = json_decode($jsonData, true);
            if (isset($datos['inicio_turno'])) 
            {
                $tiempoTranscurrido = time() - intval($datos['inicio_turno']);
                if ($tiempoTranscurrido >= $tiempoLimite) 
                {
                    // Tiempo expirado: marcar como perdido y remover
                    try 
                    {
                        $redis->hDel($claveSeleccionando, $clvuni);
                        actualizarEstadoRegistroAlumno($redis, $clvuni, 4); // Estado perdido
                        error_log(" Tiempo expirado para $clvuni, marcado como perdido (estado 4)");
                        $alguienExpirado = true;
                    } 
                    catch (Exception $expEx) 
                    {
                        error_log(" Error al procesar expiración de $clvuni: " . $expEx->getMessage());
                        // Intentar remover de todas formas
                        $redis->hDel($claveSeleccionando, $clvuni);
                    }
                }
            }
        }

        // Si alguien expiró o no hay nadie en selección, intentar atender siguiente
        if ($alguienExpirado || empty($seleccionando)) {
            if ($redis->lLen($nombreCola) > 0) {
                atenderSiguienteAutomatico($redis);
            }
        }

    } catch (Exception $e) {
        error_log("Error en loop de cola_automatica: " . $e->getMessage());
    }

    sleep(3); // Revisar todo cada segundo
}

/*
function hayLockersDisponibles() 
{
    try {
        require('/var/www/html/comun/conectar.php');
    } catch (Exception $e) {
        error_log("Error cargando conectar.php en hayLockersDisponibles: " . $e->getMessage());
        return false;
    }

    // Total de lockers activos
    $queryTotal = "SELECT COUNT(*) as total FROM plantilla.loc_locker WHERE activo = 1";
    $resTotal = mysqli_query($dbh, $queryTotal);
    $total = mysqli_fetch_assoc($resTotal)['total'];

    // Lockers ya reservados
    $queryReservados = "SELECT COUNT(*) as reservados 
                        FROM plantilla.loc_reserva 
                        WHERE estado IN (1,3)";
    $resReservados = mysqli_query($dbh, $queryReservados);
    $reservados = mysqli_fetch_assoc($resReservados)['reservados'];

    error_log("OKKKK");

    mysqli_close($dbh);

    return ($reservados >= $total);
}
*/
/**
 * Verifica disponibilidad de lockers y congela/descongela la cola
 * @param Redis $redis - Conexión a Redis
 * @param mysqli $dbh - Conexión a BD
 * @return array - ['hay_disponibles' => bool, 'total' => int, 'reservados' => int]
 */
function verificarYManejarDisponibilidadLockers($redis, $dbh)
{
    try {
        // Total de lockers activos
        $queryTotal = "SELECT COUNT(*) as total FROM plantilla.loc_locker WHERE activo = 1";
        $resTotal = mysqli_query($dbh, $queryTotal);
        $total = mysqli_fetch_assoc($resTotal)['total'];

        // Lockers ya reservados/asignados
        $queryReservados = "SELECT COUNT(*) as reservados 
                            FROM plantilla.loc_reserva 
                            WHERE estado IN (1,3)";
        $resReservados = mysqli_query($dbh, $queryReservados);
        $reservados = mysqli_fetch_assoc($resReservados)['reservados'];

        $hayDisponibles = ($reservados < $total);

        // GESTIONAR ESTADO EN REDIS
        if (!$hayDisponibles) {
            // NO hay lockers disponibles → CONGELAR cola
            if ($redis->get('cola:congelada') !== '1') {
                $redis->set('cola:congelada', '1');
                $redis->set('cola:fecha_congelacion', date('Y-m-d H:i:s'));
                error_log(" COLA CONGELADA - No hay lockers disponibles (Reservados: $reservados/$total)");
            }
        } else {
            // SÍ hay lockers disponibles → DESCONGELAR cola
            if ($redis->get('cola:congelada') === '1') {
                $redis->del('cola:congelada');
                $redis->del('cola:fecha_congelacion');
                error_log(" COLA DESCONGELADA - Lockers disponibles de nuevo (Disponibles: " . ($total - $reservados) . "/$total)");
            }
        }

        return [
            'hay_disponibles' => $hayDisponibles,
            'total' => $total,
            'reservados' => $reservados,
            'disponibles' => $total - $reservados
        ];

    } catch (Exception $e) {
        error_log("Error en verificarYManejarDisponibilidadLockers: " . $e->getMessage());
        return [
            'hay_disponibles' => false,
            'total' => 0,
            'reservados' => 0,
            'disponibles' => 0
        ];
    }
}
?>