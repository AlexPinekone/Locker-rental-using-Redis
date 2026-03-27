<?php
/**
 * actualizar_locker_json.php
 * 
 * Actualiza el archivo JSON de fila con el locker seleccionado por el alumno
 * Se llama desde JavaScript cuando el alumno confirma la selección de locker
 * 
 * Parámetros POST:
 * - clvuni: Clave única del alumno
 * - id_edificio: Código del edificio (ej: "A", "B")
 * - numero_locker: Número del locker (ej: 5, 12)
 * - ciclo: Ciclo (ej: 2025)
 */

header('Content-Type: application/json');
session_start();

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');

if (!isset($_SESSION['clvuni'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

// Obtener parámetros
$clvuni = isset($_POST['clvuni']) ? trim($_POST['clvuni']) : null;
$id_edificio = isset($_POST['id_edificio']) ? trim($_POST['id_edificio']) : null;
$numero_locker = isset($_POST['numero_locker']) ? intval($_POST['numero_locker']) : null;
$ciclo = isset($_POST['ciclo']) ? trim($_POST['ciclo']) : null;

// Validar parámetros
if (!$clvuni || !$id_edificio || !$numero_locker || !$ciclo) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

// Validar que el usuario de sesión coincida
if ($_SESSION['clvuni'] != $clvuni) {
    echo json_encode(['status' => 'error', 'message' => 'Validación de usuario fallida']);
    exit;
}

try {
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/conexion_redis.php');
    require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/utils.php');

    if (isset($error_redis) && $error_redis) {
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

    //PROBLEMA -> Funcion correcta: buscarRegistroAlumno() en utils
    // Buscar al alumno en la fila
    $indiceAlumno = null;
    for ($i = count($datosFila) - 1; $i >= 0; $i--) {
        if (isset($datosFila[$i]['clvuni']) && $datosFila[$i]['clvuni'] === $clvuni) {
            $indiceAlumno = $i;
            break;
        }
    }

    if ($indiceAlumno === null) {
        throw new Exception("El alumno no se encuentra en la fila");
    }

    // Actualizar el registro con el locker seleccionado
    // Formato: "Edificio-NumeroLocker" (ej: "A-5", "B-12") 
    $numeroLockerFormato = $id_edificio . '-' . $numero_locker;

    $datosFila[$indiceAlumno]['locker'] = $numeroLockerFormato;
    $datosFila[$indiceAlumno]['fecha_hora_asignacion'] = date('Y-m-d H:i:s');

    // Guardar el archivo actualizado
    guardarEnArchivo($nombreArchivoFila, $datosFila);

    echo json_encode([
        'status' => 'success',
        'message' => 'Locker actualizado en la fila',
        'locker_asignado' => $numeroLockerFormato,
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