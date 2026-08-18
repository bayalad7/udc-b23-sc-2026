(function () {
    'use strict';

    var app = document.getElementById('escaneo-app');
    if (!app || typeof jsQR === 'undefined') {
        return;
    }

    // El día/evento no se manda desde el cliente: el backend lo toma de la
    // sesión del turno (ver includes/sesion.php), así no se puede falsear.
    // El endpoint sí varía según la pantalla (asistencia general vs. a un
    // evento específico) — ambas pantallas lo fijan en data-endpoint de
    // #escaneo-app (con BASE_URL ya incluido, ver config/rutas.php).
    var ENDPOINT = app.dataset.endpoint;
    var RETRASO_REANUDAR_MS = 4000;

    var video = document.getElementById('video-camara');
    var canvas = document.getElementById('canvas-camara');
    var contexto = canvas.getContext('2d', { willReadFrequently: true });
    var marco = document.getElementById('marco-escaneo');
    var estadoTexto = document.getElementById('estado-escaneo');
    var avisoSinCamara = document.getElementById('sin-camara');
    var contenedorResultado = document.getElementById('resultado');
    var plantillaResultado = document.getElementById('plantilla-resultado');

    var ESTILOS_RESULTADO = {
        entrada: { fondo: 'bg-emerald-600', icono: 'entrada' },
        salida: { fondo: 'bg-blue-600', icono: 'salida' },
        sin_registro: { fondo: 'bg-slate-700', icono: 'verificado' },
        sin_inscripcion: { fondo: 'bg-amber-600', icono: 'alerta' },
        error: { fondo: 'bg-red-600', icono: 'alerta' }
    };

    var MENSAJES_ERROR = {
        no_encontrado: 'No se encontró ningún registro con ese código.',
        codigo_invalido: 'El código QR leído no tiene un formato válido.',
        no_autorizado: 'Tu turno expiró. Vuelve a iniciar sesión.',
        error_servidor: 'Ocurrió un error guardando el registro. Intenta de nuevo.',
        peticion_invalida: 'Ocurrió un error inesperado. Intenta de nuevo.',
        red: 'No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'
    };

    var escaneando = true;
    var temporizadorReanudar = null;

    var TRAZOS_ICONOS = {
        entrada: '<path d="m10 17 5-5-5-5"/><path d="M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        salida: '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
        verificado: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        alerta: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>'
    };

    // Set mínimo de íconos en línea para las 4 tarjetas de resultado (no
    // reutiliza app/asistencias/includes/iconos.php porque ese vive en PHP,
    // no en el navegador).
    function iconoSvg(nombreClase, nombreIcono) {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' + nombreClase + '" aria-hidden="true">'
            + (TRAZOS_ICONOS[nombreIcono] || '') + '</svg>';
    }

    function iniciarCamara() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            mostrarSinCamara();
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                video.srcObject = stream;
                video.setAttribute('playsinline', 'true');
                video.play();
                requestAnimationFrame(cicloEscaneo);
            })
            .catch(function () {
                mostrarSinCamara();
            });
    }

    function mostrarSinCamara() {
        avisoSinCamara.classList.remove('hidden');
        avisoSinCamara.classList.add('flex');
        estadoTexto.classList.add('hidden');
        marco.classList.add('hidden');
    }

    function cicloEscaneo() {
        if (escaneando && video.readyState === video.HAVE_ENOUGH_DATA) {
            if (canvas.width !== video.videoWidth) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
            }

            contexto.drawImage(video, 0, 0, canvas.width, canvas.height);
            var datosImagen = contexto.getImageData(0, 0, canvas.width, canvas.height);
            var codigo = jsQR(datosImagen.data, datosImagen.width, datosImagen.height, {
                inversionAttempts: 'dontInvert'
            });

            if (codigo && codigo.data) {
                procesarCodigo(codigo.data);
                return;
            }
        }

        requestAnimationFrame(cicloEscaneo);
    }

    function procesarCodigo(codigo) {
        escaneando = false;
        estadoTexto.textContent = 'Verificando...';

        fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo: codigo })
        })
            .then(function (respuesta) {
                return respuesta.json().catch(function () {
                    return { ok: false, error: 'peticion_invalida' };
                });
            })
            .then(mostrarResultado)
            .catch(function () {
                mostrarResultado({ ok: false, error: 'red' });
            });
    }

    function mostrarResultado(datos) {
        estadoTexto.textContent = 'Listo — revisa el resultado abajo';

        var clon = plantillaResultado.content.cloneNode(true);
        var elementoEtiqueta = clon.querySelector('[data-rol="etiqueta"]');
        var elementoFoto = clon.querySelector('[data-rol="foto"]');
        var elementoNombre = clon.querySelector('[data-rol="nombre"]');
        var elementoDetalle = clon.querySelector('[data-rol="detalle"]');
        var elementoMensaje = clon.querySelector('[data-rol="mensaje"]');
        var botonInscripcion = clon.querySelector('[data-rol="boton-inscripcion"]');
        var botonSiguiente = clon.querySelector('[data-rol="boton-siguiente"]');

        var tipoResultado = datos.ok ? datos.tipo_resultado : 'error';
        var estilo = ESTILOS_RESULTADO[tipoResultado] || ESTILOS_RESULTADO.error;

        contenedorResultado.className = 'mt-4 flex flex-col items-center gap-3 rounded-2xl p-5 text-center ' + estilo.fondo;

        var etiquetas = {
            entrada: 'Entrada',
            salida: 'Salida (actualizada)',
            sin_registro: 'Sin registro',
            sin_inscripcion: 'Sin inscripción',
            error: 'No se pudo registrar'
        };
        elementoEtiqueta.innerHTML = iconoSvg('h-3.5 w-3.5 shrink-0', estilo.icono) + '<span>' + etiquetas[tipoResultado] + (datos.ok ? ' · ' + datos.hora : '') + '</span>';
        elementoEtiqueta.className = 'flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1 text-xs font-bold uppercase tracking-wide';

        if (datos.ok) {
            elementoNombre.textContent = datos.persona.nombre;
            elementoDetalle.textContent = datos.persona.detalle;
            elementoMensaje.textContent = datos.mensaje;

            if (datos.persona.foto_url) {
                elementoFoto.src = datos.persona.foto_url;
                elementoFoto.alt = datos.persona.nombre;
                elementoFoto.classList.remove('hidden');
            }

            if (datos.redirect_url) {
                botonInscripcion.href = datos.redirect_url;
                botonInscripcion.target = '_blank';
                botonInscripcion.rel = 'noopener';
                botonInscripcion.classList.remove('hidden');
                botonInscripcion.classList.add('flex');
                elementoMensaje.textContent += ' — aún no tiene ponencia/taller asignado';
            } else if (datos.asignaciones && datos.asignaciones.length) {
                var resumen = datos.asignaciones.map(function (a) { return a.nombre + ' (' + a.espacio + ')'; }).join(' · ');
                elementoDetalle.textContent += ' — ' + resumen;
            }
        } else {
            elementoNombre.textContent = '';
            elementoDetalle.textContent = '';
            elementoMensaje.textContent = MENSAJES_ERROR[datos.error] || MENSAJES_ERROR.peticion_invalida;
        }

        botonSiguiente.addEventListener('click', reanudarEscaneo);

        contenedorResultado.innerHTML = '';
        contenedorResultado.appendChild(clon);
        contenedorResultado.classList.remove('hidden');

        if (temporizadorReanudar) {
            clearTimeout(temporizadorReanudar);
        }
        temporizadorReanudar = setTimeout(reanudarEscaneo, RETRASO_REANUDAR_MS);
    }

    function reanudarEscaneo() {
        if (temporizadorReanudar) {
            clearTimeout(temporizadorReanudar);
            temporizadorReanudar = null;
        }
        contenedorResultado.className = 'hidden mt-4 flex-col items-center gap-3 rounded-2xl p-5 text-center';
        contenedorResultado.innerHTML = '';
        estadoTexto.textContent = 'Apunta la cámara al código QR';
        escaneando = true;
        requestAnimationFrame(cicloEscaneo);
    }

    iniciarCamara();
})();
