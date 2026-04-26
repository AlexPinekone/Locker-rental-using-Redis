<?php
require($_SERVER['DOCUMENT_ROOT'].'/comun/variables.php');
?>

<!-- Todos los Registros del Día -->
<div>
    <h4 style="text-align: left; margin-bottom: 15px;">Todos los Registros del Día</h4>

    <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
        <input type="text" id="buscar-clvuni" class="form-control" 
            placeholder="Buscar por clave única..." 
            style="width: 250px;">
        <button class="btn btn-default" onclick="limpiarBusqueda()">
            Limpiar
        </button>
        <span id="estado-actualizacion" class="text-muted" style="font-size: 12px;">
            Actualizando automáticamente
        </span>
    </div>
    
    <div style="height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
        <table class="table table-striped table-hover" style="margin-bottom: 0; table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 8%;">
                <col style="width: 12%;">
                <col style="width: 10%;">
                <col style="width: 18%;">
                <col style="width: 12%;">
                <col style="width: 12%;">
                <col style="width: 8%;">
                <col style="width: 15%;">
                <col style="width: 5%;">
            </colgroup>
            <thead style="position: sticky; top: 0; background: #f9f9f9; z-index: 10;">
                <tr>
                    <th style="border-bottom: 2px solid #ddd; width: 6%;">Turno</th>
                    <th style="border-bottom: 2px solid #ddd; width: 12%;">Hora Entrada</th>
                    <th style="border-bottom: 2px solid #ddd; width: 10%;">Clave Única</th>
                    <th style="border-bottom: 2px solid #ddd; width: 18%;">Nombres</th>
                    <th style="border-bottom: 2px solid #ddd; width: 12%;">Ape_pat</th>
                    <th style="border-bottom: 2px solid #ddd; width: 12%;">Ape_Mat</th>
                    <th style="border-bottom: 2px solid #ddd; width: 5%;">Locker</th>
                    <th style="border-bottom: 2px solid #ddd; width: 15%;">Hora Asignación</th>
                    <th style="border-bottom: 2px solid #ddd; width: 10%;">Estado</th>
                </tr>
            </thead>
            <tbody id="tabla-todos-registros-body">
                <tr>
                    <td colspan="9" style="text-align: center;">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="text-muted" style="text-align: left; font-size: 12px; margin-top: 5px;">
        * Mostrando todos los registros en fila del día.
    </p>
</div>