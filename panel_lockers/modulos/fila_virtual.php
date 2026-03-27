<?php
// Este archivo solo contiene HTML
// Las funciones están en js/fila_virtual.js
?>

<div style="padding: 20px;">
    <h2>Fila Virtual</h2>
    
    <!-- Cola Activa -->
    <div id="seccion-cola-activa" style="margin-bottom: 40px;">
        <p style="text-align: center;">Cargando cola activa...</p>
    </div>

    <hr style="margin: 40px 0; border-top: 2px solid #ccc;">

    <!-- Todos los Registros -->
    <div id="seccion-registros">
        <p style="text-align: center;">Cargando registros...</p>
    </div>
</div>

<script type="text/javascript">
    function inicializar_fila_virtual() 
    {
        cargarComponenteColaActiva();
        cargarComponenteRegistros();
        setInterval(cargarComponenteColaActiva, 2000);
        iniciarActualizacionRegistros();
    }
</script>