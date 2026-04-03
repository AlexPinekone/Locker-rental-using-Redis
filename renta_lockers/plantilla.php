<?php
session_start();
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

$error_msg = false;
$sistemaCerrado = false;

//Guardar la clave en sesión despues de completar el formulario
if (isset($_POST['clvuni'])) 
{
    $_SESSION['clvuni'] = $_POST['clvuni'];
}

//Obtener la clave del alumno mediante la sesión
$alumnoLogueado = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;

//Verificar que el sistema este abierto
if ($alumnoLogueado) {
    // Verificar sistema ANTES de validar al alumno
    require('redis/comun/conexion_redis.php');
    if ($redis->get('config:estado_sistema') !== 'abierto') {
        $sistemaCerrado = true;
        $alumnoLogueado = null;
    }
}


//Verificar que la clave en la sesion sea valida
if ($alumnoLogueado) 
{
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');
    
    //Consulta segura para obtener clave_unica, nombre, apellido_paterno y apellido_materno
    $query = "SELECT clave_unica, nombres, ape_pat, ape_mat FROM plantilla.fcq_alumno WHERE clave_unica = ?";
    $stmt = mysqli_prepare($dbh, $query);
    
    mysqli_stmt_bind_param($stmt, "s", $alumnoLogueado);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    $existe = mysqli_stmt_num_rows($stmt);

    if ($existe > 0) 
    {
        // Obtener los resultados
        mysqli_stmt_bind_result($stmt, $clave_unica, $nombres, $ape_pat, $ape_mat);
        mysqli_stmt_fetch($stmt);
        
        // Guardar en sesión
        $_SESSION['clave_unica'] = $clave_unica;
        $_SESSION['nombres'] = $nombres;
        $_SESSION['ape_pat'] = $ape_pat;
        $_SESSION['ape_mat'] = $ape_mat;
        
        // Crear nombre completo en sesión
        $_SESSION['nombre_completo'] = trim($nombres . ' ' . $ape_pat . ' ' . $ape_mat);
    } 
    else 
    {
        $error_msg = "ID inválido: El alumno no se encuentra registrado en el sistema.";
        unset($_SESSION['clvuni']);
        $alumnoLogueado = null;
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($dbh);
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<!--<link rel="shortcut icon" href="fcq.ico" type="image/ico">-->

	<title>FCQ :: Lockers</title>
	
	<script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/jquery-3.4.1.min.js"></script>

	<link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/css/bootstrap.min.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/pagina.css?0001" />
	
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/js/bootstrap.min.js"></script>
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootbox.min.js"></script>
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif; text-align: center;">
        <div class="container">
            <div id="contenido" style="height:500px">
                <h2>Renta de Lockers</h2>

                <div id="renta_nueva" style="padding: 30px;">
                    <?php if ($sistemaCerrado): ?>
                        <div class="alert alert-warning" style="margin-top: 30px;">
                            <h4>Sistema no disponible</h4>
                            <p>El sistema de lockers aún no ha sido abierto por el administrador.</p>
                            <p style="font-size: 0.85em; color: #666;">Intenta de nuevo más tarde.</p>
                        </div>
                        <a href="cerrar_sesion.php" class="btn btn-secondary">Regresar</a>
                    <!--Error. El alumno no esta en la base de datos-->
                    <?php elseif ($error_msg): ?>
                        <p class="error" style="color:red;"><?php echo $error_msg; ?></p>
                        <!--Salir de esta pagina-->
                        <a href="index.php" class="btn btn-secondary">Regresar</a>
                    <!--Formulario. Ingresar a la cola-->
                    <?php elseif (!$alumnoLogueado): ?>
                        <p style="font-size:15px;">Ingresa tu n&uacute;mero de control o ID:</p>
                        
                        <form action="" method="POST">
                            <input type="text" id="clvuni" name="clvuni" class="form-control" style="width: 200px; margin: 0 auto;" required>
                            <br>
                            <button type="submit" class="btn btn-primary">Entrar a la cola</button>
                        </form>
                    <!--Vista: Esperando a que sea tu turno-->
                    <?php else: ?>
                        <h2><span id="clave-alumno"><?php echo "Tu clave unica es: " . htmlspecialchars($alumnoLogueado); ?></span></h2>
                        <div id="contenedor-estado">
                            <p>Tu turno es:</p>
                                <div id="turno" style="font-size: 2em; font-weight: bold;">...</div>
                            <p>Personas delante de ti:</p>
                                <div id="numero_delante" style="font-size: 2em; font-weight: bold;">...</div>
                            <p style="font-size: 0.8em; color: #666;">La p&aacute;gina se actualiza sola. No la cierres.</p>
                            <br>
                            <div style="margin-top: 20px;">
                                <button class="btn btn-sm btn-warning" onclick="salirCola()">Salir de la cola</button>
                                <a href="cerrar_sesion.php" class="btn btn-sm btn-danger">Cerrar Sesión</a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
                <!--Script. Unirse a la cola y esperar-->
                <?php if ($alumnoLogueado): ?>
				<script>
                    const alumnoId = "<?php echo htmlspecialchars($alumnoLogueado); ?>";
                    $('#cargando').show();

                    let intervalo = null;

                    $.post('redis/cola/unirse_cola.php')
                        .done(function(data) 
                        {
                            $('#cargando').hide();
                            if (data.status === 'sistema_cerrado') 
                            {
                                $('#contenedor-estado').html(`
                                    <div class="alert alert-warning">
                                        <h4>Sistema no disponible</h4>
                                        <p>${data.message}</p>
                                    </div>
                                    <a href="cerrar_sesion.php" class="btn btn-secondary">Salir</a>
                                `);
                                return;
                            }
                            if (data.status === 'error') 
                            {
                                $('#contenedor-estado').html(`
                                    <p style="color:red;">Error al unirse a la cola: ${data.message}</p>
                                    <a href="cerrar_sesion.php" class="btn btn-danger">Salir</a>
                                `);
                                return;
                            }
                            revisarTurno();
                            intervalo = setInterval(() => {
                                if (document.querySelector('#atendido')) 
                                {
                                    clearInterval(intervalo);
                                } 
                                else 
                                {
                                    revisarTurno();
                                }
                            }, 3000);
                        })
                        .fail(function() 
                        {
                            console.error('No se pudo contactar a unirse_cola.php');
                        });
    
                    function revisarTurno() 
                    {
                        $.getJSON('redis/cola/consultar_estado.php')
                            .done(function(data) 
                            {
                                if (data.status === 'sistema_cerrado') 
                                {
                                    clearInterval(intervalo); // detener polling
                                    $('#contenedor-estado').html(`
                                        <div class="alert alert-warning">
                                            <h4>Sistema cerrado</h4>
                                            <p>${data.message}</p>
                                            <p style="font-size:0.85em; color:#666;">Has sido removido de la cola automáticamente.</p>
                                        </div>
                                        <a href="cerrar_sesion.php" class="btn btn-secondary">Salir</a>
                                    `);
                                }
                                else if (data.status === 'atendido' || data.status === 'seleccionando') 
                                {
                                    $('#contenedor-estado').html(`
                                        <p id="atendido" style="color:green; font-weight:bold; font-size:1.5em;">
                                            ¡ES TU TURNO!<br>Proceso de asignacion de lockers
                                        </p>
                                        <button class="btn btn-success" onclick="window.location.href='cerrar_sesion.php'">Finalizar</button>
                                    `);
                                } 
                                else if (data.status === 'esperando') 
                                {
                                    $('#numero_delante').text(data.posicion);
                                    $('#turno').text(data.turno);
                                }
                            })
                            .fail(function() 
                            {
                                console.error('Error al conectar con el servidor');
                            });
                    }

                    function salirCola() 
                    {
                        if (confirm('¿Estás seguro de que deseas salir de la cola?')) 
                        {
                            $.post('redis/cola/salir_cola.php')
                                .done(function(data) {
                                    if (data.status === 'success') 
                                    {
                                        $('#contenedor-estado').html(`
                                            <p style="color:green; font-weight:bold;">Has salido de la cola correctamente</p>
                                            <a href="index.php" class="btn btn-primary">Volver al inicio</a>
                                        `);
                                    } else {
                                        alert('Error: ' + data.message);
                                    }
                                })
                                .fail(function() {
                                    alert('Error al conectar con el servidor');
                                });
                        }
                    }
                </script>
                <?php endif; ?>
            </div>

            <div id="cargando" style="position:fixed; top:50%; left:50%; width:70px; height:65px; margin-top:-80px; margin-left:-35px; z-index:1000">
                <div class="panel panel-default" style="width:70px; height:65px">
                    <img style="margin-top: 10px; margin-left: 12px" width="45px" class="center-block topmargin50" src="../comun/imagenes/cargando.gif" />
                </div>
            </div>
        </div>
        <?php include($www_footer); ?>
    </div>
</body>

    <script type="text/javascript" src="js/plantilla.js?0001"></script>

</html>