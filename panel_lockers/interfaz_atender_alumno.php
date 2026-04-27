<?php
session_start();
//Asignar ciclo escolar de forma manual.
$_SESSION['ciclo'] = "2025-2026-II";

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <title>FCQ :: Sistema de Lockers</title>
    
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/pagina.css?0001" />
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/jquery-3.4.1.min.js"></script>
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/js/bootstrap.min.js"></script>
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootbox.min.js"></script>
    <script type="text/javascript" src="js/fila_virtual.js"></script>
   
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif;">
        <div class="container" style="margin-top: 20px;">
            <h1 style="text-align: center; margin-bottom: 30px;">Sistema de Lockers - FCQ</h1>
            
            <!-- Botones para las secciones del panel de administrador -->
            <ul class="nav nav-pills">
                <li><a href="?modulo=quimica">La Químicarrera</a></li>

                <li class="dropdown">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                        Locker <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="?modulo=fila_virtual">Fila Virtual</a></li>
                        <li><a href="?modulo=renta_locker">Renta de Locker</a></li>
                        <li><a href="?modulo=configuracion">Configuración</a></li>
                    </ul>
                </li>
            </ul>

            <div style="margin-top:20px; min-height:400px;">
                <?php
                $modulo = $_GET['modulo'] ?? 'quimica';

                $permitidos = ['quimica', 'fila_virtual', 'renta_locker', 'configuracion'];

                if (in_array($modulo, $permitidos)) 
                {
                    include "modulos/$modulo.php";
                } 
                else 
                {
                    echo "<div class='alert alert-danger'>Módulo no válido</div>";
                }
                ?>
            </div>

            <!-- Loading -->
            <div id="cargando" style="position:fixed; top:50%; left:50%; width:70px; height:65px; margin-top:-80px; margin-left:-35px; z-index:1000; display:none;">
                <div class="panel panel-default" style="width:70px; height:65px">
                    <img style="margin-top: 10px; margin-left: 12px" width="45px" class="center-block topmargin50" src="../comun/imagenes/cargando.gif" />
                </div>
            </div>
        </div>
        <?php include($www_footer); ?>
    </div>
</body>
</html>