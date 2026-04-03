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

// Obtener parámetros
$id_l = isset($_POST['id_l']) ? intval($_POST['id_l']) : null;
$clvuni = isset($_POST['clvuni']) ? trim($_POST['clvuni']) : null;
$ciclo = isset($_POST['ciclo']) ? trim($_POST['ciclo']) : null;

// Validar parámetros
if (!$id_l || !$clvuni || !$ciclo) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

// Validar que el usuario de la sesión coincida
if ($_SESSION['clvuni'] != $clvuni) {
    echo json_encode(['status' => 'error', 'message' => 'Validación de usuario fallida']);
    exit;
}

try {
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

    // ✅ PASO 1: Verificar que el locker exista y esté activo
    $queryVerificar = "SELECT id, numero, activo FROM plantilla.loc_locker WHERE id = ? LIMIT 1";
    $stmtVerificar = mysqli_prepare($dbh, $queryVerificar);
    
    if (!$stmtVerificar) {
        throw new Exception("Error en prepared statement: " . mysqli_error($dbh));
    }
    
    mysqli_stmt_bind_param($stmtVerificar, "i", $id_l);
    
    if (!mysqli_stmt_execute($stmtVerificar)) {
        throw new Exception("Error al ejecutar query: " . mysqli_stmt_error($stmtVerificar));
    }
    
    $resultVerificar = mysqli_stmt_get_result($stmtVerificar);
    
    if (!$resultVerificar || mysqli_num_rows($resultVerificar) == 0) {
        mysqli_stmt_close($stmtVerificar);
        mysqli_close($dbh);
        echo json_encode(['status' => 'error', 'message' => 'El locker no existe']);
        exit;
    }
    
    $lockerInfo = mysqli_fetch_assoc($resultVerificar);
    $numeroLocker = $lockerInfo['numero'];
    $activoLocker = $lockerInfo['activo'];
    
    // Verificar que esté activo
    if ($activoLocker == 0) {
        mysqli_stmt_close($stmtVerificar);
        mysqli_close($dbh);
        echo json_encode(['status' => 'error', 'message' => 'El locker no está activo']);
        exit;
    }
    
    mysqli_stmt_close($stmtVerificar);
    
    // ✅ PASO 2: Verificar que el locker no esté ya reservado
    $queryReservado = "SELECT id FROM plantilla.loc_reserva WHERE id_l = ? AND estado = 1 LIMIT 1";
    $stmtReservado = mysqli_prepare($dbh, $queryReservado);
    
    if (!$stmtReservado) {
        throw new Exception("Error en prepared statement: " . mysqli_error($dbh));
    }
    
    mysqli_stmt_bind_param($stmtReservado, "i", $id_l);
    
    if (!mysqli_stmt_execute($stmtReservado)) {
        throw new Exception("Error al ejecutar query: " . mysqli_stmt_error($stmtReservado));
    }
    
    $resultReservado = mysqli_stmt_get_result($stmtReservado);
    
    if ($resultReservado && mysqli_num_rows($resultReservado) > 0) {
        mysqli_stmt_close($stmtReservado);
        mysqli_close($dbh);
        echo json_encode(['status' => 'error', 'message' => 'Este locker ya ha sido reservado']);
        exit;
    }
    
    mysqli_stmt_close($stmtReservado);
    
    // ✅ PASO 3: Verificar que el usuario no tenga ya una reserva activa
    $queryUserReserva = "SELECT id FROM plantilla.loc_reserva WHERE clave_unica = ? AND ciclo = ? AND estado = 1 LIMIT 1";
    $stmtUserReserva = mysqli_prepare($dbh, $queryUserReserva);
    
    if (!$stmtUserReserva) {
        throw new Exception("Error en prepared statement: " . mysqli_error($dbh));
    }
    
    mysqli_stmt_bind_param($stmtUserReserva, "ss", $clvuni, $ciclo);
    
    if (!mysqli_stmt_execute($stmtUserReserva)) {
        throw new Exception("Error al ejecutar query: " . mysqli_stmt_error($stmtUserReserva));
    }
    
    $resultUserReserva = mysqli_stmt_get_result($stmtUserReserva);
    
    if ($resultUserReserva && mysqli_num_rows($resultUserReserva) > 0) {
        mysqli_stmt_close($stmtUserReserva);
        mysqli_close($dbh);
        echo json_encode(['status' => 'error', 'message' => 'Ya tienes un locker reservado en este ciclo']);
        exit;
    }
    
    mysqli_stmt_close($stmtUserReserva);
    
    // ✅ PASO 4: INSERTAR la reserva
    $fechaRenta = date('Y-m-d H:i:s');
    $estado = 1;

    $queryInsertar = "INSERT INTO plantilla.loc_reserva (id_l, clave_unica, ciclo, fecha_r, fecha_c, fecha_p, estado) 
                      VALUES (?, ?, ?, ?, NULL, NULL, ?)";
    $stmtInsertar = mysqli_prepare($dbh, $queryInsertar);
    
    if (!$stmtInsertar) {
        throw new Exception("Error en prepared statement: " . mysqli_error($dbh));
    }

    mysqli_stmt_bind_param($stmtInsertar, "isssi", $id_l, $clvuni, $ciclo, $fechaRenta, $estado);
    
    if (!mysqli_stmt_execute($stmtInsertar)) {
        throw new Exception("Error al ejecutar insert: " . mysqli_stmt_error($stmtInsertar));
    }

    $idReserva = mysqli_insert_id($dbh);
    mysqli_stmt_close($stmtInsertar);
    mysqli_close($dbh);

    // Limpiar estado de selección en Redis si existe
    try {
        require($_SERVER['DOCUMENT_ROOT'].'/renta_lockers/redis/comun/conexion_redis.php');
        if (!isset($error_redis) || !$error_redis) {
            $claveSeleccionando = 'locker:seleccionando';
            $redis->hDel($claveSeleccionando, $clvuni);
        }
    } catch (Exception $e) {
        // No interrumpir si Redis falla aquí, la reserva ya fue registrada.
    }

    // ✅ Respuesta exitosa
    echo json_encode([
        'status' => 'success',
        'message' => 'Locker reservado correctamente',
        'id_reserva' => $idReserva,
        'id_l' => $id_l,
        'numero_locker' => $numeroLocker,
        'fecha_r' => $fechaRenta
    ]);

} catch (Exception $e) {
    // Cierra conexión en caso de error
    if (isset($dbh)) {
        mysqli_close($dbh);
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>