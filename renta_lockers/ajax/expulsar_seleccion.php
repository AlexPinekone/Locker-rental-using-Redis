<?php
header('Content-Type: application/json');
session_start();

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');

if (!isset($_SESSION['clvuni'])) 
{
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

$clvuni = $_SESSION['clvuni'];

try 
{
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/conexion_redis.php');

    if (isset($error_redis) && $error_redis) 
    {
        throw new Exception($error_redis);
    }

    $clvuni_seguro = htmlspecialchars($clvuni);
    $claveSeleccionando = 'locker:seleccionando';

    // Limpiar estado de selección en Redis
    // El cambio de estado a 4 en JSON es responsabilidad exclusiva de cola_automatica.php
    $redis->hDel($claveSeleccionando, $clvuni_seguro);

    echo json_encode([
        'status' => 'success',
        'message' => 'Sesión de selección finalizada',
        'clvuni' => $clvuni_seguro
    ]);
} 
catch (Exception $e) 
{
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
