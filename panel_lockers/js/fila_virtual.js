// Variables globales
let intervaloTodosRegistros = null;
let todosLosRegistros = [];
let busquedaActiva = false;

// Funciones de cola

function vincularEventosCola() 
{
    $('#btn-atender').off('click').on('click', atenderSiguiente);
    $('#btn-abrir-lockers').off('click').on('click', abrirSistemaLockers);
    $('#btn-cerrar-lockers').off('click').on('click', cerrarSistemaLockers);
}

function atenderSiguiente() 
{
    $('#btn-atender').prop('disabled', true);

    $.post('redis/cola/atender_alumno.php', function(data) {
        if (data.status === 'success') 
        {
            $('#cargando').show();
            cargarComponenteColaActiva();
            actualizarDatosRegistros();
            $('#cargando').hide();
            $('#btn-atender').prop('disabled', false);
        } 
        else if (data.status === 'empty') 
        {
            $('#mensaje-atender').html('<p class="alert alert-warning">No hay alumnos en la cola</p>');
            $('#btn-atender').prop('disabled', true);
        } 
        else 
        {
            $('#mensaje-atender').html('<p class="alert alert-danger">Error: ' + data.message + '</p>');
            $('#btn-atender').prop('disabled', false);
        }
    }, 'json').fail(function() {
        $('#mensaje-atender').html('<p class="alert alert-danger">Error de conexión</p>');
        $('#btn-atender').prop('disabled', false);
    });
}

function abrirSistemaLockers() 
{
    $('#btn-abrir-lockers').prop('disabled', true);

    $.post('redis/sistema/abrir_sistema.php', function(data) {
        if (data.status === 'success') 
        {
            $('#mensaje-sistema-lockers').html(
                '<p class="alert alert-success"> ' + data.message + '</p>'
            );
            $('#btn-abrir-lockers')
                .text('Sistema Abierto')
                .addClass('btn-success')
                .removeClass('btn-info');
        } 
        else 
        {
            $('#mensaje-sistema-lockers').html(
                '<p class="alert alert-warning">' + data.message + '</p>'
            );
            $('#btn-abrir-lockers').prop('disabled', false);
        }
    }, 'json').fail(function() {
        $('#mensaje-sistema-lockers').html('<p class="alert alert-danger">Error de conexión</p>');
        $('#btn-abrir-lockers').prop('disabled', false);
    });
}

function cerrarSistemaLockers() 
{
    if (!confirm('¿Estás seguro de que deseas cerrar el sistema? Se vaciará la cola activa.')) 
    {
        return;
    }

    $('#btn-cerrar-lockers').prop('disabled', true);

    $.post('redis/sistema/cerrar_sistema.php', function(data) 
    {
        if (data.status === 'success') 
        {
            $('#mensaje-sistema-lockers').html(
                '<p class="alert alert-danger"> ' + data.message + '</p>'
            );
            $('#btn-abrir-lockers')
                .text('Abrir Sistema')
                .removeClass('btn-success')
                .addClass('btn-info')
                .prop('disabled', false);
            $('#btn-cerrar-lockers').prop('disabled', false);
            $('#tabla-proximos-body').html('<tr><td colspan="6" style="text-align:center;">Sistema cerrado</td></tr>');
            $('#total-cola').text('0');
        } 
        else 
        {
            $('#mensaje-sistema-lockers').html(
                '<p class="alert alert-warning"> ' + data.message + '</p>'
            );
            $('#btn-cerrar-lockers').prop('disabled', false);
        }
    }, 'json').fail(function() {
        $('#mensaje-sistema-lockers').html('<p class="alert alert-danger">Error de conexión</p>');
        $('#btn-cerrar-lockers').prop('disabled', false);
    });
}

// Funciones de registros

function vincularEventosRegistros() 
{
    $('#buscar-clvuni').off('input').on('input', function() {
        manejarBusqueda(this.value);
    });
}

function iniciarActualizacionRegistros() 
{
    actualizarDatosRegistros();
    intervaloTodosRegistros = setInterval(actualizarDatosRegistros, 5000);
    $('#estado-actualizacion').html('Actualizando automáticamente').css('color', '#009900');
}

function pausarActualizacionRegistros() 
{
    clearInterval(intervaloTodosRegistros);
    intervaloTodosRegistros = null;
    $('#estado-actualizacion').html('Actualización pausada').css('color', '#e67e22');
}

function actualizarDatosRegistros() 
{
    $.getJSON('redis/consultas/obtener_todos_registros.php', function(data) {
        if (data.status === 'success') {
            todosLosRegistros = data.registros;

            if (!busquedaActiva) {
                renderizarTablaRegistros(todosLosRegistros);
            }
        }
    });
}

function renderizarTablaRegistros(registros) 
{
    if (registros.length === 0) 
    {
        $('#tabla-todos-registros-body').html(
            '<tr><td colspan="9" style="text-align:center;">No hay registros</td></tr>'
        );
        return;
    }

    let html = '';
    registros.forEach(function(registro) {
        html += '<tr>';
        html += '<td>' + (registro.turno || '-') + '</td>';
        html += '<td>' + (registro.fecha_hora_entrada || '-') + '</td>';
        html += '<td>' + (registro.clvuni || '-') + '</td>';
        html += '<td>' + (registro.nombres || '-') + '</td>';
        html += '<td>' + (registro.ape_pat || '-') + '</td>';
        html += '<td>' + (registro.ape_mat || '-') + '</td>';
        html += '<td>' + (registro.locker || '-') + '</td>';
        html += '<td>' + (registro.fecha_hora_asignacion || '-') + '</td>';
        html += '<td><span class="badge">' + obtenerTextoEstado(registro.estado) + '</span></td>';
        html += '</tr>';
    });
    $('#tabla-todos-registros-body').html(html);
}

function manejarBusqueda(valor) 
{
    valor = valor.trim();

    if (valor === '') {
        limpiarBusqueda();
        return;
    }

    busquedaActiva = true;
    pausarActualizacionRegistros();

    const filtrados = todosLosRegistros.filter(function(registro) {
        return registro.clvuni && 
            registro.clvuni.toLowerCase().includes(valor.toLowerCase());
    });

    if (filtrados.length === 0) 
    {
        $('#tabla-todos-registros-body').html(
            '<tr><td colspan="9" style="text-align:center;">No se encontraron resultados para: <strong>' + valor + '</strong></td></tr>'
        );
    } 
    else 
    {
        renderizarTablaRegistros(filtrados);
    }
}

function limpiarBusqueda() 
{
    busquedaActiva = false;
    $('#buscar-clvuni').val('');
    renderizarTablaRegistros(todosLosRegistros);
    iniciarActualizacionRegistros();
}

function obtenerTextoEstado(estado) 
{
    switch(estado) 
    {
        case 0: return 'Normal';
        case 1: return 'Salida Propia';
        case 2: return 'Seleccionando';
        case 3: return 'Finalizado';
        case 4: return 'Expulsion';
        case 5: return 'Cancelado';
        default: return 'Desconocido';
    }
}

// Funciones de componentes

function cargarComponenteColaActiva() 
{
    $.get('componentes/cola_activa.php', function(html) {
        $('#seccion-cola-activa').html(html);
        vincularEventosCola();
    });
}

function cargarComponenteRegistros() 
{
    $.get('componentes/registros_dia.php', function(html) {
        $('#seccion-registros').html(html);
        vincularEventosRegistros();
        actualizarDatosRegistros();
    });
}