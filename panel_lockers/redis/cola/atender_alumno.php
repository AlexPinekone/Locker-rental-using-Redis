<?php
header('Content-Type: application/json');

// Conexiones y validaciones base
require('../comun/conexion_redis.php');
require('../comun/verificar_sistema.php');
require_once('../comun/utils.php');

if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

try 
{
    $claveSeleccionando = 'locker:seleccionando';

    // Función para mover la cola
    $resultado = atenderSiguienteManual($redis);

    if ($resultado) 
    {
        $cola_restante = $redis->lLen($nombreCola);

        echo json_encode([
            'status' => 'success',
            'message' => 'Alumno movido a selección de locker',
            'fecha_hora' => date('Y-m-d H:i:s'),
            'cola_restante' => $cola_restante
        ]);
    } 
    else 
    {
        echo json_encode([
            'status' => 'empty',
            'message' => 'No hay nadie en la cola de espera.',
            'cola_restante' => 0
        ]);
    }
}
catch (Exception $e) 
{
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>