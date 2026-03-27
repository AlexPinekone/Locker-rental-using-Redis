/**
 * seleccionar_locker.js - MODIFICADO
 * Lógica completa para la selección de lockers
 * 
 * ✅ CAMBIO: Ahora también actualiza el archivo JSON de fila con el locker seleccionado
 */

// Variables globales
let tiempoRestante = 120; // 2 minutos
let lockersSeleccionado = null;
let lockersReservados = [];
let clvuni = '';
let ciclo = '';

/**
 * Inicializar la página
 */
function inicializarSeleccionador(clvuniParam, cicloParam, lockersReservadosParam, edificiosDataParam, tiempoServidor) {
    clvuni = clvuniParam;
    ciclo = cicloParam;
    lockersReservados = lockersReservadosParam;

    tiempoRestante = tiempoServidor;
    
    // Generar mapas para todos los edificios
    if (edificiosDataParam) {
        for (let edificio in edificiosDataParam) {
            if (edificiosDataParam.hasOwnProperty(edificio)) {
                generarMapa(edificio, edificiosDataParam[edificio], lockersReservados);
            }
        }
    }
    
    // Iniciar timer
    iniciarTimer();
}

/**
 * Función genérica para generar el mapa de lockers
 * @param {string} edificio - Código del edificio
 * @param {array} lockers - Array de lockers
 * @param {array} reservados - Array de IDs de lockers reservados
 */
