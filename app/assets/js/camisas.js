// Modal de confirmación antes de guardar un pago (app/camisas/public/index.php)
// — cada alumno es su propio <form data-confirmar-pago>. Se intercepta el
// envío real, se muestra el modal #confirmar-pago con un resumen de lo que
// se va a guardar, y solo al confirmar se reenvía el mismo formulario ya con
// la acción elegida. Mismo criterio "vendorizado, sin framework" que
// app/assets/js/inscripciones.js y app/assets/js/admin.js.

(function () {
    var dialogo = document.getElementById('confirmar-pago');
    if (!dialogo) {
        return;
    }

    var elNombre = document.getElementById('confirmar-pago-nombre');
    var elDetalle = document.getElementById('confirmar-pago-detalle');
    var botonConfirmar = document.getElementById('confirmar-pago-boton');

    var formularioPendiente = null;
    var accionPendiente = null;

    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;
        if (!(formulario instanceof HTMLFormElement) || !formulario.hasAttribute('data-confirmar-pago')) {
            return;
        }
        // El reenvío programático de abajo (tras confirmar) no debe volver a
        // interceptarse — si no, el modal se abriría en un loop infinito.
        if (formulario.dataset.confirmado === '1') {
            return;
        }
        evento.preventDefault();

        var submitter = evento.submitter;
        var accion = submitter ? submitter.value : 'guardar';
        var inputMonto = formulario.querySelector('[name="camisa_pago"]');
        var inputPide = formulario.querySelector('[name="camisa_pedir"]');
        var pideCamisa = accion === 'liquidar' || (inputPide && (inputPide.type !== 'checkbox' || inputPide.checked));

        var monto = accion === 'liquidar' && submitter
            ? submitter.dataset.montoFinal
            : '$' + (inputMonto ? inputMonto.value.trim() : '0');

        elNombre.textContent = formulario.dataset.alumnoNombre || '';
        elDetalle.textContent = (accion === 'liquidar' ? 'Se marcará como pagada completa: ' : 'Se guardará: ')
            + monto + ' · ' + (pideCamisa ? 'sí pide camisa' : 'no pide camisa') + '.';

        formularioPendiente = formulario;
        accionPendiente = accion;
        dialogo.showModal();
    });

    botonConfirmar.addEventListener('click', function () {
        if (!formularioPendiente) {
            return;
        }
        // form.submit() no incluye el botón que se "clicó" (aquí ninguno se
        // clicó de verdad) — se agrega la acción elegida como campo oculto
        // para que guardar-pago.php la reciba igual que si se hubiera
        // enviado con el botón original.
        var inputAccion = document.createElement('input');
        inputAccion.type = 'hidden';
        inputAccion.name = 'accion';
        inputAccion.value = accionPendiente;
        formularioPendiente.appendChild(inputAccion);
        formularioPendiente.dataset.confirmado = '1';

        var formulario = formularioPendiente;
        dialogo.close();
        formulario.submit();
    });

    // Cualquier forma de cerrar el <dialog> (Cancelar, backdrop, Esc) dispara
    // 'close' — se libera el formulario pendiente ahí para no dejar el modal
    // apuntando a uno viejo si se reabre con otra tarjeta.
    dialogo.addEventListener('close', function () {
        formularioPendiente = null;
        accionPendiente = null;
    });

    document.addEventListener('click', function (evento) {
        var disparador = evento.target.closest('[data-cerrar-modal]');
        if (disparador) {
            var objetivo = document.getElementById(disparador.dataset.cerrarModal);
            if (objetivo instanceof HTMLDialogElement) {
                objetivo.close();
            }
            return;
        }
        if (evento.target instanceof HTMLDialogElement) {
            evento.target.close();
        }
    });
})();
