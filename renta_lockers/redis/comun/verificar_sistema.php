<?php
// Despues de conexion_redis.php
// Detiene la ejecución si el sistema está cerrado.

$estadoSistema = $redis->get('config:estado_sistema');

if ($estadoSistema !== 'abierto') 
{
    echo json_encode([
        'status'  => 'sistema_cerrado',
        'message' => 'El sistema aún no ha sido abierto por el administrador.'
    ]);
    exit;
}
?>