function generarMapa(edificio, lockers, reservados) {
    const contenedor = document.getElementById('mapa-' + edificio);
    if (!contenedor) return;
    
    let html = '';
    const porRenglon = {};
    
    lockers.forEach(locker => {
        if (!porRenglon[locker.ren]) porRenglon[locker.ren] = [];
        porRenglon[locker.ren].push(locker);
    });

    Object.keys(porRenglon).sort((a, b) => parseInt(a) - parseInt(b)).forEach(ren => {
        porRenglon[ren].sort((a, b) => parseInt(a.col) - parseInt(b.col));

        html += '<div style="margin-bottom: 15px;">';
        porRenglon[ren].forEach(locker => {
            // ✅ Usar el ID único de la BD
            const isReservado = reservados.includes(parseInt(locker.id)); 
            const isInactivo = locker.activo == 0;
            const isSeleccionado = lockersSeleccionado && lockersSeleccionado.id === locker.id;
            
            let clases = 'locker-btn';
            if (isReservado) clases += ' reservado';
            if (isInactivo) clases += ' inactivo';
            if (isSeleccionado) clases += ' seleccionado';
            
            const disabled = isReservado || isInactivo;
            
            // ID HTML único usando el ID de la BD
            const lockerData = JSON.stringify({
                id: locker.id,              // ✅ ID único de la BD
                id_a: locker.id_a, 
                numero: locker.numero, 
                edificio: edificio,
                ren: locker.ren,
                col: locker.col
            }).replace(/"/g, '&quot;');

            html += `
                <button 
                    class="${clases}" 
                    id="locker-node-${locker.id}"
                    ${disabled ? 'disabled' : ''}
                    onclick='seleccionarLocker(${lockerData})'
                    title="Locker ${locker.numero} - Edificio ${edificio}">
                    <span class="locker-numero">${locker.numero}</span>
                    <span class="locker-pos">R${locker.ren}C${locker.col}</span>
                </button>
            `;
        });
        html += '</div>';
    });

    contenedor.innerHTML = html;
}

/**
 * Seleccionar un locker
 */
function seleccionarLocker(locker) {
    // 1. Quitar clase del anterior usando el ID único
    if (lockersSeleccionado) {
        const btnAnterior = document.getElementById('locker-node-' + lockersSeleccionado.id);
        if (btnAnterior) {
            btnAnterior.classList.remove('seleccionado');
        }
    }

    // 2. Marcar el nuevo
    lockersSeleccionado = locker;
    const btnNuevo = document.getElementById('locker-node-' + locker.id);
    if (btnNuevo) {
        btnNuevo.classList.add('seleccionado');
    }

    document.getElementById('btnConfirmarSeleccion').disabled = false;
}

/**
 * Confirmar selección y mostrar modal
 */
function confirmarSeleccion() {
    if (lockersSeleccionado) {
        document.getElementById('lockersSeleccionadoInfo').textContent = 
            `Locker #${lockersSeleccionado.numero} - Edificio ${lockersSeleccionado.edificio}`;
        document.getElementById('confirmationModal').classList.add('active');
    }
}

/**
 * Cancelar modal
 */
function cancelarModal() {
    document.getElementById('confirmationModal').classList.remove('active');
}

/**
 * Confirmar final y guardar reserva
 */
function confirmarFinal() {
    if (lockersSeleccionado) {
        guardarReserva(lockersSeleccionado);
    }
}

/**
 * Cancelar y volver al dashboard
 */
function cancelarSeleccion() {
    if (confirm('¿Deseas cancelar la selección y volver al panel?')) {
        window.location.href = 'dashboard.php';
    }
}

/**
 * Guardar reserva en la base de datos y actualizar JSON de fila
 * ✅ MODIFICADO: Ahora también actualiza el archivo JSON con el locker
 */
function guardarReserva(locker) {
    // Primero: Guardar en la BD (plantilla.loc_reserva)
    $.post('ajax/reservar_locker.php', {
        id_l: locker.id,        // ID único de la BD
        clvuni: clvuni,
        ciclo: ciclo
    })
    .done(function(data) {
        if (data.status === 'success') {
            // ✅ CAMBIO: Después de guardar en BD, actualizar el JSON de fila
            actualizarLockerEnJSON(locker);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .fail(function() {
        alert('Error al guardar la reserva');
    });
}

/**
 * ✅ NUEVA FUNCIÓN: Actualizar el archivo JSON de fila con el locker seleccionado
 * Formato del locker: "Edificio-Numero" (ej: "A-5", "B-12")
 */
function actualizarLockerEnJSON(locker) {
    $.post('ajax/actualizar_locker_json.php', {
        clvuni: clvuni,
        id_edificio: locker.edificio,           // ✅ Código del edificio (A, B, C, etc.)
        numero_locker: locker.numero,            // ✅ Número del locker
        ciclo: ciclo
    })
    .done(function(data) {
        if (data.status === 'success') {
            // ✅ Locker actualizado en JSON y BD
            document.getElementById('confirmationModal').classList.remove('active');
            alert('¡Locker reservado exitosamente!');
            
            // Limpiar sesión de tiempo y redirigir
            window.location.href = 'dashboard.php';
        } else {
            // Si falla la actualización del JSON, mostrar error pero la BD ya tiene el dato
            console.warn('Advertencia: El locker se guardó en BD pero hubo error al actualizar JSON: ' + data.message);
            alert('El locker fue asignado, pero hubo un error al actualizar el historial. Por favor, recargue la página.');
            window.location.href = 'dashboard.php';
        }
    })
    .fail(function() {
        console.error('Error de conexión al actualizar JSON');
        alert('Error al actualizar el historial. El locker puede haber sido guardado. Redirigiendo...');
        window.location.href = 'dashboard.php';
    });
}

/**
 * Iniciar contador de 2 minutos
 */
function iniciarTimer() {
    const timerDisplay = document.getElementById('timerDisplay');
    
    const intervalo = setInterval(() => {
        tiempoRestante--;
        
        const minutos = Math.floor(tiempoRestante / 60);
        const segundos = tiempoRestante % 60;
        
        timerDisplay.textContent = 
            String(minutos).padStart(2, '0') + ':' + String(segundos).padStart(2, '0');
        
        // Cambiar color cuando quedan menos de 30 segundos
        if (tiempoRestante <= 30) {
            timerDisplay.classList.add('warning');
        }
        
        // Tiempo agotado
        if (tiempoRestante <= 0) {
            clearInterval(intervalo);
            tiempoAgotado();
        }
    }, 1000);
}

/**
 * Manejar cuando se agota el tiempo
 */
function tiempoAgotado() {
    alert('Se ha agotado el tiempo para seleccionar un locker.');
    // Aquí se ejecutará el sistema de expulsión programado después
    window.location.href = 'dashboard.php';
}

/**
 * Configurar event listeners cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function() {
    // Botón confirmar selección
    const btnConfirmarSeleccion = document.getElementById('btnConfirmarSeleccion');
    if (btnConfirmarSeleccion) {
        btnConfirmarSeleccion.addEventListener('click', confirmarSeleccion);
    }

    // Botón cancelar modal
    const btnCancelarModal = document.getElementById('btnCancelarModal');
    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', cancelarModal);
    }

    // Botón confirmar final
    const btnConfirmarFinal = document.getElementById('btnConfirmarFinal');
    if (btnConfirmarFinal) {
        btnConfirmarFinal.addEventListener('click', confirmarFinal);
    }

    // Botón cancelar
    const btnCancelar = document.getElementById('btnCancelar');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', cancelarSeleccion);
    }
});