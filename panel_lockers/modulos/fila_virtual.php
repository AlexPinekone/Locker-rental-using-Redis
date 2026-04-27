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
        setInterval(function() {
            if (typeof cargarDetallesCola === 'function') 
            {
                cargarDetallesCola();
            }
        }, 2000);
        iniciarActualizacionRegistros();
    }

    $(document).ready(function() {
        inicializar_fila_virtual();
    });
</script>