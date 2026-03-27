<?php
header('Content-Type: application/json');

// Primero se conecta a redis. Luego se bloquea todo si el sistema aún no esta abierto.
require('../comun/conexion_redis.php');
require('../comun/verificar_sistema.php');
require('../comun/utils.php');

if (isset($error_redis) && $error_redis) 
{
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

try 
{
    $itemJson = $redis->lPop($nombreCola);

    if ($itemJson) 
    {
        $datosAlumno = json_decode($itemJson, true);
        $clvuni = $datosAlumno['clvuni'];
        $turno = $datosAlumno['turno'];
        
        $fechaHoy = date('Y-m-d');
        
        $nombreArchivoFila = getNombreArchivoFila($redis, $fechaHoy);
        $nombreArchivoTemporal = LOCKER_TEMP . "/temporal_{$fechaHoy}.jsonl";
        $nombreArchivoSalidas = LOCKER_TEMP . "/salida_{$fechaHoy}.json";
        
        // Llamar a la función de comparación y agregación
        $datosFila = compararYAgregarRegistros($nombreArchivoTemporal, $nombreArchivoFila, $nombreArchivoSalidas);
        
        // Buscar el registro del alumno
        $resultado = buscarRegistroAlumno($datosFila, $clvuni);
        $registroCompleto = $resultado['registro'];
        $indiceRegistro = $resultado['indice'];
        
        // Si no lo encuentra, crear uno nuevo
        if (!$registroCompleto) 
        {
            $registroCompleto = crearNuevoRegistro($clvuni, $turno);
            $indiceRegistro = count($datosFila);
        }
        
        // Actualizar en el array
        $datosFila[$indiceRegistro] = $registroCompleto;
        
        // Guardar el archivo actualizado
        guardarEnArchivo($nombreArchivoFila, $datosFila);

        $cola_restante = $redis->lLen($nombreCola);

        echo json_encode([
            'status'              => 'success', 
            'message'             => 'Alumno movido a fila - Esperando selección de locker',
            'id'                  => $clvuni,
            'turno'               => $turno,
            'fecha_hora'          => date('Y-m-d H:i:s'),
            'cola_restante'       => $cola_restante,
            'path_fila'           => $nombreArchivoFila,
            'total_registros'     => count($datosFila)
        ]);
    } 
    else 
    {
        echo json_encode([
            'status'  => 'empty', 
            'message' => 'No hay nadie en la cola de espera.',
            'cola_restante' => 0
        ]);
    }
}
catch (Exception $e) 
{
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>