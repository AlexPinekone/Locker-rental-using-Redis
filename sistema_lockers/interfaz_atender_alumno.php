<?php
session_start();
$_SESSION['ciclo'] = "2025-2026-II";

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <title>FCQ :: Sistema de Lockers</title>
    
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/jquery-3.4.1.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/pagina.css?0001" />
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/js/bootstrap.min.js"></script>
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootbox.min.js"></script>
    
    <!-- Scripts de módulos -->
    <script type="text/javascript" src="js/fila_virtual.js"></script>
    <script type="text/javascript" src="js/modulos.js"></script>
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif;">
        <div class="container" style="margin-top: 20px;">
            <h1 style="text-align: center; margin-bottom: 30px;">Sistema de Lockers - FCQ</h1>
            
            <!-- Nav de pestañas -->
            <ul class="nav nav-tabs" role="tablist" id="menu-principal">
                <!-- Química -->
                <li role="presentation" class="active">
                    <a href="#quimica" aria-controls="quimica" role="tab" data-toggle="tab" class="tab-link" data-modulo="quimica">
                        La Químicarrera
                    </a>
                </li>
                
                <!--Menu Desplegable -->
                <li role="presentation" class="dropdown">
                    <a href="#" id="menu-desplegable" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                        Locker <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="#fila-virtual" data-toggle="tab" class="tab-link" data-modulo="fila_virtual">
                             Fila Virtual
                        </a></li>
                        <li><a href="#renta-locker" data-toggle="tab" class="tab-link" data-modulo="renta_locker">
                             Renta de Locker
                        </a></li>
                        <li><a href="#configuracion" data-toggle="tab" class="tab-link" data-modulo="configuracion">
                             Configuración
                        </a></li>
                    </ul>
                </li>
            </ul>

            <!-- Contenido de las pestañas -->
            <div class="tab-content" style="margin-top: 20px;">
                
                <!-- Pestaña: Química -->
                <div role="tabpanel" class="tab-pane active" id="quimica">
                    <div id="contenido-quimica" class="contenido-modulo">
                        <p style="text-align: center;">Cargando módulo Química...</p>
                    </div>
                </div>

                <!-- Pestaña: Fila Virtual -->
                <div role="tabpanel" class="tab-pane" id="fila-virtual">
                    <div id="contenido-fila-virtual" class="contenido-modulo">
                        <p style="text-align: center;">Cargando Fila Virtual...</p>
                    </div>
                </div>

                <!-- Pestaña: Renta Locker -->
                <div role="tabpanel" class="tab-pane" id="renta-locker">
                    <div id="contenido-renta-locker" class="contenido-modulo">
                        <p style="text-align: center;">Cargando Renta de Locker...</p>
                    </div>
                </div>

                <!-- Pestaña: Configuración -->
                <div role="tabpanel" class="tab-pane" id="configuracion">
                    <div id="contenido-configuracion" class="contenido-modulo">
                        <p style="text-align: center;">Cargando Configuración...</p>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div id="cargando" style="position:fixed; top:50%; left:50%; width:70px; height:65px; margin-top:-80px; margin-left:-35px; z-index:1000; display:none;">
                <div class="panel panel-default" style="width:70px; height:65px">
                    <img style="margin-top: 10px; margin-left: 12px" width="45px" class="center-block topmargin50" src="../comun/imagenes/cargando.gif" />
                </div>
            </div>
        </div>

        <script type="text/javascript">
            cargarModulo('quimica');
        </script>

        <?php include($www_footer); ?>
    </div>
</body>
</html>