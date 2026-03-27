<?php
header('Content-Type: application/json');

require($_SERVER['DOCUMENT_ROOT'] . '/comun/conectar.php');

try 
{
    $id_reserva   = isset($_POST['id_reserva'])   ? (int)$_POST['id_reserva']   : null;
    $nuevo_estado = isset($_POST['nuevo_estado'])  ? (int)$_POST['nuevo_estado'] : null;

    if (!$id_reserva || $nuevo_estado === null) 
    {
        throw new Exception("Parámetros inválidos.");
    }

    // Solo permitir cambios desde estado 1
    $checkQuery = "SELECT estado FROM plantilla.loc_reserva WHERE id = ?";
    $stmt = mysqli_prepare($dbh, $checkQuery);
    mysqli_stmt_bind_param($stmt, "i", $id_reserva);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $estadoActual);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($estadoActual !== 1) 
    {
        throw new Exception("Solo se puede cambiar el estado desde 'Reservado'.");
    }

    // Determinar qué fecha actualizar según el nuevo estado
    if ($nuevo_estado === 3) 
    {
        // Pagado → actualizar fecha_p
        $query = "UPDATE plantilla.loc_reserva 
                  SET estado = ?, fecha_p = NOW() 
                  WHERE id = ?";
    } 
    elseif ($nuevo_estado === 2) 
    {
        // Sin pago → actualizar fecha_c
        $query = "UPDATE plantilla.loc_reserva 
                  SET estado = ?, fecha_c = NOW() 
                  WHERE id = ?";
    } 
    else
    {
        throw new Exception("Estado no permitido.");
    }

    $stmt = mysqli_prepare($dbh, $query);
    mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $id_reserva);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Estado actualizado correctamente.'
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