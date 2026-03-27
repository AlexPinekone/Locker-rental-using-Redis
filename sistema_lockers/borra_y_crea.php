<?php
    header('Content-Type: application/json');
    $response = array('status' => 'ERROR', 'inserted' => 0);
    
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

    function generarCadenaAleatoria($longitud = 10) 
    {
        $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($caracteres), 0, $longitud);
    }

    function generarApellido($longitud = 12) 
    {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return ucfirst(substr(str_shuffle($caracteres), 0, $longitud));
    }

    /*
    // Deshabilitar restricciones de clave foránea
    mysqli_query($dbh, "SET FOREIGN_KEY_CHECKS=0");
    
    // Borrar registros de la tabla dependiente primero
    mysqli_query($dbh, "TRUNCATE TABLE plantilla.loc_reserva");
    
    // Luego borrar registros de la tabla principal
    mysqli_query($dbh, "TRUNCATE TABLE plantilla.fcq_alumno");
    
    // Rehabilitar restricciones
    mysqli_query($dbh, "SET FOREIGN_KEY_CHECKS=1");

    */

    $exitos = 0;

    for ($i = 0; $i < 100; $i++) 
    {
        $clvuni  = rand(100000, 999999);
        $nombres = generarCadenaAleatoria(15);
        $ape_pat = generarApellido();
        $ape_mat = generarApellido();

        $query = "INSERT INTO plantilla.fcq_alumno (clave_unica, nombres, ape_pat, ape_mat, curp) 
                  VALUES ($clvuni, '$nombres', '$ape_pat', '$ape_mat', '123')";
        
        if (mysqli_query($dbh, $query)) 
        {
            $exitos++;
        }
    }

    if ($exitos > 0) 
    {
        $response['status'] = 'OK';
        $response['message'] = "Se insertaron $exitos registros correctamente.";
        $response['inserted'] = $exitos;
    } 
    else 
    {
        $response['status'] = 'ERROR';
        $response['message'] = 'No se pudo insertar ningún registro.';
    }

    mysqli_close($dbh);
    echo json_encode($response);
?>
