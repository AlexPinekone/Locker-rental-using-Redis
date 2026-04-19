<?php
header('Content-Type: application/json');

require($_SERVER['DOCUMENT_ROOT'] . '/comun/conectar.php');

try 
{
    $query = "SELECT fecha_ini, hora_ini, fecha_fin, hora_fin, costo 
              FROM plantilla.loc_config 
              LIMIT 1";

    $resultado = mysqli_query($dbh, $query);

    if (!$resultado) 
    {
        throw new Exception("Error en la consulta: " . mysqli_error($dbh));
    }

    $config = mysqli_fetch_assoc($resultado);

    if (!$config) 
    {
        // Si no hay registro aún, devolver valores vacíos
        $config = [
            'fecha_ini' => '',
            'hora_ini'  => '',
            'fecha_fin' => '',
            'hora_fin'  => '',
            'costo'     => ''
        ];
    }

    echo json_encode([
        'status' => 'success',
        'config' => $config
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