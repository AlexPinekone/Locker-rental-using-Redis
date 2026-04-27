<?php
session_start();
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['clvuni'])) 
{
    header('Location: login.php');
    exit;
}

$error_msg = false;
$sistemaCerrado = false;
$horaApertura = null;
$fechaApertura = null;
$estadoAlumno = 'inicio'; // 'inicio', 'cola', 'seleccionando', 'locker_reservado'
$infoLocker = null;
$turnoActual = null;
$posicionEnCola = null;

// Obtener información del sistema (si está abierto y horario)
require('redis/comun/conexion_redis.php');
$estadoSistema = $redis->get('config:estado_sistema');
$clvuni = isset($_SESSION['clvuni']) ? $_SESSION['clvuni'] : null;

// NUEVA LÓGICA: Verificación de auto-apertura/cierre
// Incluir archivo de utilidades de procesos para verificar horarios
require($_SERVER['DOCUMENT_ROOT'].'/panel_lockers/redis/comun/procesos.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/panel_lockers/redis/comun/utils.php');
require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

// Verificar si es hora de apertura y abrir automáticamente si es necesario
if ($estadoSistema !== 'abierto') {
    $horarios = obtenerHorariosConfig($dbh);
    
    if ($horarios && estamosEnPeriodoDeApertura($horarios)) {
        // Es hora de apertura, abrir automáticamente
        if (!isset($_SESSION['ciclo'])) {
            $_SESSION['ciclo'] = 'sin-ciclo-definido';
        }
        
        $ciclo = $_SESSION['ciclo'];
        $redis->set('config:ciclo', $ciclo);
        
        $fechaHoy = date('Y-m-d');
        
        // Crear carpetas si no existen
        if (!is_dir(LOCKER_TEMP)) {
            mkdir(LOCKER_TEMP, 0755, true);
        }
        
        // Definición de rutas de los archivos
        $archivos = [
            getNombreArchivoFila($redis, $fechaHoy),
            LOCKER_TEMP . "/temporal_{$fechaHoy}.jsonl",
            LOCKER_TEMP . "/salidas_{$fechaHoy}.json",
        ];
        
        // Buscar si ya están los archivos, si no crea un json[] o un jsonl vacío
        foreach ($archivos as $ruta) {
            if (!file_exists($ruta)) {
                $esJson = str_ends_with($ruta, '.json');
                file_put_contents($ruta, $esJson ? '[]' : '');
            }
        }
        
        // Marcar sistema como abierto
        $redis->set('config:estado_sistema', 'abierto');
        $redis->set('config:fecha_apertura', date('Y-m-d H:i:s'));
        
        // Limpiar cola
        $redis->del($nombreCola);
        $redis->del('contador:turno');
        
        // Iniciar cola automática (solo si no está corriendo)
        iniciarColaAutomaticaSiNoEsta();
        
        // Actualizar la variable para reflejar que se abrió
        $estadoSistema = 'abierto';
    }
}

// Cerrar conexión después de verificación de horarios
if (isset($dbh)) {
    mysqli_close($dbh);
}

