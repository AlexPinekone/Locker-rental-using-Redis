<?php
header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');
require('../comun/verificar_sistema.php');
require('../comun/utils.php');

$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;

if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

if ($clvuni) 
{
    try 
    {
        $clvuni_seguro = htmlspecialchars($clvuni);
        $fechaHoy = date('Y-m-d');
        
        // Sacar de la cola
        $resultadoSalida = sacarDeColaPorClvuni($redis, $nombreCola, $clvuni_seguro);
        
        // Registrar en salidas
        registrarSalidas(
            $clvuni_seguro,
            $resultadoSalida['datosUsuario']['turno'],
            1,
            'salida_voluntaria_cola',
            $fechaHoy
        );
        
        // Finalizar sesión
        unset($_SESSION['clvuni']);
        session_destroy();
        
        echo json_encode([
            'status'           => 'success', 
            'message'          => 'Has salido de la cola y se registró la salida',
            'clvuni'           => $clvuni_seguro,
            'posicionAnterior' => $resultadoSalida['indice'] + 1,
            'estadoNuevo'      => 1,
            'fechaSalida'      => date('Y-m-d H:i:s')
        ]);
    } 
    catch (Exception $e) 
    {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} 
else 
{
    echo json_encode(['status' => 'error', 'message' => 'No hay una sesión activa']);
}
?>