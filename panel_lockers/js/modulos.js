// Objeto para almacenar los módulos cargados
const modulos = {};

/**
 * Configurar eventos de los botones y menú
 */
$(document).ready(function() {
    // Evento cuando se hace clic en un botón de módulo
    $(document).on('click', '.btn-modulo', function(e) {
        e.preventDefault();
        
        const modulo = $(this).data('modulo');
        cambiarModulo(modulo);
    });

    // Evento cuando se hace clic en un elemento del menú desplegable
    $(document).on('click', '.menu-modulo', function(e) {
        e.preventDefault();
        
        const modulo = $(this).data('modulo');
        cambiarModulo(modulo);
        
        // Cerrar el dropdown después de hacer clic
        $('.dropdown-toggle').parent().removeClass('open');
    });
});

/**
 * Cambia el módulo activo y carga su contenido
 * @param {string} nombreModulo - Nombre del módulo (quimica, fila_virtual, etc.)
 */
function cambiarModulo(nombreModulo) {
    // Actualizar estados de botones
    $('.btn-modulo').removeClass('activo');
    $('[data-modulo="' + nombreModulo + '"]').addClass('activo');
    
    // Cargar el módulo si no está cargado
    if (!modulos[nombreModulo]) {
        cargarModulo(nombreModulo);
    } else {
        // Si ya está cargado, solo mostrar el contenido (reinicializar si es necesario)
        cargarModulo(nombreModulo);
    }
}

/**
 * Carga un módulo desde su archivo PHP
 * @param {string} nombreModulo - Nombre del módulo (quimica, fila_virtual, etc.)
 */
function cargarModulo(nombreModulo) {
    $('#cargando').show();

    // Determinar la ruta del archivo
    const rutaModulo = `modulos/${nombreModulo}.php`;

    $.get(rutaModulo, function(html) {
        // Cargar el HTML en el contenedor
        $('#contenido-actual').html(html);
        
        // Esconder el indicador de carga
        $('#cargando').hide();

        // Marcar como cargado
        modulos[nombreModulo] = true;

        // Inicializar funciones específicas del módulo si existen
        if (typeof window[`inicializar_${nombreModulo}`] === 'function') {
            window[`inicializar_${nombreModulo}`]();
        }
    }).fail(function() {
        $('#contenido-actual').html(
            '<div class="alert alert-danger" role="alert">' +
            '<strong>Error:</strong> No se pudo cargar el módulo "' + nombreModulo + '".' +
            '</div>'
        );
        $('#cargando').hide();
    });
}