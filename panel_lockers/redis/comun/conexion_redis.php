<?php

require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
date_default_timezone_set('America/Mexico_City');

$redis = new Redis();
$error_redis = false;

// Las rutas que se usan a lo largo de todo el sistema
define('PROYECTO_ROOT', realpath($_SERVER['DOCUMENT_ROOT'] . '/sistema_lockers'));
define('LOCKER_LOGS',   realpath($_SERVER['DOCUMENT_ROOT'] . '/locker_logs'));
define('LOCKER_TEMP',   realpath($_SERVER['DOCUMENT_ROOT'] . '/locker_logs/temp'));

try 
{
    $redis->connect('127.0.0.1', 6379);
} 
catch (Exception $e) 
{
    $error_redis = "Error: No se pudo conectar a Redis.";
}

//La cola de redis que se usa en todo el sistema
$nombreCola = "locker";
?>
