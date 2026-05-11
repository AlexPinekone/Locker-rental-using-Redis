<?php

	session_start();
	
	require('../../panel_lockers/redis/comun/conexion_redis.php');
	require_once('../../panel_lockers/redis/comun/utils.php');
	require('./excel/SimpleXLSXGen.php');

	if (isset($error_redis) && $error_redis) 
	{
		// Error en Redis: adios
		echo "Error en conexión a Redis: " . $error_redis;
		exit;
	}

	/**
	 * Convertir el número de estado a su descripción textual
	 */
	function obtenerDescripcionEstado($estado)
	{
		$mapeoEstados = [
			0 => 'Normal',
			1 => 'Salida Propia',
			2 => 'Seleccionando',
			3 => 'Finalizado',
			4 => 'Expulsion',
			5 => 'Cancelado'
		];

		return $mapeoEstados[$estado] ?? 'Desconocido';
	}

	try 
	{
		// Obtener el ciclo
		$ciclo       = $redis->get('config:ciclo') ?: '2025-2026-II';
		$cicloSeguro = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $ciclo);

		$registros = [];

		if (is_dir(LOCKER_LOGS)) 
		{
			// Buscar todos los archivos del ciclo: fila_{ciclo}_*.json
			$patron   = LOCKER_LOGS . "/fila_{$cicloSeguro}_*.json";
			$archivos = glob($patron);

			// Ordenar por fecha
			sort($archivos);

			foreach ($archivos as $archivo) 
			{
				$contenido = file_get_contents($archivo);
				$datos     = json_decode($contenido, true) ?: [];

				// Agregar la fecha del archivo
				foreach ($datos as &$registro) 
				{
					$registro['_fecha'] = basename($archivo, '.json'); // fila_{ciclo}_{fecha}
				}

				unset($registro);

				$registros = array_merge($registros, $datos);
			}
		}

		// Columnas del Excel
		$columnas = [
			['Turno', 'Clave Única', 'Ape_pat', 'Ape_Mat','Nombres','Fecha y Hora de Entrada', 'Fecha y Hora de Asignación',  'Locker',  'Estado']
		];

		// Agregar los registros
		foreach ($registros as $registro) 
		{
			$columnas[] = [
				$registro['turno'] ?? '',
				$registro['clvuni'] ?? '',
				$registro['ape_pat'] ?? '',
				$registro['ape_mat'] ?? '',
				$registro['nombres'] ?? '',
				$registro['fecha_hora_entrada'] ?? '',
				$registro['fecha_hora_asignacion'] ?? '',
				$registro['locker'] ?? '',
				obtenerDescripcionEstado($registro['estado'] ?? '')
			];
		}

		$xlsx = SimpleXLSXGen::fromArray($columnas);
		$xlsx->downloadAs('reporte_registros_' . $ciclo . '.xlsx');
		
	}
	catch (Exception $e) 
	{
		echo "Error: " . $e->getMessage();
	}

?>