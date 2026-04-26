<?php
/**
 * procesos.php - Utilidades para manejo de procesos
 * Maneja la instancia única de cola_automatica.php y verificaciones de horario
 */

/**
 * Obtener la ruta del archivo PID para cola_automatica.php
 * @return string - Ruta del archivo PID
 */
function obtenerRutaPIDColaaAutomatica()
{
    $tmpDir = sys_get_temp_dir();
    return $tmpDir . DIRECTORY_SEPARATOR . 'cola_automatica.pid';
}

/**
 * Verificar si cola_automatica.php ya está corriendo
 * @return bool - True si está corriendo, False si no
 */
function estaColaAutomaticaEnEjecucion()
{
    $rutaPID = obtenerRutaPIDColaaAutomatica();
    
    if (!file_exists($rutaPID)) {
        return false;
    }
    
    $pid = file_get_contents($rutaPID);
    $pid = trim($pid);
    
    if (empty($pid) || !is_numeric($pid)) {
        return false;
    }
    
    // Verificar si el proceso con ese PID existe
    return shell_exec("ps -p " . (int)$pid . " 2>/dev/null | wc -l") > 1;
}

/**
 * Iniciar cola_automatica.php si no está en ejecución
 * Asegura que solo haya una instancia
 * 
 * @return array - Array con status, message, y pid
 */
function iniciarColaAutomaticaSiNoEsta()
{
    // Si hay un proceso ejecutándose, verificar si está funcionando
    if (estaColaAutomaticaEnEjecucion()) {
        // Verificar si el proceso está realmente saludable (log sin errores recientes)
        $logPath = '/tmp/cola_automatica.log';
        if (file_exists($logPath)) {
            $logContent = file_get_contents($logPath);
            // Si hay errores de "Failed to open stream" en las últimas líneas, el proceso está fallando
            if (strpos($logContent, 'Failed to open stream') !== false && 
                strpos($logContent, 'conectar.php') !== false) {
                error_log("Proceso de cola_automatica fallando, deteniendo y reiniciando...");
                detenerColaAutomatica();
                sleep(2); // Esperar a que se detenga
            } else {
                return [
                    'status' => 'ya_ejecutandose',
                    'message' => 'cola_automatica.php ya está en ejecución',
                    'pid' => file_get_contents(obtenerRutaPIDColaaAutomatica())
                ];
            }
        } else {
            return [
                'status' => 'ya_ejecutandose',
                'message' => 'cola_automatica.php ya está en ejecución',
                'pid' => file_get_contents(obtenerRutaPIDColaaAutomatica())
            ];
        }
    }
    
    try {
        // Ejecutar en background y obtener el PID
        $comando = 'cd /var/www/html/panel_lockers/redis/sistema && nohup php cola_automatica.php > /tmp/cola_automatica.log 2>&1 & echo $!';
        $pid = exec($comando);
        $pid = trim($pid);
        
        if (empty($pid) || !is_numeric($pid)) {
            throw new Exception('No se pudo obtener el PID del nuevo proceso');
        }
        
        // Guardar el PID
        $rutaPID = obtenerRutaPIDColaaAutomatica();
        file_put_contents($rutaPID, $pid);
        
        return [
            'status' => 'iniciado',
            'message' => 'cola_automatica.php iniciado correctamente',
            'pid' => $pid
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'pid' => null
        ];
    }
}

/**
 * Detener cola_automatica.php
 * @return array - Array con status y message
 */
function detenerColaAutomatica()
{
    $rutaPID = obtenerRutaPIDColaaAutomatica();
    
    if (!file_exists($rutaPID)) {
        return [
            'status' => 'no_ejecutandose',
            'message' => 'No hay proceso de cola_automatica en ejecución'
        ];
    }
    
    $pid = file_get_contents($rutaPID);
    $pid = trim($pid);
    
    if (!empty($pid) && is_numeric($pid)) {
        shell_exec("kill " . (int)$pid . " 2>/dev/null");
        sleep(1); // Darle tiempo para terminar
    }
    
    // Eliminar el archivo PID
    @unlink($rutaPID);
    
    return [
        'status' => 'detenido',
        'message' => 'cola_automatica.php ha sido detenido'
    ];
}

/**
 * Obtener información de horarios de la base de datos
 * @param mysqli $dbh - Conexión a base de datos
 * @return array - Array con fecha_ini, hora_ini, fecha_fin, hora_fin o falso si no existe
 */
function obtenerHorariosConfig($dbh)
{
    $query = "SELECT fecha_ini, hora_ini, fecha_fin, hora_fin FROM plantilla.loc_config LIMIT 1";
    $result = mysqli_query($dbh, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return false;
    }
    
    return mysqli_fetch_assoc($result);
}

/**
 * Verificar si estamos dentro del período de apertura
 * @param Array $horarios - Array con fecha_ini, hora_ini, fecha_fin, hora_fin
 * @return bool - True si estamos dentro del período, False si no
 */
function estamosEnPeriodoDeApertura($horarios)
{
    if (!$horarios) {
        return false;
    }
    
    $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    $fechaHoy = $ahora->format('Y-m-d');
    $horaAhora = $ahora->format('H:i:s');
    
    // Crear timestamps para comparación
    $inicioTimestamp = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $horarios['fecha_ini'] . ' ' . $horarios['hora_ini'],
        new DateTimeZone('America/Mexico_City')
    )->getTimestamp();
    
    $finTimestamp = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $horarios['fecha_fin'] . ' ' . $horarios['hora_fin'],
        new DateTimeZone('America/Mexico_City')
    )->getTimestamp();
    
    $ahoraTimestamp = $ahora->getTimestamp();
    
    return ($ahoraTimestamp >= $inicioTimestamp && $ahoraTimestamp < $finTimestamp);
}

/**
 * Verificar si ya pasó la hora de cierre
 * @param Array $horarios - Array con fecha_ini, hora_ini, fecha_fin, hora_fin
 * @return bool - True si ya pasó la hora de cierre, False si no
 */
function yaDebioCerrar($horarios)
{
    if (!$horarios) {
        return false;
    }
    
    $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    
    $finTimestamp = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $horarios['fecha_fin'] . ' ' . $horarios['hora_fin'],
        new DateTimeZone('America/Mexico_City')
    )->getTimestamp();
    
    $ahoraTimestamp = $ahora->getTimestamp();
    
    return ($ahoraTimestamp >= $finTimestamp);
}

/**
 * Verificar si ya es hora de abrir
 * @param Array $horarios - Array con fecha_ini, hora_ini, fecha_fin, hora_fin
 * @return bool - True si ya es la hora de apertura, False si no
 */
function yaEsHoraDeAbrir($horarios)
{
    if (!$horarios) {
        return false;
    }
    
    $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    
    $inicioTimestamp = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        $horarios['fecha_ini'] . ' ' . $horarios['hora_ini'],
        new DateTimeZone('America/Mexico_City')
    )->getTimestamp();
    
    $ahoraTimestamp = $ahora->getTimestamp();
    
    return ($ahoraTimestamp >= $inicioTimestamp);
}
?>
