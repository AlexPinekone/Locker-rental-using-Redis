<?php
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
?>

<!-- Info y botones de la cola -->
<div style="margin-bottom: 30px;">

    <div style="margin: 20px 0; text-align: center;">
        <button class="btn btn-info btn-lg" id="btn-abrir-lockers" style="width: 200px;">
            Abrir Sistema
        </button>
        <button class="btn btn-danger btn-lg" id="btn-cerrar-lockers" style="width: 200px; margin-left: 10px;">
            Cerrar Sistema
        </button>
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
        <table class="table table-striped table-hover" style="margin-bottom: 0;">
            <thead style="position: sticky; top: 0; background: #f9f9f9; z-index: 10;">
                <tr>
                    <th style="border-bottom: 2px solid #ddd;">Turno</th>
                    <th style="border-bottom: 2px solid #ddd;">Hora Entrada</th>
                    <th style="border-bottom: 2px solid #ddd;">Clave Única</th>
                    <th style="border-bottom: 2px solid #ddd;">Locker</th>
                    <th style="border-bottom: 2px solid #ddd;">Estado</th>
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
    // Script para cargar datos de la cola cuando este componente se carga
    $(document).ready(function() {
        cargarDetallesCola();
    });

    function cargarDetallesCola() {
        $.getJSON('redis/consultas/obtener_detalles_fila.php', function(data) {
            if (data.status === 'success') {
                $('#total-cola').text(data.total_cola);
                
                if (data.detalles.length === 0) {
                    $('#tabla-proximos-body').html('<tr><td colspan="5" style="text-align: center;">Cola vacía</td></tr>');
                    $('#btn-atender').prop('disabled', true);
                } else {
                    let html = '';
                    data.detalles.forEach(function(alumno) {
                        html += '<tr>';
                        html += '<td>' + alumno.turno + '</td>';
                        html += '<td>' + alumno.fecha_hora_entrada + '</td>';
                        html += '<td>' + alumno.clvuni + '</td>';
                        html += '<td>' + alumno.locker + '</td>';
                        html += '<td><span class="badge">' + obtenerTextoEstado(alumno.estado) + '</span></td>';
                        html += '</tr>';
                    });
                    $('#tabla-proximos-body').html(html);
                    $('#btn-atender').prop('disabled', false);
                }
            }
        });
    }

    function obtenerTextoEstado(estado) {
        switch(estado) {
            case 0: return 'Normal';
            case 1: return 'Salida Propia';
            case 2: return 'Expulsión';
            default: return 'Desconocido';
        }
    }
</script>