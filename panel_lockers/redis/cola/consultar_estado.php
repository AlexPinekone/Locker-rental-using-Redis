<?php
header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');
require_once('../comun/utils.php');

$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;

if ($error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión']);
    exit;
}

if ($clvuni) 
{
    try 
    {
        // Si el sistema cerró, sacar al alumno de la cola y notificarle
        if ($redis->get('config:estado_sistema') !== 'abierto') 
        {
            $clvuni_seguro = htmlspecialchars($clvuni);

            // Intentar sacarlo de la cola si aún está
            try 
            {
                sacarDeColaPorClvuni($redis, $nombreCola, $clvuni_seguro);
            } 
            catch (Exception $e) 
            {
                // Si no estaba en la cola, no importa, continuar
            }

            // Destruir sesión del alumno
            unset($_SESSION['clvuni']);
            session_destroy();

            echo json_encode([
                'status'  => 'sistema_cerrado',
                'message' => 'El sistema ha sido cerrado por el administrador.'
            ]);
            exit;
        }

        $clvuni_seguro = htmlspecialchars($clvuni);
        $lista = $redis->lRange($nombreCola, 0, -1);

        $encontrado = false;
        $posicion   = 0;
        $turno      = null;

        foreach ($lista as $idx => $item) 
        {
            $datosAlumno = json_decode($item, true);
            if ($datosAlumno['clvuni'] === $clvuni_seguro) 
            {
                $encontrado = true;
                $posicion   = $idx;
                $turno      = $datosAlumno['turno'];
                break;
            }
        }

        if (!$encontrado) 
        {
            echo json_encode([
                'status'  => 'atendido',
                'mensaje' => 'Ya no estás en la cola'
            ]);
        } 
        else 
        {
            echo json_encode([
                'status'          => 'esperando',
                'posicion'        => $posicion,
                'turno'           => $turno,
                'personas_delante'=> $posicion
            ]);
        }
    } 
    catch (Exception $e) 
    {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 
else 
{
    echo json_encode(['status' => 'error', 'message' => 'Sesion expirada']);
}
?>