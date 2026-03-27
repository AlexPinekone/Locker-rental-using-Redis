<?php

//Rutas de los archivos si por algun motivo no son definidas en conexion_redis.php
if (!defined('PROYECTO_ROOT')) 
{
    define('PROYECTO_ROOT', realpath($_SERVER['DOCUMENT_ROOT'] . '/sistema_lockers'));
    define('LOCKER_LOGS',   realpath($_SERVER['DOCUMENT_ROOT'] . '/locker_logs'));
    define('LOCKER_TEMP',   realpath($_SERVER['DOCUMENT_ROOT'] . '/locker_logs/temp'));
}

/**
 * Construir el nombre del archivo principal de registros
 * Lee el ciclo desde Redis para que cualquier módulo pueda usarlo
 *
 * @param object $redis - Conexión a Redis
 * @param string $fechaHoy - Fecha en formato Y-m-d (opcional, default hoy)
 * @return string - Ruta completa del archivo
 */
function getNombreArchivoFila($redis, $fechaHoy = null)
{
    if ($fechaHoy === null) {
        $fechaHoy = date('Y-m-d');
    }

    $ciclo = $redis->get('config:ciclo') ?: 'sin-ciclo';

    // Sanitizar ciclo para nombre de archivo seguro
    $cicloSeguro = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $ciclo);

    return LOCKER_LOGS . "/fila_{$cicloSeguro}_{$fechaHoy}.json";
}

/**
 * Comparar y agregar registros de temporal a fila
 * 
 * @param string $nombreArchivoTemporal - Ruta del archivo temporal_{fecha}.jsonl
 * @param string $nombreArchivoFila - Ruta del archivo fila_{fecha}.json
 * @return array - Array con los datos de fila actualizados
 * @throws Exception
 */
function compararYAgregarRegistrosDeTemporal($nombreArchivoTemporal, $nombreArchivoFila) 
{
    // Leer temporal_{fecha}.jsonl temporal
    $registrosTemporal = [];
    if (file_exists($nombreArchivoTemporal)) 
    {
        $lineas = file($nombreArchivoTemporal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) 
        {
            $registro = json_decode($linea, true);
            if ($registro) 
            {
                $registrosTemporal[] = $registro;
            }
        }
    }
    
    // Leer temporal_{fecha}.json
    $datosFila = [];
    if (file_exists($nombreArchivoFila)) 
    {
        $contenido = file_get_contents($nombreArchivoFila);
        $datosFila = json_decode($contenido, true) ?: [];
    }
    
    // Crear identificador único para comparación: clvuni + turno
    $registrosFilaUnicos = [];
    foreach ($datosFila as $registro) 
    {
        if (isset($registro['clvuni']) && isset($registro['turno'])) 
        {
            $identificadorUnico = $registro['clvuni'] . '_' . $registro['turno'];
            $registrosFilaUnicos[$identificadorUnico] = true;
        }
    }
    
    // Agregar registros de temporal que NO estén en fila
    foreach ($registrosTemporal as $registro) 
    {
        if (isset($registro['clvuni']) && isset($registro['turno'])) 
        {
            $identificadorUnico = $registro['clvuni'] . '_' . $registro['turno'];
            
            // Si la combinación clvuni+turno no existe, agregar
            if (!isset($registrosFilaUnicos[$identificadorUnico])) 
            {
                $datosFila[] = $registro;
                $registrosFilaUnicos[$identificadorUnico] = true;
            }
        }
    }
    
    // Guardar los datos actualizados
    guardarEnArchivo($nombreArchivoFila, $datosFila);
    
    return $datosFila;
}

/**
 * Procesar salidas: tomar registros de salidas y modificarlos en fila
 * 
 * @param string $nombreArchivoFila - Ruta del archivo fila_{fecha}.json
 * @param string $nombreArchivoSalidas - Ruta del archivo salidas_{fecha}.json
 * @return bool - True si se procesó correctamente
 * @throws Exception
 */
