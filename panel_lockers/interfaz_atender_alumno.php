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
    
    <style>
        .botones-menu {
            margin-top: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn-modulo {
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-modulo.activo {
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        
        .dropdown-menu > li > a {
            cursor: pointer;
            padding: 10px 20px;
        }
        
        .dropdown-menu > li > a:hover {
            background-color: #f5f5f5;
        }
        
        .contenido-modulo {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif;">
        <div class="container" style="margin-top: 20px;">
            <h1 style="text-align: center; margin-bottom: 30px;">Sistema de Lockers - FCQ</h1>
            
            <!-- Botones de Menú -->
            <div class="botones-menu">
                <!-- Botón Química -->
                <button type="button" class="btn btn-info btn-modulo activo" data-modulo="quimica">
                    <i class="glyphicon glyphicon-flask"></i> La Química
                </button>
                
                <!-- Botón Desplegable Locker -->
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="glyphicon glyphicon-folder-open"></i> Locker <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="menu-modulo" data-modulo="fila_virtual">
                            <i class="glyphicon glyphicon-list"></i> Fila Virtual
                        </a></li>
                        <li><a class="menu-modulo" data-modulo="renta_locker">
                            <i class="glyphicon glyphicon-tag"></i> Renta de Locker
                        </a></li>
                        <li><a class="menu-modulo" data-modulo="configuracion">
                            <i class="glyphicon glyphicon-cog"></i> Configuración
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Contenedor de Módulos -->
            <div id="contenedor-modulos" style="margin-top: 20px; min-height: 400px;">
                <div id="contenido-actual" class="contenido-modulo">
                    <p style="text-align: center;">Cargando módulo...</p>
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
            // Cargar módulo inicial
            cargarModulo('quimica');
        </script>

        <?php include($www_footer); ?>
    </div>
</body>
</html>