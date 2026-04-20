<?php
// cola_automatica.php - Proceso en background para manejar la cola automáticamente
require('../comun/conexion_redis.php');
require('../comun/verificar_sistema.php');
require('../comun/utils.php');

if (isset($error_redis) && $error_redis) {
    error_log("Error de Redis en cola_automatica: $error_redis");
    exit(1);
}

while (true) {
    try {
        // Verificar si el sistema está abierto
        if ($redis->get('config:estado_sistema') !== 'abierto') {
            sleep(5);
            continue;
        }
        error_log("Instancia");
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
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

    // Total de lockers activos
    $queryTotal = "SELECT COUNT(*) as total FROM plantilla.loc_locker WHERE activo = 1";
    $resTotal = mysqli_query($dbh, $queryTotal);
    $total = mysqli_fetch_assoc($resTotal)['total'];

    // Lockers ya reservados
    $queryReservados = "SELECT COUNT(*) as reservados 
                        FROM plantilla.loc_reserva 
                        WHERE estado IN (1,2,3)";
    $resReservados = mysqli_query($dbh, $queryReservados);
    $reservados = mysqli_fetch_assoc($resReservados)['reservados'];

    error_log("OKKKK");

    mysqli_close($dbh);

    return ($reservados >= $total);
}
?>