function procesarSalidasEnFila ($nombreArchivoFila, $nombreArchivoSalidas) 
{
    // Leer el archivo de salidas
    $datosSalidas = [];
    if (file_exists($nombreArchivoSalidas)) 
    {
        $contenido = file_get_contents($nombreArchivoSalidas);
        $datosSalidas = json_decode($contenido, true) ?: [];
    }
    
    // Si no hay registros de salidas, retornar
    if (empty($datosSalidas)) 
    {
        return true;
    }
    
    // Leer fila_{fecha}.json
    $datosFila = [];
    if (file_exists($nombreArchivoFila)) 
    {
        $contenido = file_get_contents($nombreArchivoFila);
        $datosFila = json_decode($contenido, true) ?: [];
    }
    
    // Si no hay registros en fila, retornar
    if (empty($datosFila)) 
    {
        return true;
    }
    
    // Procesar cada registro de salidas
    foreach ($datosSalidas as $registroSalidas) 
    {
        if (isset($registroSalidas['clvuni'])) 
        {
            $clvuni = $registroSalidas['clvuni'];
            
            // Encontrar la ÚLTIMA instancia del clvuni en fila
            $ultimoIndice = null;
            for ($i = count($datosFila) - 1; $i >= 0; $i--) 
            {
                if (isset($datosFila[$i]['clvuni']) && $datosFila[$i]['clvuni'] === $clvuni) 
                {
                    $ultimoIndice = $i;
                    break;
                }
            }
            
            // Si se encontró el registro, actualizarlo
            if ($ultimoIndice !== null) 
            {
                // Mezclar datos de salidas con el registro de fila
                $datosFila[$ultimoIndice] = array_merge($datosFila[$ultimoIndice], $registroSalidas);
            }
        }
    }
    
    // Guardar el archivo fila actualizado en formato pretty
    guardarEnArchivo($nombreArchivoFila, $datosFila);
    
    // Limpiar el archivo de salidas después de procesar
    $archivoVacio = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($nombreArchivoSalidas, $archivoVacio);
    
    return true;
}

/**
 * Procesar ambas: agregar temporal a fila y luego procesar salidas
 * 
 * @param string $nombreArchivoTemporal - Ruta del archivo temporal_{fecha}.jsonl
 * @param string $nombreArchivoFila - Ruta del archivo fila_{fecha}.json
 * @param string $nombreArchivoSalidas - Ruta del archivo salidas_{fecha}.json
 * @return array - Array con los datos de fila actualizados
 * @throws Exception
 */
function compararYAgregarRegistros($nombreArchivoTemporal, $nombreArchivoFila, $nombreArchivoSalidas = null) 
{
    // Primero: agregar registros de temporal a fila
    $datosFila = compararYAgregarRegistrosDeTemporal($nombreArchivoTemporal, $nombreArchivoFila);
    
    // Segundo: procesar salidas en fila (si existe)
    if ($nombreArchivoSalidas) 
    {
        procesarSalidasEnFila($nombreArchivoFila, $nombreArchivoSalidas);
    }
    
    return $datosFila;
}

/**
 * Buscar el registro del alumno
 * 
 * @param array $datosFila - Array de registros fila
 * @param string $clvuni - Clave única del alumno
 * @return array - Array con ['registro' => registro o null, 'indice' => índice]
 */
function buscarRegistroAlumno($datosFila, $clvuni) 
{
    // Encontrar la ÚLTIMA instancia del clvuni
    $ultimoIndice = null;
    $ultimoRegistro = null;
    for ($i = count($datosFila) - 1; $i >= 0; $i--) 
    {
        if (isset($datosFila[$i]['clvuni']) && $datosFila[$i]['clvuni'] === $clvuni) 
        {
            $ultimoIndice = $i;
            $ultimoRegistro = $datosFila[$i];
            break;
        }
    }
    
    // Si se encontró el registro
    if ($ultimoIndice !== null) 
    {
        return [
            'registro' => $ultimoRegistro,
            'indice' => $ultimoIndice
        ];
    }
    else
    {
        return [
            'registro' => null,
            'indice' => null
        ];
    }
}

/**
 * Crear un nuevo registro de locker
 * 
 * @param string $clvuni - Clave única del alumno
 * @param string $turno - Turno del alumno
 * @return array - Nuevo registro
 */
function crearNuevoRegistro($clvuni, $turno) 
{
    return [
        'clvuni' => $clvuni,
        'turno' => $turno,
        'estado' => -1,
        'posicionEntrada' => 0,
        'fecha_hora_entrada' => date('Y-m-d H:i:s'),
        'locker' => '-',
        'fecha_hora_asignacion' => '-'
    ];
}

/**
 * Actualizar información del registro de locker
 * 
 * @param array $registroCompleto - Registro a actualizar
 * @param int $numeroLocker - Número del locker asignado
 * @return array - Registro actualizado
 */
function actualizarRegistroLocker($registroCompleto, $numeroLocker = 1) 
{
    $registroCompleto['locker'] = $numeroLocker;
    $registroCompleto['fecha_hora_asignacion'] = date('Y-m-d H:i:s');
    
    return $registroCompleto;
}

/**
 * Guardar datos en archivo JSON
 * 
 * @param string $nombreArchivo - Ruta del archivo
 * @param array $datos - Datos a guardar
 * @return bool - True si se guardó correctamente
 * @throws Exception
 */
function guardarEnArchivo($nombreArchivo, $datos) 
{
    $jsonFinal = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $resultado = file_put_contents($nombreArchivo, $jsonFinal);
    
    if ($resultado === false) 
    {
        throw new Exception("No se pudo escribir en el archivo: " . $nombreArchivo);
    }
    
    return true;
}