// Si el sistema no está abierto, mostrar mensaje de cerrado
if ($estadoSistema !== 'abierto') 
{
    $sistemaCerrado = true;
    
    // Obtener información de fecha y hora de apertura desde la base de datos
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');
    
    $query = "SELECT fecha_ini, hora_ini FROM loc_config LIMIT 1";
    $result = mysqli_query($dbh, $query);
    
    if ($result && mysqli_num_rows($result) > 0) 
    {
        $row = mysqli_fetch_assoc($result);
        $fechaApertura = $row['fecha_ini'];
        $horaApertura = $row['hora_ini'];
    }
    
    mysqli_close($dbh);
} 
else if ($clvuni) 
{
    // Sistema abierto - Verificar estado del alumno
    
    // Buscar si tiene locker reservado
    require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');
    
    $queryLocker = "SELECT lr.id, lr.id_l, ll.numero, ll.id_a, lr.fecha_r, lr.fecha_c 
                    FROM plantilla.loc_reserva lr
                    JOIN plantilla.loc_locker ll ON lr.id_l = ll.id
                    WHERE lr.clave_unica = ? AND lr.estado IN (1, 3) 
                    LIMIT 1";
    
    $stmtLocker = mysqli_prepare($dbh, $queryLocker);
    mysqli_stmt_bind_param($stmtLocker, "s", $clvuni);
    mysqli_stmt_execute($stmtLocker);
    $resultLocker = mysqli_stmt_get_result($stmtLocker);
    
    if ($resultLocker && mysqli_num_rows($resultLocker) > 0) 
    {
        $infoLocker = mysqli_fetch_assoc($resultLocker);
        $estadoAlumno = 'locker_reservado';
    }
    
    mysqli_stmt_close($stmtLocker);
    mysqli_close($dbh);
    
    // Si no tiene locker, buscar si está en la cola
    if ($estadoAlumno === 'inicio' && isset($redis) && (!isset($error_redis) || !$error_redis)) 
    {
        require_once($_SERVER['DOCUMENT_ROOT'].'/panel_lockers/redis/comun/utils.php');

        // Usar el nombre de cola global definido en conexion_redis.php
        global $nombreCola;

        $lista = $redis->lRange($nombreCola, 0, -1);

        $usuarioEnCola = false;
        foreach ($lista as $idx => $item) {
            $itemDecodificado = json_decode($item, true);
            if (isset($itemDecodificado['clvuni']) && $itemDecodificado['clvuni'] === $clvuni) 
            {
                $usuarioEnCola = true;
                $turnoActual = $itemDecodificado['turno']; 
                $posicionEnCola = $idx;

                // Verificar si está siendo atendido
                // CAMBIO
                if ($posicionEnCola === 0) 
                {
                    $estadoAlumno = 'seleccionando';
                } 
                else 
                {
                    $estadoAlumno = 'cola';
                }
                break;
            }
        }
    }
}

