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
    echo json_encode(['status' => 'error', 'message' => 'Validación de usuario fallida: clv: '.$_SESSION['clvuni']]);
    exit;
}

try {
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

    // Verificar que el locker exista
    $queryVerificar = "SELECT numero FROM plantilla.loc_locker WHERE numero = ? AND activo = 1";
    $stmtVerificar = mysqli_prepare($dbh, $queryVerificar);
    mysqli_stmt_bind_param($stmtVerificar, "i", $id_l);
    mysqli_stmt_execute($stmtVerificar);
    mysqli_stmt_store_result($stmtVerificar);

    if (mysqli_stmt_num_rows($stmtVerificar) == 0) 
    {
        echo json_encode(['status' => 'error', 'message' => 'El locker no existe o no está activo']);
        mysqli_stmt_close($stmtVerificar);
        mysqli_close($dbh);
        exit;
    }
    mysqli_stmt_close($stmtVerificar);

    // Verificar que el locker no esté ya reservado
    $queryReservado = "SELECT id FROM plantilla.loc_reserva WHERE id_l = ? AND estado = 1";
    $stmtReservado = mysqli_prepare($dbh, $queryReservado);
    mysqli_stmt_bind_param($stmtReservado, "i", $id_l);
    mysqli_stmt_execute($stmtReservado);
    mysqli_stmt_store_result($stmtReservado);

    if (mysqli_stmt_num_rows($stmtReservado) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Este locker ya ha sido reservado']);
        mysqli_stmt_close($stmtReservado);
        mysqli_close($dbh);
        exit;
    }
    mysqli_stmt_close($stmtReservado);

    // Verificar que el locker no este inactivo
    $queryInactivo = "SELECT id FROM plantilla.loc_locker WHERE id = ? AND activo = 0";
    $stmtInactivo = mysqli_prepare($dbh, $queryInactivo);
    mysqli_stmt_bind_param($stmtInactivo, "i", $id_l);
    mysqli_stmt_execute($stmtInactivo);
    mysqli_stmt_store_result($stmtInactivo);

    if (mysqli_stmt_num_rows($stmtInactivo) > 0) 
    {
        echo json_encode(['status' => 'error', 'message' => 'Este locker ya ha sido reservado']);
        mysqli_stmt_close($stmtInactivo);
        mysqli_close($dbh);
        exit;
    }
    mysqli_stmt_close($stmtInactivo);

    // Verificar que el usuario no tenga ya una reserva activa
    $queryUserReserva = "SELECT id FROM plantilla.loc_reserva WHERE clave_unica = ? AND ciclo = ? AND estado = 1";
    $stmtUserReserva = mysqli_prepare($dbh, $queryUserReserva);
    mysqli_stmt_bind_param($stmtUserReserva, "ss", $clvuni, $ciclo);
    mysqli_stmt_execute($stmtUserReserva);
    mysqli_stmt_store_result($stmtUserReserva);

    if (mysqli_stmt_num_rows($stmtUserReserva) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ya tienes un locker reservado en este ciclo']);
        mysqli_stmt_close($stmtUserReserva);
        mysqli_close($dbh);
        exit;
    }
    mysqli_stmt_close($stmtUserReserva);

    // Insertar la reserva
    $fechaRenta = date('Y-m-d H:i:s');
    $estado = 1;

    $queryInsertar = "INSERT INTO loc_reserva (id_l, clave_unica, ciclo, fecha_r, fecha_c, fecha_p, estado) 
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

    // Respuesta exitosa
    echo json_encode([
        'status' => 'success',
        'message' => 'Locker reservado correctamente',
        'id_reserva' => $idReserva,
        'id_l' => $id_l,
        'fecha_r' => $fechaRenta
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>