<?php
header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');

if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

if ($redis->get('config:estado_sistema') !== 'abierto') 
{
    echo json_encode(['status' => 'error', 'message' => 'El sistema ya está cerrado.']);
    exit;
}

try 
{
    // Marcar como cerrado
    $redis->set('config:estado_sistema', 'cerrado');
    $redis->set('config:fecha_cierre', date('Y-m-d H:i:s'));

    // Limpiar cola y contador
    $redis->del($nombreCola);
    $redis->del('contador:turno');

    // Limpiar archivos temporales si existen
    if (is_dir(LOCKER_TEMP)) 
    {
        $archivos = scandir(LOCKER_TEMP);
        foreach ($archivos as $archivo) 
        {
            if ($archivo === '.' || $archivo === '..') 
            {
                continue;
            }

            $rutaArchivo = LOCKER_TEMP . '/' . $archivo;
            if (is_file($rutaArchivo)) 
            {
                @unlink($rutaArchivo);
            }
        }
    }

    echo json_encode([
        'status'        => 'success',
        'message'       => 'Sistema cerrado correctamente.',
        'fecha_cierre'  => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>