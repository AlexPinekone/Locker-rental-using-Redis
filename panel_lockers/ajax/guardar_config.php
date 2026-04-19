<?php
header('Content-Type: application/json');

require($_SERVER['DOCUMENT_ROOT'] . '/comun/conectar.php');

try 
{
    $fecha_ini = isset($_POST['fecha_ini']) ? $_POST['fecha_ini'] : null;
    $hora_ini  = isset($_POST['hora_ini'])  ? $_POST['hora_ini']  : null;
    $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
    $hora_fin  = isset($_POST['hora_fin'])  ? $_POST['hora_fin']  : null;
    $costo     = isset($_POST['costo'])     ? $_POST['costo']     : null;

    if (!$fecha_ini || !$hora_ini || !$fecha_fin || !$hora_fin || $costo === null) 
    {
        throw new Exception("Todos los campos son requeridos.");
    }

    // Verificar si ya existe un registro
    $checkQuery = "SELECT COUNT(*) as total FROM plantilla.loc_config";
    $checkResult = mysqli_query($dbh, $checkQuery);
    $checkRow = mysqli_fetch_assoc($checkResult);

    if ($checkRow['total'] > 0) 
    {
        // Actualizar registro existente
        $query = "UPDATE plantilla.loc_config 
                  SET fecha_ini = ?, hora_ini = ?, fecha_fin = ?, hora_fin = ?, costo = ?";
        $stmt = mysqli_prepare($dbh, $query);
        mysqli_stmt_bind_param($stmt, "ssssd", $fecha_ini, $hora_ini, $fecha_fin, $hora_fin, $costo);
    }
    else 
    {
        // Insertar primer registro
        $query = "INSERT INTO plantilla.loc_config (fecha_ini, hora_ini, fecha_fin, hora_fin, costo) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($dbh, $query);
        mysqli_stmt_bind_param($stmt, "ssssd", $fecha_ini, $hora_ini, $fecha_fin, $hora_fin, $costo);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Configuración guardada correctamente.'
    ]);
}
catch (Exception $e) 
{
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
finally 
{
    mysqli_close($dbh);
}
?>