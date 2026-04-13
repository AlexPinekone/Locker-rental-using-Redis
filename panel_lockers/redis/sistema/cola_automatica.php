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
                        error_log("✓ Tiempo expirado para $clvuni, marcado como perdido (estado 4)");
                        $alguienExpirado = true;
                    } catch (Exception $expEx) {
                        error_log("✗ Error al procesar expiración de $clvuni: " . $expEx->getMessage());
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
?>