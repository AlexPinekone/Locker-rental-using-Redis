<?php
header('Content-Type: application/json');
session_start();

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');

if (!isset($_SESSION['clvuni'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

$clvuni = $_SESSION['clvuni'];

try {
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/conexion_redis.php');
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/utils.php');

    if (isset($error_redis) && $error_redis) {
        throw new Exception($error_redis);
    }

    $clvuni_seguro = htmlspecialchars($clvuni);
    $claveSeleccionando = 'locker:seleccionando';

    // Limpiar estado de selección en Redis
    $redis->hDel($claveSeleccionando, $clvuni_seguro);

    // Actualizar el registro en el archivo JSON de fila
    actualizarEstadoRegistroAlumno($redis, $clvuni_seguro, 4);

    echo json_encode([
        'status' => 'success',
        'message' => 'Registro actualizado a estado 4',
        'clvuni' => $clvuni_seguro
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