/**
 * Sacar a un usuario de la cola en Redis
 * 
 * @param object $redis - Conexión a Redis
 * @param string $nombreCola - Nombre de la cola en Redis
 * @param string $clvuni - Clave única del alumno a sacar
 * @return array - Array con ['exito' => bool, 'indice' => índice, 'datosUsuario' => datos]
 * @throws Exception
 */
function sacarDeColaPorClvuni($redis, $nombreCola, $clvuni) 
{
    $lista = $redis->lRange($nombreCola, 0, -1);
    
    if (empty($lista)) 
    {
        throw new Exception("La cola está vacía");
    }
    
    $indiceEncontrado = null;
    $datosUsuarioEnCola = null;
    
    // Buscar al usuario en la cola
    foreach ($lista as $idx => $item) 
    {
        $itemDecodificado = json_decode($item, true);
        if ($itemDecodificado['clvuni'] === $clvuni) 
        {
            $indiceEncontrado = $idx;
            $datosUsuarioEnCola = $itemDecodificado;
            break;
        }
    }
    
    if ($indiceEncontrado === null) 
    {
        throw new Exception("El alumno no se encuentra en la cola");
    }
    
    // Reconstruir la cola sin el usuario
    $nuevaCola = [];
    foreach ($lista as $idx => $item) 
    {
        if ($idx !== $indiceEncontrado) 
        {
            $nuevaCola[] = $item;
        }
    }
    
    // Actualizar la cola en Redis
    $redis->del($nombreCola);
    if (!empty($nuevaCola)) 
    {
        foreach ($nuevaCola as $item) 
        {
            $redis->rPush($nombreCola, $item);
        }
    }
    
    return [
        'exito' => true,
        'indice' => $indiceEncontrado,
        'datosUsuario' => $datosUsuarioEnCola
    ];
}

/**
 * Registrar salidas de un usuario en archivo
 * 
 * @param string $clvuni - Clave única del alumno
 * @param int $turno - Número de turno
 * @param string $estado - Estado del registro (default: 1)
 * @param string $accion - Acción que generó la salidas
 * @param string $fechaHoy - Fecha en formato Y-m-d
 * @return bool - True si se registró correctamente
 * @throws Exception
 */
function registrarSalidas($clvuni, $turno, $estado = 1, $accion = 'salida_voluntaria_cola', $fechaHoy = null) 
{
    if ($fechaHoy === null) 
    {
        $fechaHoy = date('Y-m-d');
    }
    
    $carpetaSalidas = LOCKER_TEMP;
    
    if (!is_dir($carpetaSalidas)) 
    {
        mkdir($carpetaSalidas, 0755, true);
    }
    
    $nombreArchivoSalidas = LOCKER_TEMP . "/salidas_{$fechaHoy}.json";
    
    // Leer salidas existente
    $datosSalidas = [];
    if (file_exists($nombreArchivoSalidas)) 
    {
        $contenido = file_get_contents($nombreArchivoSalidas);
        $datosSalidas = json_decode($contenido, true) ?: [];
    }
    
    // Crear nuevo registro de salidas
    $nuevoRegistroSalidas = [
        'clvuni'               => $clvuni,
        'turno'                => $turno,
        'estado'               => $estado,
        'fecha_hora_salida' => date('Y-m-d H:i:s'),
    ];
    
    // Agregar al array
    $datosSalidas[] = $nuevoRegistroSalidas;
    
    // Guardar en el archivo con formato pretty
    guardarEnArchivo($nombreArchivoSalidas, $datosSalidas);
    
    return true;
}

/**
 * Buscar el último registro de un alumno en todos los archivos del ciclo
 * 
 * @param object $redis - Conexión a Redis
 * @param string $clvuni - Clave única del alumno
 * @return array|null - Último registro encontrado o null
 */
function buscarRegistroAlumnoCicloCompleto($redis, $clvuni)
{
    $ciclo       = $redis->get('config:ciclo') ?: 'sin-ciclo';
    $cicloSeguro = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $ciclo);

    $patron   = LOCKER_LOGS . "/fila_{$cicloSeguro}_*.json";
    $archivos = glob($patron);

    if (empty($archivos)) return null;

    // Ordenar de más reciente a más antiguo
    rsort($archivos);

    foreach ($archivos as $archivo)
    {
        $contenido = file_get_contents($archivo);
        $registros = json_decode($contenido, true) ?: [];

        // Buscar la última instancia del alumno en este archivo
        for ($i = count($registros) - 1; $i >= 0; $i--)
        {
            if (isset($registros[$i]['clvuni']) && $registros[$i]['clvuni'] === $clvuni)
            {
                return $registros[$i];
            }
        }
    }

    return null;
}

?>