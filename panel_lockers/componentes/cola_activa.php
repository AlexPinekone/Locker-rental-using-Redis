<?php
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
?>

<!-- Info y botones de la cola -->
<div style="margin-bottom: 30px;">

    <div style="margin: 20px 0; text-align: center;">
        <!--
        <button class="btn btn-info btn-lg" id="btn-abrir-lockers" style="width: 200px;">
            Abrir Sistema
        </button>
        <button class="btn btn-danger btn-lg" id="btn-cerrar-lockers" style="width: 200px; margin-left: 10px;">
            Cerrar Sistema
        </button>
        -->
        <div id="mensaje-sistema-lockers" style="margin-top: 10px;"></div>
    </div>

    <div class="alert alert-info" style="text-align: center; display: inline-block; width: 100%;">
        <p style="margin: 0;">Alumnos en espera:</p>
        <h3 style="margin: 10px 0 0 0;"><strong id="total-cola">0</strong></h3>
    </div>

    <div style="margin: 20px 0; text-align: center;">
        <button class="btn btn-success btn-lg" id="btn-atender" style="width: 300px;">
            Atender Siguiente
        </button>
        <div id="mensaje-atender" style="margin-top: 10px;"></div>
    </div>
</div>

<!-- Tabla de Alumnos en Cola -->
<div>
    <h4 style="text-align: left; margin-bottom: 15px;">Lista de Espera</h4>
    
    <div style="height: 350px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
        <table class="table table-striped table-hover" style="margin-bottom: 0; table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 15%;">
                <col style="width: 25%;">
                <col style="width: 35%;">
                <col style="width: 25%;">
            </colgroup>
            <thead style="position: sticky; top: 0; background: #f9f9f9; z-index: 10;">
                <tr>
                    <th style="border-bottom: 2px solid #ddd; width: 15%;">Turno</th>
                    <th style="border-bottom: 2px solid #ddd; width: 25%;">Hora Entrada</th>
                    <th style="border-bottom: 2px solid #ddd; width: 35%;">Clave Única</th>
                    <th style="border-bottom: 2px solid #ddd; width: 25%;">Estado</th>
                </tr>
            </thead>
            <tbody id="tabla-proximos-body">
                <tr>
                    <td colspan="5" style="text-align: center;">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="text-muted" style="text-align: left; font-size: 12px; margin-top: 5px;">
        * Deslice hacia abajo para ver todos los alumnos en espera.
    </p>
</div>

<script type="text/javascript">
    var ultimaColaActiva = ultimaColaActiva || null;
    var ultimoTotalCola = ultimoTotalCola || null;

    function cargarDetallesCola() 
    {
        $.getJSON('redis/consultas/obtener_detalles_fila.php', function(data) {
            if (data.status !== 'success') 
            {
                return;
            }

            const detalles = data.detalles || [];
            const totalCola = data.total_cola || 0; // cola de Redis
            const totalActivos = data.total_activos || detalles.length;
            const detallesJSON = JSON.stringify(detalles);

            if (detallesJSON === ultimaColaActiva && totalActivos === ultimoTotalCola) 
            {
                return;
            }

            ultimaColaActiva = detallesJSON;
            ultimoTotalCola = totalActivos;
            $('#total-cola').text(totalActivos);

            if (detalles.length === 0) 
            {
                $('#tabla-proximos-body').html('<tr><td colspan="5" style="text-align: center;">Cola vacía</td></tr>');
                $('#btn-atender').prop('disabled', true);
            } 
            else 
            {
                let html = '';
                detalles.forEach(function(alumno) {
                    html += '<tr>';
                    html += '<td>' + alumno.turno + '</td>';
                    html += '<td>' + alumno.fecha_hora_entrada + '</td>';
                    html += '<td>' + alumno.clvuni + '</td>';
                    html += '<td><span class="badge">' + obtenerTextoEstado(alumno.estado) + '</span></td>';
                    html += '</tr>';
                });
                $('#tabla-proximos-body').html(html);
                $('#btn-atender').prop('disabled', totalCola === 0);
            }
        }).fail(function() {
            $('#tabla-proximos-body').html('<tr><td colspan="5" style="text-align: center;">Error de conexión</td></tr>');
        });
    }

    function obtenerTextoEstado(estado) 
    {
        switch(estado) 
        {
            case 0: return 'Normal';
            case 1: return 'Salida Propia';
            case 2: return 'Seleccionando';
            case 3: return 'Finalizado';
            case 4: return 'Expulsado';
            case 5: return 'Cancelaci&oacute;n';
            default: return 'Desconocido';
        }
    }
</script>