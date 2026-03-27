<?php
session_start();
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['clvuni'])) {
    header('Location: login.php');
    exit;
}

$error_msg = false;
$sistemaCerrado = false;
$horaApertura = null;
$fechaApertura = null;

// Obtener información del sistema (si está abierto y horario)
require('redis/comun/conexion_redis.php');
$estadoSistema = $redis->get('config:estado_sistema');

if ($estadoSistema !== 'abierto') {
    $sistemaCerrado = true;
    
    // Obtener información de fecha y hora de apertura desde la base de datos
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');
    
    $query = "SELECT fecha_ini, hora_ini FROM loc_config LIMIT 1";
    $result = mysqli_query($dbh, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $fechaApertura = $row['fecha_ini'];
        $horaApertura = $row['hora_ini'];
    }
    
    mysqli_close($dbh);
}

// Recuperar datos de la sesión
$nombreCompleto = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'Usuario';
$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <title>FCQ :: Lockers - Panel Principal</title>
    
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/jquery-3.4.1.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $adds; ?>/pagina.css?0001" />
    
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/js/bootstrap.min.js"></script>
    <script type="text/javascript" language="javascript" src="<?php echo $adds; ?>/bootbox.min.js"></script>
    
    <style>
        .dashboard-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .user-info {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .user-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .user-clave {
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }
        .alert-closed {
            margin: 20px 0;
        }
        .horario-info {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .horario-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .horario-info strong {
            color: #856404;
        }
        .button-group {
            margin-top: 30px;
            text-align: center;
        }
        .button-group button, .button-group a {
            margin: 5px;
        }
        .status-open {
            color: #28a745;
            font-weight: bold;
        }
        .status-closed {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif;">
        <div class="dashboard-container">
            
            <!-- Información del Usuario -->
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nombreCompleto); ?></div>
                <div class="user-clave">Clave Única: <?php echo htmlspecialchars($clvuni); ?></div>
            </div>

            <!-- Verificar Estado del Sistema -->
            <?php if ($sistemaCerrado): ?>
                <!-- Sistema Cerrado -->
                <div class="alert alert-danger alert-closed">
                    <h4><span class="status-closed">● Sistema no disponible</span></h4>
                    <p>El sistema de renta de lockers aún no ha sido abierto por el administrador.</p>
                </div>
                
                <!-- Mostrar información de apertura si está disponible -->
                <?php if ($horaApertura && $fechaApertura): ?>
                    <div class="horario-info">
                        <p><strong>Horario de apertura:</strong></p>
                        <p>📅 Fecha: <?php echo htmlspecialchars($fechaApertura); ?></p>
                        <p>🕐 Hora: <?php echo htmlspecialchars($horaApertura); ?></p>
                    </div>
                <?php else: ?>
                    <div class="horario-info">
                        <p><strong>No hay información de horario disponible.</strong></p>
                        <p>Ponte en contacto con el administrador para más detalles.</p>
                    </div>
                <?php endif; ?>
                
                <div class="button-group">
                    <a href="cerrar_sesion.php" class="btn btn-secondary">Cerrar Sesión</a>
                </div>

            <?php else: ?>
                <!-- Sistema Abierto - Opción de unirse a la cola -->
                <div class="alert alert-success">
                    <h4><span class="status-open">● Sistema disponible</span></h4>
                    <p>Puedes unirte a la cola de renta de lockers.</p>
                </div>

                <div id="contenedor-estado" style="text-align: center; padding: 20px;">
                    <p style="font-size: 16px; margin-bottom: 20px;">¿Deseas unirte a la cola de renta de lockers?</p>
                    <div class="button-group">
                        <button class="btn btn-success btn-lg" onclick="unirseAlaCola()">Unirse a la Cola</button>
                        <a href="cerrar_sesion.php" class="btn btn-secondary">Cerrar Sesión</a>
                    </div>
                </div>

                <!-- Contenedor para mostrar el estado mientras está en la cola -->
                <div id="cola-estado" style="display: none; text-align: center; padding: 20px;">
                    <h3>Tu turno es: <span id="turno" style="font-size: 2em; font-weight: bold; color: #0066cc;">...</span></h3>
                    <p>Personas delante de ti:</p>
                    <div id="numero_delante" style="font-size: 2em; font-weight: bold; color: #333;">...</div>
                    <p style="font-size: 0.8em; color: #666; margin-top: 20px;">La página se actualiza automáticamente. No la cierres.</p>
                    <div style="margin-top: 30px;">
                        <button class="btn btn-sm btn-warning" onclick="salirCola()">Salir de la cola</button>
                        <a href="cerrar_sesion.php" class="btn btn-sm btn-danger">Cerrar Sesión</a>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <?php include($www_footer); ?>
    </div>

    <!-- Script para manejar la cola -->
    <?php if (!$sistemaCerrado): ?>
    <script>
        const alumnoId = "<?php echo htmlspecialchars($clvuni); ?>";
        let intervalo = null;

        function unirseAlaCola() {
            $('#contenedor-estado').hide();
            $('#cola-estado').show();

            $.post('redis/cola/unirse_cola.php')
                .done(function(data) {
                    if (data.status === 'sistema_cerrado') {
                        $('#cola-estado').html(`
                            <div class="alert alert-warning">
                                <h4>Sistema no disponible</h4>
                                <p>${data.message}</p>
                            </div>
                            <a href="dashboard.php" class="btn btn-secondary">Volver</a>
                        `);
                        return;
                    }
                    if (data.status === 'error') {
                        $('#cola-estado').html(`
                            <p style="color:red;">Error al unirse a la cola: ${data.message}</p>
                            <a href="dashboard.php" class="btn btn-danger">Volver</a>
                        `);
                        return;
                    }
                    revisarTurno();
                    intervalo = setInterval(() => {
                        if (document.querySelector('#atendido')) {
                            clearInterval(intervalo);
                        } else {
                            revisarTurno();
                        }
                    }, 3000);
                })
                .fail(function() {
                    console.error('No se pudo contactar a unirse_cola.php');
                    $('#cola-estado').html(`
                        <p style="color:red;">Error de conexión. Intenta de nuevo.</p>
                        <a href="dashboard.php" class="btn btn-danger">Volver</a>
                    `);
                });
        }

        function revisarTurno() {
            $.getJSON('redis/cola/consultar_estado.php')
                .done(function(data) {
                    if (data.status === 'sistema_cerrado') {
                        clearInterval(intervalo);
                        $('#cola-estado').html(`
                            <div class="alert alert-warning">
                                <h4>Sistema cerrado</h4>
                                <p>${data.message}</p>
                                <p style="font-size:0.85em; color:#666;">Has sido removido de la cola automáticamente.</p>
                            </div>
                            <a href="dashboard.php" class="btn btn-secondary">Volver</a>
                        `);
                    }
                    else if (data.status === 'atendido') {
                        // Redirigir a la pantalla de selección de lockers
                        window.location.href = 'seleccionar_locker.php';
                    }
                    else if (data.status === 'esperando') {
                        $('#numero_delante').text(data.posicion);
                        $('#turno').text(data.turno);
                    }
                })
                .fail(function() {
                    console.error('Error al conectar con el servidor');
                });
        }

        function salirCola() {
            if (confirm('¿Estás seguro de que deseas salir de la cola?')) {
                $.post('redis/cola/salir_cola.php')
                    .done(function(data) {
                        if (data.status === 'success') {
                            $('#cola-estado').html(`
                                <p style="color:green; font-weight:bold;">Has salido de la cola correctamente</p>
                                <a href="dashboard.php" class="btn btn-primary">Volver al panel</a>
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

</body>

<script type="text/javascript" src="js/plantilla.js?0001"></script>

</html>