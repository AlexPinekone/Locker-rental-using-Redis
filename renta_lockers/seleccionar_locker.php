<?php
session_start();
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

if (!isset($_SESSION['clvuni'])) {
    header('Location: login.php');
    exit;
}

require($_SERVER['DOCUMENT_ROOT'].'/comun/conectar.php');

$clvuni = $_SESSION['clvuni'];
$nombres = isset($_SESSION['nombres']) ? $_SESSION['nombres'] : '';
$ape_pat = isset($_SESSION['ape_pat']) ? $_SESSION['ape_pat'] : '';
$ape_mat = isset($_SESSION['ape_mat']) ? $_SESSION['ape_mat'] : '';
$nombreCompleto = trim($nombres . ' ' . $ape_pat . ' ' . $ape_mat);

$query = "SELECT id, id_a, numero, ren, col, activo FROM plantilla.loc_locker ORDER BY id_a, ren, col";
$result = mysqli_query($dbh, $query);

$lockesPorEdificio = [];
while ($row = mysqli_fetch_assoc($result)) {
    $edificio = $row['id_a'];
    if (!isset($lockesPorEdificio[$edificio])) {
        $lockesPorEdificio[$edificio] = [];
    }
    $lockesPorEdificio[$edificio][] = $row;
}

$lockersReservados = [];

// Obtener lockers reservados con estado 1, 2 o 3
$queryReservados = "SELECT id_l FROM plantilla.loc_reserva WHERE estado IN (1, 2, 3)";
$resultReservados = mysqli_query($dbh, $queryReservados);

if ($resultReservados && mysqli_num_rows($resultReservados) > 0) {
    while ($row = mysqli_fetch_assoc($resultReservados)) {
        $lockersReservados[] = intval($row['id_l']);
    }
}

mysqli_close($dbh);

// Obtener ciclo desde Redis (guardado en abrir_sistema.php)
require('redis/comun/conexion_redis.php');

$cicloActual = (isset($redis) && (!isset($error_redis) || !$error_redis)) 
    ? $redis->get('config:ciclo') 
    : date('Y');
?>

