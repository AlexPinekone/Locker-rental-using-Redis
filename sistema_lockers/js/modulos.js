// Objeto para almacenar los módulos cargados
const modulos = {};

/**
 * Configurar eventos de las pestañas
 */
$(document).ready(function() 
{
    // Evento cuando se hace clic en una pestaña
    $(document).on('click', '.tab-link', function(e) {
        e.preventDefault();
        
        const modulo = $(this).data('modulo');
        
        // Cambiar a la pestaña
        $(this).tab('show');
        
        // Cargar el módulo si no está cargado
        if (!modulos[modulo]) 
        {
            cargarModulo(modulo);
            modulos[modulo] = true;
        }
    });
});

/**
 * Carga un módulo desde su archivo PHP
 * @param {string} nombreModulo - Nombre del módulo (quimica, fila_virtual, etc.)
 */
function cargarModulo(nombreModulo) 
{
    $('#cargando').show();

    // Determinar la ruta del archivo
    const rutaModulo = `modulos/${nombreModulo}.php`;
    
    // Obtener el ID del contenedor
    const idContenedor = `contenido-${nombreModulo.replace(/_/g, '-')}`;

    $.get(rutaModulo, function(html) {
        // Cargar el HTML en el contenedor
        $(`#${idContenedor}`).html(html);
        
        // Esconder el indicador de carga
        $('#cargando').hide();

        // Inicializar funciones específicas del módulo si existen
        if (typeof window[`inicializar_${nombreModulo}`] === 'function') 
        {
            window[`inicializar_${nombreModulo}`]();
        }
    }).fail(function() {
        $(`#${idContenedor}`).html(
            '<div class="alert alert-danger" role="alert">' +
            '<strong>Error:</strong> No se pudo cargar el módulo.' +
            '</div>'
        );
        $('#cargando').hide();
    });
}