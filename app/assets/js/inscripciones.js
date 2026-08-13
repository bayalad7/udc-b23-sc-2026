// Abre el <dialog> (modal nativo) referenciado por data-abrir-modal="id" —
// se usa tanto para el modal de "inscritos" como el de confirmación de
// inscripción (ver academico.php). Delegado en document para no tener que
// engancharse botón por botón.
document.addEventListener('click', function (evento) {
    var disparador = evento.target.closest('[data-abrir-modal]');
    if (!disparador) {
        return;
    }
    var dialogo = document.getElementById(disparador.dataset.abrirModal);
    if (dialogo instanceof HTMLDialogElement) {
        dialogo.showModal();
    }
});

// Clic en el backdrop (fuera del contenido) cierra el modal — <dialog> no lo
// trae de fábrica. Un clic dentro del contenido nunca llega aquí como
// target=dialog porque el contenido está envuelto en su propio <div>.
document.addEventListener('click', function (evento) {
    if (evento.target instanceof HTMLDialogElement) {
        evento.target.close();
    }
});
