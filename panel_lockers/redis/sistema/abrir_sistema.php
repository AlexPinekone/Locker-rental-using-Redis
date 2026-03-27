<?php
header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');
require('../comun/utils.php');

//Guardar el ciclo en conexion_redis para que lo use todo el sitemas

// Verificar que Redis corra
if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

// Verificar que no esté ya abierto
if ($redis->get('config:estado_sistema') === 'abierto') 
{
    echo json_encode(['status' => 'error', 'message' => 'El sistema ya está abierto.']);
    exit;
}

// Leer ciclo de la sesión del admin
$ciclo = isset($_SESSION['ciclo']) ? $_SESSION['ciclo'] : '2025-2026-II';
$redis->set('config:ciclo', $ciclo);

try 
{   
    // Variables para los archivos 
    $fechaHoy  = date('Y-m-d');

    // Crear carpetas si no existen
    if (!is_dir(LOCKER_TEMP)) 
    {
        mkdir(LOCKER_TEMP, 0755, true);
    }

    // Definicion de rutas de los archivos
    $archivos = [
        getNombreArchivoFila($redis, $fechaHoy),
        LOCKER_TEMP . "/temporal_{$fechaHoy}.jsonl",
        LOCKER_TEMP . "/salidas_{$fechaHoy}.json",
    ];

    // Buscar si ya estan los archivos, si no crea un json[] o un jsonl'' vacio
    foreach ($archivos as $ruta) 
    {
        if (!file_exists($ruta)) 
        {
            $esJson = str_ends_with($ruta, '.json');
            file_put_contents($ruta, $esJson ? '[]' : '');
        }
    }

    // Marcar sistema como abierto en Redis. El resto de de la app ahora lo sabe.
    $redis->set('config:estado_sistema', 'abierto');
    $redis->set('config:fecha_apertura', date('Y-m-d H:i:s'));

    // Limpiar cola y contador por si quedaron residuos de otro dia 
    $redis->del($nombreCola);
    $redis->del('contador:turno');

    echo json_encode([
        'status'          => 'success',
        'message'         => 'Sistema abierto correctamente.',
        'ciclo'           => $ciclo,
        'fecha_apertura'  => date('Y-m-d H:i:s'),
        'archivos_creados'=> $archivos
    ]);

} 
catch (Exception $e) 
{
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>