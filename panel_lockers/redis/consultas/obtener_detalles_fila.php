<?php
header('Content-Type: application/json');

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
    $fechaHoy = date('Y-m-d');

    $nombreArchivoFila = getNombreArchivoFila($redis, $fechaHoy);
    $nombreArchivoTemporal      = LOCKER_TEMP . "/temporal_{$fechaHoy}.jsonl";
    $nombreArchivoSalidas = LOCKER_TEMP . "/salidas_{$fechaHoy}.json";

    // Obtener la cola actual de Redis
    $proximos = $redis->lRange($nombreCola, 0, -1);

    if (empty($proximos)) 
    {
        // Cola vacía: limpiar y resetear
        $redis->del('contador:turno');

        // Procesar salidas en fila
        procesarSalidasEnFila($nombreArchivoFila, $nombreArchivoSalidas);

        // Borrar archivos temporales
        if (is_dir(LOCKER_TEMP)) 
        {
            $archivos = scandir(LOCKER_TEMP);
            
            foreach ($archivos as $archivo) 
            {
                if ($archivo === '.' || $archivo === '..') continue;

                $rutaArchivo = LOCKER_TEMP . '/' . $archivo;

                if (is_file($rutaArchivo)) 
                {
                    unlink($rutaArchivo);
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'total_cola' => 0,
            'detalles' => []
        ]);
        exit;
    }

    // Llamar a la función de comparación y agregación
    compararYAgregarRegistros($nombreArchivoTemporal, $nombreArchivoFila, $nombreArchivoSalidas);

    // Crear array de turnos de Redis para búsqueda rápida
    $turnosEnCola = [];
    foreach ($proximos as $item) 
    {
        $datosAlumno = json_decode($item, true);
        if (isset($datosAlumno['turno'])) 
        {
            $turnosEnCola[$datosAlumno['turno']] = $datosAlumno['clvuni'];
        }
    }
    
    // Leer archivo fila
    $detalles = [];
    if (file_exists($nombreArchivoFila)) 
    {
        try 
        {
            $contenido = file_get_contents($nombreArchivoFila);
            $registrosFila = json_decode($contenido, true) ?: [];
            
            // Filtrar solo los registros que están en la cola actual
            foreach ($registrosFila as $registro) 
            {
                if (isset($registro['turno']) && isset($turnosEnCola[$registro['turno']])) 
                {
                    // Verificar que el clvuni coincida
                    if ($registro['clvuni'] === $turnosEnCola[$registro['turno']]) 
                    {
                        $detalles[] = [
                            'turno' => $registro['turno'] ?? '-',
                            'clvuni' => $registro['clvuni'] ?? '-',
                            'fecha_hora_entrada' => $registro['fecha_hora_entrada'] ?? '-',
                            'locker' => $registro['locker'] ?? '-',
                            'estado' => $registro['estado'] ?? '0',
                            'posicion' => count($detalles) + 1
                        ];
                    }
                }
            }
        } 
        catch (Exception $e) 
        {
            error_log("Error al parsear fila: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'total_cola' => $redis->lLen($nombreCola),
        'detalles' => $detalles
    ]);
}
catch (Exception $e) 
{
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>