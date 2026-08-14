(function () {
    'use strict';

    var form = document.getElementById('form-registro');
    if (!form) {
        return;
    }

    var mensaje = document.getElementById('mensaje-validacion');
    var TAMANO_MAXIMO_FOTO = 5 * 1024 * 1024; // 5 MB
    var TIPOS_FOTO_PERMITIDOS = ['image/jpeg', 'image/png'];

    var campoCuenta = document.getElementById('numero_cuenta');
    campoCuenta.addEventListener('input', function () {
        campoCuenta.value = campoCuenta.value.toUpperCase();
    });

    function mostrarError(texto) {
        mensaje.textContent = texto;
        mensaje.classList.remove('hidden');
    }

    function ocultarError() {
        mensaje.classList.add('hidden');
        mensaje.textContent = '';
    }

    form.addEventListener('submit', function (evento) {
        ocultarError();

        var nombre = form.nombre_completo.value.trim();
        var cuenta = form.numero_cuenta.value.trim();
        var grado = form.grado.value.trim();
        var grupo = form.grupo.value.trim();
        var correo = form.correo_institucional.value.trim();
        var camisaCorte = form.camisa_corte.value.trim();
        var camisaTalla = form.camisa_talla.value.trim();
        var foto = form.foto.files[0];

        if (!nombre || !grado || !grupo || !correo || !camisaCorte || !camisaTalla) {
            evento.preventDefault();
            mostrarError('Completa todos los campos obligatorios.');
            return;
        }

        if (!/^[A-Z0-9]{8}$/.test(cuenta)) {
            evento.preventDefault();
            mostrarError('El número de cuenta debe tener exactamente 8 caracteres (letras y/o números).');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            evento.preventDefault();
            mostrarError('Escribe un correo institucional válido.');
            return;
        }

        if (!foto) {
            evento.preventDefault();
            mostrarError('Adjunta tu fotografía tipo carnet.');
            return;
        }

        if (TIPOS_FOTO_PERMITIDOS.indexOf(foto.type) === -1) {
            evento.preventDefault();
            mostrarError('La fotografía debe ser JPG o PNG.');
            return;
        }

        if (foto.size > TAMANO_MAXIMO_FOTO) {
            evento.preventDefault();
            mostrarError('La fotografía no debe pesar más de 5 MB.');
            return;
        }
    });
})();
