<?php
/**
 * auto_abrir_cerrar.php - Script para apertura/cierre automático del sistema
 * Verifica horarios y realiza apertura/cierre automático
 */

header('Content-Type: application/json');
session_start();

require('../comun/conexion_redis.php');
require('../comun/procesos.php');
require_once('../comun/utils.php');

if (isset($error_redis) && $error_redis) {
    echo json_encode(['status' => 'error', 'message' => $error_redis]);
    exit;
}

try {
    require($_SERVER['DOCUMENT_ROOT'] . '/comun/conectar.php');
    
    // Obtener horarios de la configuración
    $horarios = obtenerHorariosConfig($dbh);
    
    if (!$horarios) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No hay configuración de horarios establecida'
        ]);
        mysqli_close($dbh);
        exit;
    }
    
    $estadoSistema = $redis->get('config:estado_sistema');
    
    // CASO 1: Verificar si debe estar ABIERTO
    if (estamosEnPeriodoDeApertura($horarios)) {
        
        // Si ya está abierto, solo verificar que cola_automatica esté corriendo
        if ($estadoSistema === 'abierto') {
            $resultadoInit = iniciarColaAutomaticaSiNoEsta();
            
            echo json_encode([
                'status' => 'abierto',
                'message' => 'Sistema ya estaba abierto, verificado estado de cola automática',
                'cola_status' => $resultadoInit
            ]);
            mysqli_close($dbh);
            exit;
        }
        
        // Si no está abierto, ABRIRLO AUTOMÁTICAMENTE
        // Código similar a abrir_sistema.php
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
        
        // Marcar sistema como abierto en Redis
        $redis->set('config:estado_sistema', 'abierto');
        $redis->set('config:fecha_apertura', date('Y-m-d H:i:s'));
        
        // Limpiar cola y contador por si quedaron residuos de otro día
        $redis->del($nombreCola);
        $redis->del('contador:turno');
        
        // Iniciar cola_automatica.php
        $resultadoInit = iniciarColaAutomaticaSiNoEsta();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Sistema abierto automáticamente',
            'ciclo' => $ciclo,
            'fecha_apertura' => date('Y-m-d H:i:s'),
            'cola_status' => $resultadoInit
        ]);
        
        mysqli_close($dbh);
        exit;
    }
    
    // CASO 2: Verificar si debe estar CERRADO
    if (yaDebioCerrar($horarios)) {
        
        if ($estadoSistema === 'abierto') {
            // CERRAR EL SISTEMA AUTOMÁTICAMENTE
            
            // Marcar como cerrado
            $redis->set('config:estado_sistema', 'cerrado');
            $redis->set('config:fecha_cierre', date('Y-m-d H:i:s'));
            
            // Limpiar cola y contador
            $redis->del($nombreCola);
            $redis->del('contador:turno');
            
            // Detener cola_automatica
            $resultadoDetener = detenerColaAutomatica();
            
            // Limpiar archivos temporales
            if (is_dir(LOCKER_TEMP)) {
                $archivos = scandir(LOCKER_TEMP);
                foreach ($archivos as $archivo) {
                    if ($archivo === '.' || $archivo === '..') {
                        continue;
                    }
                    $rutaArchivo = LOCKER_TEMP . '/' . $archivo;
                    if (is_file($rutaArchivo)) {
                        @unlink($rutaArchivo);
                    }
                }
            }
            
            echo json_encode([
                'status' => 'cerrado',
                'message' => 'Sistema cerrado automáticamente - ya pasó la hora de cierre',
                'fecha_cierre' => date('Y-m-d H:i:s'),
                'cola_status' => $resultadoDetener
            ]);
        } else {
            echo json_encode([
                'status' => 'cerrado',
                'message' => 'Sistema ya estaba cerrado'
            ]);
        }
        
        mysqli_close($dbh);
        exit;
    }
    
    // CASO 3: Fuera del horario
    echo json_encode([
        'status' => 'fuera_horario',
        'message' => 'El sistema aún no está disponible o ya cerró',
        'horarios' => [
            'apertura' => $horarios['fecha_ini'] . ' ' . $horarios['hora_ini'],
            'cierre' => $horarios['fecha_fin'] . ' ' . $horarios['hora_fin']
        ]
    ]);
    
    mysqli_close($dbh);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
