<?php
session_start();

$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;

if ($clvuni) {
    try {
        require('redis/comun/conexion_redis.php');
        require('../panel_lockers/redis/comun/utils.php');
        if (!isset($error_redis) || !$error_redis) {
            $claveSeleccionando = 'locker:seleccionando';
            if ($redis->hExists($claveSeleccionando, $clvuni)) {
                $redis->hDel($claveSeleccionando, $clvuni);
                actualizarEstadoRegistroAlumno($redis, $clvuni, 4); // Estado perdido
                atenderSiguienteAutomatico($redis);
            }
        }
    } catch (Exception $e) {
        // No interrumpir el cierre de sesión
    }
}

session_destroy();
header("Location: login.php");
exit();
?>