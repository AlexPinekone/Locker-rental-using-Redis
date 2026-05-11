/**
 * seleccionar_locker.js
 * Lógica completa para la selección de lockers
 */

// Variables globales
let tiempoRestante = 20; // Antorcha 120 2 minutos
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
            const isReservado = reservados.includes(locker.id); 
            const isInactivo = locker.activo == 0;
            const isSeleccionado = lockersSeleccionado && 
                                   lockersSeleccionado.edificio === edificio &&
                                   lockersSeleccionado.numero === locker.numero;
            
            let clases = 'locker-btn';
            if (isReservado) clases += ' reservado';
            if (isInactivo) clases += ' inactivo';
            if (isSeleccionado) clases += ' seleccionado';
            
            const disabled = isReservado || isInactivo;
            
            // ID ÚNICO: Combina edificio + número
            const lockerId = `${edificio}_${locker.numero}`;
            const lockerData = JSON.stringify({
                id_a: locker.id_a, 
                numero: locker.numero, 
                edificio: edificio
            }).replace(/"/g, '&quot;');

            html += `
                <button 
                    class="${clases}" 
                    id="locker-${lockerId}"
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
 * Seleccionar un locker (Versión corregida)
 */
function seleccionarLocker(locker) {
    // 1. Quitar clase del anterior
    if (lockersSeleccionado) {
        const btnAnterior = document.getElementById(
            `locker-${lockersSeleccionado.edificio}_${lockersSeleccionado.numero}`
        );
        if (btnAnterior) {
            btnAnterior.classList.remove('seleccionado');
        }
    }

    // 2. Marcar el nuevo
    lockersSeleccionado = locker;
    const btnNuevo = document.getElementById(
        `locker-${locker.edificio}_${locker.numero}`
    );
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
 * Guardar reserva en la base de datos
 */
function guardarReserva(locker) {
    $.post('ajax/reservar_locker.php', {
        id_l: locker.numero,
        clvuni: clvuni,
        ciclo: ciclo
    })
    .done(function(data) {
        if (data.status === 'success') {
            document.getElementById('confirmationModal').classList.remove('active');
            
            // Mostrar mensaje de éxito
            alert('¡Locker reservado exitosamente!');
            
            // Redirigir al dashboard
            window.location.href = 'dashboard.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .fail(function() {
        alert('Error al guardar la reserva');
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