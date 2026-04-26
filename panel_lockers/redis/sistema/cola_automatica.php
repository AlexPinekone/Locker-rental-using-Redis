<?php
// cola_automatica.php - Proceso en background para manejar la cola automáticamente
require('../comun/conexion_redis.php');
require_once('../comun/utils.php');
require('../comun/procesos.php');

if (isset($error_redis) && $error_redis) {
    error_log("Error de Redis en cola_automatica: $error_redis");
    exit(1);
}

// Obtener conexión a BD para verificar horarios
try {
    require('/var/www/html/comun/conectar.php');
} catch (Exception $e) {
    error_log("Error cargando conectar.php: " . $e->getMessage());
    exit(1);
}

// Función para limpiar el PID cuando el proceso termine
function limpiarPID() {
    $rutaPID = obtenerRutaPIDColaaAutomatica();
    if (file_exists($rutaPID)) {
        @unlink($rutaPID);
        error_log("PID limpiado al terminar el proceso");
    }
}

// Configurar manejo de señales para limpiar PID
if (function_exists('pcntl_signal')) {
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

if (!$horarios) {
    error_log("Error: No hay configuración de horarios establecida");
    exit(1);
}

while (true) {
    // Procesar señales pendientes
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    
    try {
        if (time() - $ultimaActualizacionHorarios >= $intervaloRefresh) {
            $horarios = obtenerHorariosConfig($dbh);
            $ultimaActualizacionHorarios = time();
            error_log("Horarios refrescados: " . json_encode($horarios));
        }
        $debioCerrar = yaDebioCerrar($horarios);
        error_log("Verificación de cierre - Resultado: " . ($debioCerrar ? 'SÍ' : 'NO') . 
                  " | Horarios: " . json_encode($horarios) . 
                  " | Hora actual: " . date('Y-m-d H:i:s'));

        // VERIFICACIÓN 1: Verificar si ya es hora de CIERRE automático
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
        
        //Cerrar el sistema automáticamente si no hay lockers disponibles
        
        /*
        if (hayLockersDisponibles()) {
            error_log(" No hay lockers disponibles. Cerrando sistema automáticamente...");

            // Cambiar estado en Redis
            $redis->set('config:estado_sistema', 'cerrado');

            // (Opcional) limpiar estructuras
            $redis->del($nombreCola);
            $redis->del('locker:seleccionando');

            sleep(10);
            continue;
        }*/
        
        // Es necesario revisar la fecha y hora de inicio del sistema.

        $claveSeleccionando = 'locker:seleccionando';
        $tiempoLimite = 120;

        // Obtener todos los alumnos en selección
        $seleccionando = $redis->hGetAll($claveSeleccionando);

        $alguienExpirado = false;

        foreach ($seleccionando as $clvuni => $jsonData) {
            $datos = json_decode($jsonData, true);
            if (isset($datos['inicio_turno'])) {
                $tiempoTranscurrido = time() - intval($datos['inicio_turno']);
                if ($tiempoTranscurrido >= $tiempoLimite) {
                    // Tiempo expirado: marcar como perdido y remover
                    try 
                    {
                        $redis->hDel($claveSeleccionando, $clvuni);
                        actualizarEstadoRegistroAlumno($redis, $clvuni, 4); // Estado perdido
                        error_log(" Tiempo expirado para $clvuni, marcado como perdido (estado 4)");
                        $alguienExpirado = true;
                    } catch (Exception $expEx) {
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

    sleep(3); // Revisar cada segundo
}


function hayLockersDisponibles() {
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
?>