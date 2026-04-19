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

    // Obtener la cola actual de Redis y los alumnos que están seleccionando locker
    $proximos = $redis->lRange($nombreCola, 0, -1);
    $claveSeleccionando = 'locker:seleccionando';
    $seleccionandoHash = $redis->hGetAll($claveSeleccionando);

    if (empty($proximos) && empty($seleccionandoHash)) 
    {
        // Cola vacía y sin seleccionando: limpiar y resetear
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

    // Crear arrays de turnos de Redis para búsqueda rápida
    $turnosEnCola = [];
    foreach ($proximos as $item) 
    {
        $datosAlumno = json_decode($item, true);
        if (isset($datosAlumno['turno'])) 
        {
            $turnosEnCola[$datosAlumno['turno']] = $datosAlumno['clvuni'];
        }
    }

    $turnosSeleccionando = [];
    foreach ($seleccionandoHash as $clvuni => $jsonData) 
    {
        $datosSeleccionando = json_decode($jsonData, true);
        if (isset($datosSeleccionando['turno'])) 
        {
            $turnosSeleccionando[$datosSeleccionando['turno']] = $clvuni;
        }
    }

    $turnosActivos = array_merge($turnosEnCola, $turnosSeleccionando);
    
    // Leer archivo fila
    $detalles = [];
    if (file_exists($nombreArchivoFila)) 
    {
        try 
        {
            $contenido = file_get_contents($nombreArchivoFila);
            $registrosFila = json_decode($contenido, true) ?: [];
            
            // Filtrar registros: estado 0 si están en cola Redis, estado 2 si están en hash seleccionando
            foreach ($registrosFila as $registro) 
            {
                $mostrarRegistro = false;

                if (isset($registro['estado'])) 
                {
                    $estado = intval($registro['estado']);

                    if ($estado == 0) 
                    {
                        // Para estado 0: verificar que esté en la cola Redis
                        if (isset($registro['turno']) && isset($turnosEnCola[$registro['turno']]) && $registro['clvuni'] === $turnosEnCola[$registro['turno']]) 
                        {
                            $mostrarRegistro = true;
                        }
                    } 
                    elseif ($estado == 2) 
                    {
                        // Para estado 2: verificar que esté en el hash seleccionando
                        if (isset($seleccionandoHash[$registro['clvuni']])) 
                        {
                            $mostrarRegistro = true;
                        }
                    }
                }

                if ($mostrarRegistro) 
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
        catch (Exception $e) 
        {
            error_log("Error al parsear fila: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'total_cola' => $redis->lLen($nombreCola),
        'total_activos' => count($detalles),
        'detalles' => $detalles
    ]);
}
catch (Exception $e) 
{
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>