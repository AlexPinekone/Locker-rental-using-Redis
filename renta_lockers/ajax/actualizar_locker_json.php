<?php

header('Content-Type: application/json');
session_start();

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');

if (!isset($_SESSION['clvuni'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

// Obtener parámetros
$clvuni = isset($_POST['clvuni']) ? trim($_POST['clvuni']) : null;
$id_l = isset($_POST['id_l']) ? intval($_POST['id_l']) : null;
$ciclo = isset($_POST['ciclo']) ? trim($_POST['ciclo']) : null;

// Validar parámetros
if (!$clvuni || !$id_l || !$ciclo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parámetros incompletos',
        'received' => [
            'clvuni' => isset($_POST['clvuni']) ? $_POST['clvuni'] : null,
            'id_l' => isset($_POST['id_l']) ? $_POST['id_l'] : null,
            'ciclo' => isset($_POST['ciclo']) ? $_POST['ciclo'] : null,
        ]
    ]);
    exit;
}

// Validar que el usuario de sesión coincida
if ($_SESSION['clvuni'] != $clvuni) {
    echo json_encode(['status' => 'error', 'message' => 'Validación de usuario fallida']);
    exit;
}

try 
{
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/conexion_redis.php');
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/utils.php');

    if (isset($error_redis) && $error_redis) 
    {
        throw new Exception($error_redis);
    }

    $fechaHoy = date('Y-m-d');
    $nombreArchivoFila = getNombreArchivoFila($redis, $fechaHoy);

    // Leer el archivo JSON de fila
    $datosFila = [];
    if (file_exists($nombreArchivoFila)) {
        $contenido = file_get_contents($nombreArchivoFila);
        $datosFila = json_decode($contenido, true) ?: [];
    }

    $resultadoAlumno = buscarRegistroAlumno($datosFila, $clvuni);
    $indiceAlumno = $resultadoAlumno['indice'];

    if ($indiceAlumno === null) {
        throw new Exception("El alumno no se encuentra en la fila");
    }

    // Actualizar el registro con el locker seleccionado usando la id de la BD
    $datosFila[$indiceAlumno]['locker'] = intval($id_l);
    $datosFila[$indiceAlumno]['fecha_hora_asignacion'] = date('Y-m-d H:i:s');
    $datosFila[$indiceAlumno]['estado'] = 3;

    // Guardar el archivo actualizado
    guardarEnArchivo($nombreArchivoFila, $datosFila);

    echo json_encode([
        'status' => 'success',
        'message' => 'Locker actualizado en la fila',
        'locker_asignado' => intval($id_l),
        'clvuni' => $clvuni,
        'fecha_asignacion' => $datosFila[$indiceAlumno]['fecha_hora_asignacion']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>