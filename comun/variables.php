<?php

	$verBSDist='3.3.7';
	
	// URL del sitio
	if (isset($_SERVER['HTTPS'])) 
	{
		$protocolo="https://";
	} 
	else 
	{
		$protocolo="http://";
	}

	$www_dir=$protocolo.'localhost/';	
	$adds='/adds';
	
	// Encabezado y pie de pagina
	$www_header=$_SERVER['DOCUMENT_ROOT'].'/comun/header.php';
	$www_footer=$_SERVER['DOCUMENT_ROOT'].'/comun/footer.php';
	
	$icono=$www_dir.'/fcq.ico';
	
?>

