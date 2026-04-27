<?php
session_start();

$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;

if ($clvuni) 
{
    try 
    {
        require('redis/comun/conexion_redis.php');
        require_once($_SERVER['DOCUMENT_ROOT'].'/panel_lockers/redis/comun/utils.php');
        if (!isset($error_redis) || !$error_redis) 
        {
            $claveSeleccionando = 'locker:seleccionando';
            if ($redis->hExists($claveSeleccionando, $clvuni)) 
            {
                $redis->hDel($claveSeleccionando, $clvuni);
                actualizarEstadoRegistroAlumno($redis, $clvuni, 5); // Asignar estado "perdido"
                // Lo siguiente causa problemas si dos personas están formadas en la misma computadora y mismo navegador:
                //  una eligiendo locker y la otra cerrando la sesion del que elige para ver su turno al mismo tiempo.
                //  Hace que la segunda persona pierda su turno.
                atenderSiguienteAutomatico($redis);
            }
        }
    } catch (Exception $e) {
        // :(
    }
}

session_destroy();
header("Location: login.php");
exit();
?>