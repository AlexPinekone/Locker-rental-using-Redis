<?php
header('Content-Type: application/json');

require('../comun/conexion_redis.php');
require('../comun/utils.php');

if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

try 
{
    // Obtener el ciclo. De momento usa Redis. Completamente abierto a cambios
    $ciclo       = $redis->get('config:ciclo') ?: '2025-2026-II';
    $cicloSeguro = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $ciclo);

    $registros = [];

    if (is_dir(LOCKER_LOGS)) 
    {
        // Buscar todos los archivos del ciclo: fila_{ciclo}_*.json
        $patron   = LOCKER_LOGS . "/fila_{$cicloSeguro}_*.json";
        $archivos = glob($patron);

        // Ordenar por fecha (el nombre lo permite al ser YYYY-MM-DD)
        sort($archivos);

        foreach ($archivos as $archivo) 
        {
            $contenido = file_get_contents($archivo);
            $datos     = json_decode($contenido, true) ?: [];

            // Agregar el nombre del archivo como referencia. Por si acaso.
            foreach ($datos as &$registro) 
            {
                $registro['_archivo'] = basename($archivo);
            }

            unset($registro);

            $registros = array_merge($registros, $datos);
        }
    }

    echo json_encode([
        'status'          => 'success',
        'ciclo'           => $ciclo,
        'total_registros' => count($registros),
        'registros'       => $registros
    ]);
}
catch (Exception $e) 
{
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>