<!DOCTYPE HTML>
<html>
<head>
    <meta charset="iso-8859-1" />
    <title>FCQ :: Seleccionar Locker</title>
    
    <script src="<?php echo $adds; ?>/jquery-3.4.1.min.js"></script>
    <link rel="stylesheet" href="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?php echo $adds; ?>/pagina.css?0001" />
    <script src="<?php echo $adds; ?>/bootstrap-<?php echo $verBSDist; ?>-dist/js/bootstrap.min.js"></script>
    <script src="js/seleccionar_locker.js?0003"></script>
    
    <style>
        body { font-family: Arial, sans-serif; }
        .container-seleccion { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .header-seleccion { text-align: center; margin-bottom: 20px; }
        .header-seleccion h2 { margin: 0; }
        .header-seleccion p { margin: 5px 0 0 0; font-size: 12px; }
        
        .debug-box { 
            background: #f0f0f0; 
            padding: 10px; 
            margin: 10px 0; 
            border-left: 4px solid #007bff;
            font-size: 12px;
            font-family: monospace;
        }
        
        .timer-container { text-align: center; margin: 20px 0; padding: 10px; background: #fffacd; }
        .timer { font-size: 48px; font-weight: bold; font-family: monospace; }
        .timer.warning { color: red; }
        .timer-text { font-size: 12px; }
        
        .edificio-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .edificio-titulo { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        
        .mapa-lockers { text-align: center; }
        
        .locker-btn { 
            width: 50px; height: 50px; margin: 2px; padding: 0; 
            border: 1px solid #999; background: #fff; cursor: pointer; font-size: 10px;
        }
        .locker-btn:hover:not(:disabled):not(.reservado):not(.seleccionado) { background: #e8f4f8; }
        .locker-btn.reservado { background: #ddd; color: #999; cursor: not-allowed; }
        .locker-btn.inactivo { background: #f5f5f5; cursor: not-allowed; }
        .locker-btn.seleccionado { background: #28a745; color: #fff; }
        
        .locker-numero { display: block; font-weight: bold; }
        .locker-pos { display: block; font-size: 8px; }
        
        .controles { text-align: center; margin-top: 20px; }
        .btn-grupo { margin: 0 5px; }
        
        .confirmation-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .confirmation-modal.active { display: flex; align-items: center; justify-content: center; }
        
        .confirmation-content { background: #fff; padding: 20px; max-width: 350px; text-align: center; }
        .confirmation-locker-info { padding: 10px; margin: 10px 0; background: #f0f0f0; font-weight: bold; }
        .confirmation-buttons { margin-top: 15px; }
        .confirmation-buttons button { margin: 0 5px; padding: 8px 15px; cursor: pointer; }
        
        .info-legend { margin: 20px 0; padding: 10px; font-size: 11px; }
        .legend-item { display: inline-block; margin-right: 15px; }
        .legend-color { display: inline-block; width: 15px; height: 15px; border: 1px solid #ccc; vertical-align: middle; margin-right: 3px; }
    </style>
</head>

<body>
    <?php include($www_header); ?>

    <div class="container_gral">
        <div class="container-seleccion">
            
            <div class="header-seleccion">
                <h2>Selecciona tu Locker</h2>
                <p>Hola, <?php echo htmlspecialchars($nombreCompleto); ?></p>
            </div>

        
            <div class="timer-container">
                <div class="timer" id="timerDisplay">02:00</div>
                <div class="timer-text">Tiempo disponible para seleccionar</div>
            </div>

            <?php foreach ($lockesPorEdificio as $edificio => $lockers): ?>
                <div class="edificio-section">
                    <div class="edificio-titulo">Edificio: <?php echo htmlspecialchars($edificio); ?></div>
                    <div class="mapa-lockers" id="mapa-<?php echo htmlspecialchars($edificio); ?>"></div>
                </div>
            <?php endforeach; ?>

            <div class="info-legend">
                <div class="legend-item"><span class="legend-color" style="background: #fff;"></span>Disponible</div>
                <div class="legend-item"><span class="legend-color" style="background: #ddd;"></span>Reservado</div>
                <div class="legend-item"><span class="legend-color" style="background: #28a745;"></span>Seleccionado</div>
                <div class="legend-item"><span class="legend-color" style="background: #f5f5f5;"></span>Inactivo</div>
            </div>

            <div class="controles">
                <div class="btn-grupo">
                    <button id="btnConfirmarSeleccion" class="btn btn-success" disabled>Confirmar</button>
                </div>
                <div class="btn-grupo">
                    <button id="btnCancelar" class="btn btn-secondary">Cancelar</button>
                </div>
            </div>
        </div>

        <div class="confirmation-modal" id="confirmationModal">
            <div class="confirmation-content">
                <h3>Confirmar Locker</h3>
                <p>¿Deseas reservar este locker?</p>
                <div class="confirmation-locker-info" id="lockersSeleccionadoInfo">Locker #XXX</div>
                <div class="confirmation-buttons">
                    <button class="btn btn-success btn-sm" id="btnConfirmarFinal">Confirmar</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelarModal">Cancelar</button>
                </div>
            </div>
        </div>

        <?php include($www_footer); ?>
    </div>

    <script>
    const edificiosData = <?php echo json_encode($lockesPorEdificio); ?>;
    document.addEventListener('DOMContentLoaded', function() {
        inicializarSeleccionador(
            "<?php echo htmlspecialchars($clvuni); ?>",
            "<?php echo htmlspecialchars($cicloActual); ?>",
            <?php echo json_encode($lockersReservados); ?>,
            edificiosData
        );
    });
</script>

</body>

<script src="js/plantilla.js?0002"></script>

</html>