<?php
header('Content-Type: application/json');

require($_SERVER['DOCUMENT_ROOT'] . '/comun/conectar.php');

try 
{
    $query = "
        SELECT 
        r.id,
        a.descripcion AS area,
        l.id AS id_locker,
        r.clave_unica,
        UPPER(CONCAT_WS(' ', al.nombres, al.ape_pat, al.ape_mat)) AS nombre_completo,
        r.fecha_r,
        r.fecha_c,
        r.fecha_p,
        r.estado
    FROM plantilla.loc_reserva r
    INNER JOIN (
        SELECT id_l, MAX(fecha_r) AS max_fecha
        FROM plantilla.loc_reserva
        GROUP BY id_l
    ) ultimas 
        ON r.id_l = ultimas.id_l 
        AND r.fecha_r = ultimas.max_fecha
    INNER JOIN plantilla.loc_locker l ON r.id_l = l.id
    INNER JOIN plantilla.loc_area a ON l.id_a = a.id
    INNER JOIN plantilla.fcq_alumno al ON r.clave_unica = al.clave_unica
    ORDER BY a.descripcion ASC, l.numero ASC;
    ";

    $resultado = mysqli_query($dbh, $query);

    if (!$resultado) {
        throw new Exception("Error en la consulta: " . mysqli_error($dbh));
    }

    $reservas = [];
    while ($fila = mysqli_fetch_assoc($resultado)) 
    {
        $reservas[] = $fila;
    }

    echo json_encode([
        'status'  => 'success',
        'total'   => count($reservas),
        'reservas'=> $reservas
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