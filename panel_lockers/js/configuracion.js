$(document).ready(function() {
    cargarConfiguracion();
});

function cargarConfiguracion() 
{
    $.getJSON('ajax/obtener_config.php')
        .done(function(data) 
        {
            if (data.status === 'success') 
            {
                renderizarFormulario(data.config);
            } 
            else 
            {
                $('#config-form-container').html(
                    '<p style="color:red;">Error al cargar: ' + data.message + '</p>'
                );
            }
        })
        .fail(function() {
            $('#config-form-container').html(
                '<p style="color:red;">Error de conexión</p>'
            );
        });
}

function renderizarFormulario(config) 
{
    const html = `
        <div style="font-size:16px;">

            <!-- Fecha y hora de inicio -->
            <div style="display:flex; gap:20px; margin-bottom:15px;">
                <div class="form-group" style="width:400px;">
                    <label>Fecha de inicio</label>
                    <input type="date" id="fecha_ini" class="form-control" 
                        value="${config.fecha_ini || ''}">
                </div>
                <div class="form-group" style="width:400px;">
                    <label>Hora de inicio</label>
                    <input type="time" id="hora_ini" class="form-control" 
                        value="${config.hora_ini || ''}">
                </div>
            </div>

            <!-- Fecha y hora de cierre -->
            <div style="display:flex; gap:20px; margin-bottom:15px;">
                <div class="form-group" style="width:400px;">
                    <label>Fecha de cierre</label>
                    <input type="date" id="fecha_fin" class="form-control" 
                        value="${config.fecha_fin || ''}">
                </div>
                <div class="form-group" style="width:400px;">
                    <label>Hora de cierre</label>
                    <input type="time" id="hora_fin" class="form-control" 
                        value="${config.hora_fin || ''}">
                </div>
            </div>

            <!-- Costo -->
            <div style="margin-bottom:15px;">
                <div class="form-group" style="width:400px;">
                    <label>Costo</label>
                    <div class="input-group">
                        <span class="input-group-addon">$</span>
                        <input type="number" id="costo" class="form-control" 
                            step="0.01" min="0" value="${config.costo || ''}">
                    </div>
                </div>
            </div>

            <!-- Botón guardar -->
            <div style="text-align:right; margin-top:20px;">
                <button class="btn btn-primary btn-lg" onclick="guardarConfiguracion()">
                    Guardar
                </button>
            </div>

        </div>
    `;
    $('#config-form-container').html(html);
}

function guardarConfiguracion() 
{
    const datos = {
        fecha_ini : $('#fecha_ini').val(),
        hora_ini  : $('#hora_ini').val(),
        fecha_fin : $('#fecha_fin').val(),
        hora_fin  : $('#hora_fin').val(),
        costo     : $('#costo').val()
    };

    // Validación básica
    if (!datos.fecha_ini || !datos.hora_ini || !datos.fecha_fin || !datos.hora_fin || datos.costo === '') 
    {
        mostrarMensaje('warning', 'Por favor llena todos los campos.');
        return;
    }

    if (parseFloat(datos.costo) < 0) 
    {
        mostrarMensaje('warning', 'El costo no puede ser negativo.');
        return;
    }

    $.post('ajax/guardar_config.php', datos)
        .done(function(data) 
        {
            if (data.status === 'success') 
            {
                mostrarMensaje('success', '✅ Configuración guardada correctamente.');
            } 
            else 
            {
                mostrarMensaje('danger', 'Error: ' + data.message);
            }
        })
        .fail(function() {
            mostrarMensaje('danger', 'Error de conexión.');
        });
}

function mostrarMensaje(tipo, texto) 
{
    $('#config-mensaje').html(
        `<div class="alert alert-${tipo}" style="text-align:center;">${texto}</div>`
    );
    // Auto-ocultar después de 4 segundos
    setTimeout(function() {
        $('#config-mensaje').html('');
    }, 4000);
}