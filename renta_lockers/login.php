<?php
session_start();
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

$error_msg = false;
$success_msg = false;

// Si el usuario ya está logueado, redirigir a dashboard
if (isset($_SESSION['clvuni'])) 
{
    header('Location: dashboard.php');
    exit;
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clvuni']) && isset($_POST['curp'])) {
    $clvuni = trim($_POST['clvuni']);
    $curp = trim(strtoupper($_POST['curp'])); // CURP es case-insensitive, normalizar a mayúsculas
    
    if (!empty($clvuni) && !empty($curp)) 
    {
        require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');
        
        // Consulta segura para validar clave única y CURP
        $query = "SELECT clave_unica, nombres, ape_pat, ape_mat, curp 
                  FROM plantilla.fcq_alumno 
                  WHERE clave_unica = ? AND UPPER(curp) = ?";
        
        $stmt = mysqli_prepare($dbh, $query);
        
        if ($stmt) 
        {
            mysqli_stmt_bind_param($stmt, "ss", $clvuni, $curp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            $existe = mysqli_stmt_num_rows($stmt);
            
            if ($existe > 0) {
                // Obtener los resultados
                mysqli_stmt_bind_result($stmt, $clave_unica, $nombres, $ape_pat, $ape_mat, $curp_verificado);
                mysqli_stmt_fetch($stmt);
                
                // Guardar en sesión
                $_SESSION['clvuni'] = $clave_unica;   
                $_SESSION['nombres'] = $nombres;
                $_SESSION['ape_pat'] = $ape_pat;
                $_SESSION['ape_mat'] = $ape_mat;
                $_SESSION['nombre_completo'] = trim($nombres . ' ' . $ape_pat . ' ' . $ape_mat);
                $_SESSION['curp'] = $curp_verificado;
                
                mysqli_stmt_close($stmt);
                mysqli_close($dbh);
                
                // Redirigir a dashboard
                header('Location: dashboard.php');
                exit;
            } 
            else 
            {
                $error_msg = "Clave única o CURP inválidos. Por favor, intenta de nuevo.";
            }
            
            mysqli_stmt_close($stmt);
        } 
        else 
        {
            $error_msg = "Error en la consulta. Por favor, intenta de nuevo.";
        }
        
        mysqli_close($dbh);
    } 
    else 
    {
        $error_msg = "Por favor, completa todos los campos.";
    }
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <title>FCQ :: Lockers - Login</title>
    
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/jquery-3.4.1.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/pagina.css?0001" />
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/js/bootstrap.min.js"></script>
    
    <style>
        .login-container {
            max-width: 400px;
            margin: 60px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .form-group label {
            font-weight: 500;
        }
        .error-msg {
            color: #d9534f;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            border-radius: 4px;
        }
        .form-control {
            margin-bottom: 15px;
        }
        .btn-block {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif;">
        <div class="login-container">
            <h2>Renta de Lockers</h2>
            <h4 style="text-align: center; color: #666; font-size: 14px; margin-bottom: 30px;">Iniciar Sesión</h4>
            
            <?php if ($error_msg): ?>
                <div class="error-msg">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label for="clvuni">Clave Única / Número de Control:</label>
                    <input type="text" 
                           id="clvuni" 
                           name="clvuni" 
                           class="form-control" 
                           placeholder="Ej: A00123456" 
                           required 
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label for="curp">CURP:</label>
                    <input type="text" 
                           id="curp" 
                           name="curp" 
                           class="form-control" 
                           placeholder="Ej: ABCD123456HDFXXX01" 
                           maxlength="18"
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
            </form>
            
            <hr>
            <p style="text-align: center; font-size: 12px; color: #999;">
                ¿Necesitas ayuda? Contacta al administrador del sistema.
            </p>
        </div>

        <?php include($www_footer); ?>
    </div>
</body>

<script type="text/javascript" src="js/plantilla.js?0001"></script>

</html>