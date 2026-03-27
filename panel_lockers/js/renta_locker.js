$(document).ready(function() {
    cargarReservas();
});

function cargarReservas() 
{
    $.getJSON('ajax/obtener_reservas.php')
        .done(function(data) 
        {
            if (data.status === 'success') 
            {
                renderizarTabla(data.reservas);
            } 
            else 
            {
                $('#renta-tabla-container').html(
                    '<p style="color:red;">Error: ' + data.message + '</p>'
                );
            }
        })
        .fail(function() {
            $('#renta-tabla-container').html(
                '<p style="color:red;">Error de conexión</p>'
            );
        });
}

function renderizarTabla(reservas)
{
    if (reservas.length === 0) 
    {
        $('#renta-tabla-container').html(
            '<p style="text-align:center; color:#999;">No hay reservas registradas.</p>'
        );
        return;
    }

    let html = `
        <div style="overflow-x:auto;">
        <table class="table table-striped table-hover table-bordered" style="font-size:14px;">
            <thead style="background:#f5f5f5;">
                <tr>
                    <th>Edificio</th>
                    <th>ID Locker</th>
                    <th>Clave Alumno</th>
                    <th>Nombre</th>
                    <th>Fecha Reserva</th>
                    <th>Estado</th>
                    <th style="width:200px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
    `;

    let areaActual = null;

    reservas.forEach(function(r) {
        // Separador visual cuando cambia el área
        if (r.area !== areaActual) 
        {
            areaActual = r.area;
            html += `
                <tr style="background:#e8f4f8;">
                    <td colspan="7" style="font-weight:bold; padding: 8px 12px;">
                        ${r.area}
                    </td>
                </tr>
            `;
        }

        html += `
            <tr id="fila-${r.id}">
                <td>${r.area}</td>
                <td>${r.id_locker}</td>
                <td>${r.clave_unica}</td>
                <td>${r.nombre_completo}</td>
                <td>${r.fecha_r || '-'}</td>
                <td>${renderizarEstado(r.estado)}</td>
                <td>${renderizarBotones(r)}</td>
            </tr>
        `;
    });

    html += `</tbody></table></div>`;

    $('#renta-tabla-container').html(html);
}

function renderizarEstado(estado) 
{
    const estados = {
        0: '<span class="label label-default">Vacío</span>',
        1: '<span class="label label-primary">Reservado</span>',
        2: '<span class="label label-warning">Sin pago</span>',
        3: '<span class="label label-success">Pagado</span>'
    };
    return estados[estado] || '<span class="label label-default">-</span>';
}

function renderizarBotones(r) 
{
    // Solo mostrar botones si el estado es 1 (Reservado)
    if (parseInt(r.estado) !== 1) return '<span style="color:#999;">—</span>';

    return `
        <button class="btn btn-success btn-xs" 
                onclick="cambiarEstado(${r.id}, 3)"
                title="Marcar como Pagado">
            Pagado
        </button>
        <button class="btn btn-warning btn-xs" 
                onclick="cambiarEstado(${r.id}, 2)"
                title="Marcar como Sin pago"
                style="margin-left:5px;">
            Sin pago
        </button>
    `;
}

function cambiarEstado(idReserva, nuevoEstado) 
{
    const textos = { 2: 'Sin pago', 3: 'Pagado' };
    if (!confirm('¿Cambiar estado a "' + textos[nuevoEstado] + '"?')) return;

    $.post('ajax/actualizar_estado_reserva.php', {
        id_reserva  : idReserva,
        nuevo_estado: nuevoEstado
    })
    .done(function(data) 
    {
        if (data.status === 'success') 
        {
            mostrarMensaje('success', ' Estado actualizado correctamente.');
            cargarReservas(); // recargar tabla
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
    $('#renta-mensaje').html(
        `<div class="alert alert-${tipo}">${texto}</div>`
    );
    setTimeout(function() {
        $('#renta-mensaje').html('');
    }, 4000);
}