<?php
header('Content-Type: application/json');
session_start();

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

// Verificar sesión
if (!isset($_SESSION['clvuni'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

$clvuni = $_SESSION['clvuni'];
$fechaHoy = date('Y-m-d H:i:s');

try {
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

    // Actualizar la reserva: cambiar fecha_c a hoy y estado a 0
    $query = "UPDATE plantilla.loc_reserva 
              SET estado = 0, fecha_c = ? 
              WHERE clave_unica = ? AND estado IN (1, 2) 
              LIMIT 1";
    
    $stmt = mysqli_prepare($dbh, $query);
    
    if (!$stmt) {
        throw new Exception("Error en prepared statement: " . mysqli_error($dbh));
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $fechaHoy, $clvuni);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar update: " . mysqli_stmt_error($stmt));
    }
    
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($dbh);
    
    if ($affectedRows > 0) {
        // Limpiar estado en Redis si existe
        try {
            require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/conexion_redis.php');
            if (!isset($error_redis) || !$error_redis) {
                $redis->hDel('locker:seleccionando', $clvuni);
            }
        } catch (Exception $e) {
            // No interrumpir si Redis falla
        }

        // Actualizar el estado en el archivo JSON a 5 (cancelado)
        try {
            require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/utils.php');
            if (!isset($error_redis) || !$error_redis) 
            {
                $fechaHoy = date('Y-m-d');
                $nombreArchivoFila = getNombreArchivoFila($redis, $fechaHoy);
                
                if (file_exists($nombreArchivoFila)) 
                {
                    $contenido = file_get_contents($nombreArchivoFila);
                    $datosFila = json_decode($contenido, true) ?: [];
                    
                    $resultado = buscarRegistroAlumno($datosFila, $clvuni);
                    if ($resultado['indice'] !== null) 
                    {
                        $datosFila[$resultado['indice']]['estado'] = 5; // Cancelado
                        guardarEnArchivo($nombreArchivoFila, $datosFila);
                    }
                }
            }
        } catch (Exception $e) {
            // No interrumpir si falla la actualización del JSON
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Reserva cancelada correctamente',
            'fecha_cancelacion' => $fechaHoy
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No se encontró una reserva activa para cancelar'
        ]);
    }

} catch (Exception $e) {
    if (isset($dbh)) {
        mysqli_close($dbh);
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