// Recuperar datos de la sesión
$nombreCompleto = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'Usuario';
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
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral" style="font-family: 'Segoe UI', sans-serif;">
        <div style="max-width: 600px; margin: 40px auto; padding: 30px; background-color: #f9f9f9; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            
            <!-- Información del Usuario -->
            <div style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e0e0e0;">
                <div style="font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($nombreCompleto); ?>
                </div>
                <div style="font-size: 12px; color: #999; margin-bottom: 15px;">
                    Clave Única: <?php echo htmlspecialchars($clvuni); ?>
                </div>
            </div>

            <!-- Verificar Estado del Sistema -->
            <?php if ($sistemaCerrado): ?>
                <!-- Sistema Cerrado -->
                <div class="alert alert-danger" style="margin: 20px 0;">
                    <h4>
                        <span style="color: #dc3545; font-weight: bold;">● Sistema no disponible</span>
                    </h4>
                    <p>El sistema de renta de lockers aún no ha sido abierto por el administrador.</p>
                </div>
                
                <!-- Mostrar información de apertura si está disponible -->
                <?php if ($horaApertura && $fechaApertura): ?>
                    <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin: 15px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">
                            <strong style="color: #856404;">Horario de apertura:</strong>
                        </p>
                        <p>Fecha: <?php echo htmlspecialchars($fechaApertura); ?></p>
                        <p>Hora: <?php echo htmlspecialchars($horaApertura); ?></p>
                    </div>
                <?php else: ?>
                    <div class="horario-info">
                        <p><strong>No hay información de horario disponible.</strong></p>
                        <p>Ponte en contacto con el administrador para más detalles.</p>
                    </div>
                <?php endif; ?>
                <<!-- QUITAR BOTÓN EN EL FUTURO, no hay que dejar que solo cierren sesion -->
                <div style="margin-top: 30px; text-align: center;">
                    <a href="cerrar_sesion.php" class="btn btn-secondary" style="margin: 5px;">Cerrar Sesión</a>
                </div>

            <?php elseif ($estadoAlumno === 'locker_reservado'): ?>
                <!-- Locker ya reservado - Mostrar información y opción de cancelar -->
                <div class="alert alert-info" style="margin: 20px 0;">
                    <h4>
                        <span style="color: #0c5460; font-weight: bold;">✓ Locker Reservado</span>
                    </h4>
                    <p>Ya tienes un locker asignado en este ciclo.</p>
                </div>

                <div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 4px; margin: 15px 0; text-align: center;">
                    <p style="font-size: 14px; color: #155724; margin-bottom: 10px;">
                        <strong>Información del Locker</strong>
                    </p>
                    <div style="font-size: 28px; font-weight: bold; color: #155724; margin: 15px 0;">
                        Locker #<?php echo htmlspecialchars($infoLocker['numero']); ?> - Edificio <?php echo htmlspecialchars($infoLocker['id_a']); ?>
                    </div>
                    <p style="font-size: 12px; color: #155724; margin: 5px 0;">
                        Fecha de reserva: <?php echo htmlspecialchars($infoLocker['fecha_r']); ?>
                    </p>
                </div>

                <div style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-danger" onclick="cancelarReservaConfirm()">Cancelar Reserva</button>
                    <a href="cerrar_sesion.php" class="btn btn-secondary" style="margin-left: 10px;">Cerrar Sesión</a>
                </div>

            <?php elseif ($estadoAlumno === 'seleccionando'): ?>
                <!-- Usuario siendo atendido - Redireccionamiento automático a seleccionar locker -->
                <div class="alert alert-success">
                    <h4>
                        <span style="color: #28a745; font-weight: bold;">¡Es tu turno!</span>
                    </h4>
                    <p>Redireccionando a la pantalla de selección de lockers...</p>
                </div>
                <div style="text-align: center;">
                    <p style="font-size: 14px; color: #666;">Por favor espera...</p>
                </div>

            <?php elseif ($estadoAlumno === 'cola'): ?>
                <!-- Usuario en cola - Mostrar posición -->
                <div class="alert alert-success">
                    <h4>
                        <span style="color: #28a745; font-weight: bold;">● En la fila</span>
                    </h4>
                    <p>Estás en la fila virtual de renta de lockers.</p>
                </div>

                <div id="cola-estado" style="text-align: center; padding: 20px;">
                    <h3>Tu turno es: <span id="turno" style="font-size: 2em; font-weight: bold; color: #0066cc;">...</span></h3>
                    <p>Personas delante de ti:</p>
                    <div id="numero_delante" style="font-size: 2em; font-weight: bold; color: #333;">...</div>
                    <p style="font-size: 0.8em; color: #666; margin-top: 20px;">La página se actualiza automáticamente. No la cierres.</p>
                    <div style="margin-top: 30px;">
                        <button class="btn btn-sm btn-warning" onclick="salirCola()">Salir de la cola</button>
                        <a href="cerrar_sesion.php" class="btn btn-sm btn-danger">Cerrar Sesión</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Estado inicial - Opción de unirse a la cola -->
                <div class="alert alert-success">
                    <h4>
                        <span style="color: #28a745; font-weight: bold;">Sistema disponible</span>
                    </h4>
                    <p>Puedes unirte a la fila virtual de renta de lockers.</p>
                </div>

                <div id="contenedor-estado" style="text-align: center; padding: 20px;">
                    <p style="font-size: 16px; margin-bottom: 20px;">¿Deseas unirte a la fila de renta de lockers?</p>
                    <div class="button-group">
                        <button class="btn btn-success btn-lg" onclick="unirseAlaCola()">Unirse a la Fila</button>
                        <a href="cerrar_sesion.php" class="btn btn-secondary">Cerrar Sesión</a>
                    </div>
                </div>

                <div id="cola-estado" style="display:none; text-align: center; padding: 20px;">
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
    <script>
        const alumnoId = "<?php echo htmlspecialchars($clvuni); ?>";
        let estadoAlumno = "<?php echo htmlspecialchars($estadoAlumno); ?>";
        const sistemaCerrado = <?php echo ($sistemaCerrado ? 'true' : 'false'); ?>;
        let intervalo = null;

        // Si está siendo atendido, redirigir automáticamente
        if (estadoAlumno === 'seleccionando') 
        {
            setTimeout(() => {
                window.location.href = 'seleccionar_locker.php';
            }, 2000);
        }

        // Si está en cola, iniciar el polling automático
        if (estadoAlumno === 'cola') 
        {
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
        }

        function unirseAlaCola() 
        {
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
                    estadoAlumno = 'cola';
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

        function cancelarReservaConfirm() {
            if (confirm('¿Estás seguro de que deseas cancelar tu reserva? Esta acción no se puede deshacer.')) {
                $.post('ajax/cancelar_reserva.php')
                    .done(function(data) {
                        if (data.status === 'success') {
                            alert('Tu reserva ha sido cancelada correctamente.');
                            window.location.href = 'dashboard.php';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .fail(function() {
                        alert('Error al conectar con el servidor');
                    });
            }
        }

        function mostrarCola(turno, personasDelante) {
            $('#contenedor-estado').hide();
            $('#cola-estado').show();
            $('#turno').text(turno || '...');
            $('#numero_delante').text(typeof personasDelante !== 'undefined' ? personasDelante : '...');
        }

        function ocultarCola() {
            $('#cola-estado').hide();
            $('#contenedor-estado').show();
        }

        function revisarTurno() {
        $.getJSON('redis/cola/consultar_estado.php')
            .done(function(data) {
                // Verificar congelacion de la cola
                if (data.status === 'cola_congelada') {
                    if (!colaCongeladaMostrada) {
                        mostrarAlertaCongelacion(data);
                        colaCongeladaMostrada = true;
                    }
                    return; // No continuar procesando
                }
                
                // Si se descongela, mostrar notificación
                if (colaCongeladaMostrada && data.status !== 'cola_congelada') {
                    ocultarAlertaCongelacion();
                    mostrarNotificacionDescongelada();
                    colaCongeladaMostrada = false;
                }
                
                // Resto de lógica original
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
                    ocultarCola();
                }
                else if (data.status === 'seleccionando') {
                    window.location.href = 'seleccionar_locker.php';
                }
                else if (data.status === 'esperando') {
                    mostrarCola(data.turno, data.personas_delante);
                }
            })
            .fail(function() {
                console.error('Error al conectar con el servidor');
            });
    }

        function verificarEstadoInicial() 
        {
            if (sistemaCerrado || estadoAlumno === 'locker_reservado') 
            {
                return;
            }

            $.getJSON('redis/cola/consultar_estado.php')
                .done(function(data) {
                    if (data.status === 'esperando') 
                    {
                        mostrarCola(data.turno, data.personas_delante);
                        if (!intervalo) 
                        {
                            intervalo = setInterval(() => revisarTurno(), 3000);
                        }
                    } 
                    else if (data.status === 'seleccionando') 
                    {
                        window.location.href = 'seleccionar_locker.php';
                    } 
                    else if (data.status === 'atendido') 
                    {
                        ocultarCola();
                    }
                })
                .fail(function() {
                    console.error('Error al verificar estado inicial');
                });
        }

        $(document).ready(function() {
            verificarEstadoInicial();
        });

        let colaCongeladaMostrada = false;

        // Mostrar alerta cuando está congelada
        function mostrarAlertaCongelacion(data) {
            const mensaje = `
                <div id="alerta-congelacion" class="alert alert-warning" style="margin: 20px 0; border: 2px solid #ff9800;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 32px;">❄️</div>
                        <div>
                            <h4 style="margin: 0; color: #ff6b00;">
                                <strong>Cola Congelada</strong>
                            </h4>
                            <p style="margin: 5px 0; font-size: 14px;">
                                No hay lockers disponibles en este momento.
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #ff6b00; font-weight: bold;">
                                ⏳ Esperando disponibilidad de lockers...
                            </p>
                        </div>
                    </div>
                </div>
            `;
            
            if ($('#alerta-congelacion').length === 0) {
                $('#cola-estado').prepend(mensaje);
            }
        }

        // Ocultar alerta de congelación
        function ocultarAlertaCongelacion() {
            $('#alerta-congelacion').fadeOut(300, function() {
                $(this).remove();
            });
        }

        // Notificación cuando se descongela
        function mostrarNotificacionDescongelada() {
            const notificacion = $(`
                <div class="alert alert-success" style="margin: 20px 0; border: 2px solid #28a745;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 32px;">✅</div>
                        <div>
                            <h4 style="margin: 0; color: #28a745;">
                                <strong>¡Cola Activa!</strong>
                            </h4>
                            <p style="margin: 5px 0;">
                                Los lockers vuelven a estar disponibles.
                            </p>
                        </div>
                    </div>
                </div>
            `);
            
            $('#cola-estado').prepend(notificacion);
            
            setTimeout(function() {
                notificacion.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    </script>
</body>

<script type="text/javascript" src="js/plantilla.js?0001"></script>

</html>