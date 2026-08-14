// Abre el <dialog> (modal nativo) referenciado por data-abrir-modal="id" —
// mismo patrón que app/assets/js/inscripciones.js, vendorizado aparte aquí
// porque app/admin no comparte el resto de la lógica de ese módulo
// (constructor de equipos, búsqueda de alumno, etc.), solo el modal.
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

// Clic en el backdrop cierra el modal — <dialog> no lo trae de fábrica.
document.addEventListener('click', function (evento) {
    if (evento.target instanceof HTMLDialogElement) {
        evento.target.close();
    }
});

document.addEventListener('click', function (evento) {
    var disparador = evento.target.closest('[data-cerrar-modal]');
    if (!disparador) {
        return;
    }
    var dialogo = document.getElementById(disparador.dataset.cerrarModal);
    if (dialogo instanceof HTMLDialogElement) {
        dialogo.close();
    }
});

// Lightbox de foto de alumno: cualquier elemento con data-foto-lightbox
// (la URL de la foto) reusa el mismo <dialog id="foto-lightbox"> — ver
// includes/layout.php — en vez de uno por fila, para no inflar el DOM en
// listados de decenas de alumnos.
document.addEventListener('click', function (evento) {
    var disparador = evento.target.closest('[data-foto-lightbox]');
    if (!disparador) {
        return;
    }
    var dialogo = document.getElementById('foto-lightbox');
    var img = document.getElementById('foto-lightbox-img');
    if (dialogo instanceof HTMLDialogElement && img) {
        img.src = disparador.dataset.fotoLightbox;
        img.alt = disparador.dataset.fotoAlt || '';
        dialogo.showModal();
    }
});
