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

// Botón "Cancelar" dentro de los formularios de equipo (academico.php,
// cultural.php, deportivo.php): no puede ser <form method="dialog"> como en
// los modales de confirmación simples, porque ya está dentro de un <form>
// más grande que se envía por POST (un <form> no puede anidar otro) — se
// cierra el <dialog> por JavaScript en su lugar.
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

// --- Constructor de equipos (academico.php, cultural.php, deportivo.php) --
// Reemplaza la captura manual de números de cuenta por un buscador exacto
// (GET a includes/buscar-alumno.php) + una grilla de integrantes ya
// validados. Cada <form> con [data-equipo-builder] describe su propio
// contexto vía data-* — ver el HTML de cada página para el significado de
// cada atributo:
//   data-contexto            "conocimiento" | "talentos" | "deportivo"
//   data-id-competicion      solo deportivo (qué torneo)
//   data-max-integrantes     tope de acompañantes/integrantes a agregar
//   data-requiere-exactos    "true" si el envío exige llegar exacto al tope
//   data-capitan-cuenta      número de cuenta de quien arma el equipo
//
// El backend (crear-equipo-*.php) siempre vuelve a validar todo — esto es
// solo para que la persona no descubra un error hasta después de escribir 9
// números de cuenta a mano.

var MOTIVOS_BUSQUEDA_ALUMNO = {
    no_encontrado: 'No se encontró ningún alumno con ese número de cuenta.',
    cruce_horario: 'Ya tiene otro evento inscrito en el horario del concurso (10:30–12:30).',
    parametros_invalidos: 'Número de cuenta inválido.',
    no_identificado: 'Tu sesión expiró — recarga la página y vuelve a identificarte.'
};

function motivoYaEnEquipo(contexto) {
    return contexto === 'conocimiento'
        ? 'Ya pertenece a otro equipo del Concurso del Conocimiento.'
        : 'Ya pertenece a otro equipo de este mismo torneo.';
}

function limpiarNodo(nodo) {
    while (nodo.firstChild) {
        nodo.removeChild(nodo.firstChild);
    }
}

function crearSpanTexto(texto, clase) {
    var span = document.createElement('span');
    if (clase) {
        span.className = clase;
    }
    span.textContent = texto;
    return span;
}

function inicializarConstructorEquipo(builder) {
    var contexto = builder.dataset.contexto;
    var idCompeticion = builder.dataset.idCompeticion || '';
    var maximo = parseInt(builder.dataset.maxIntegrantes || '9', 10);
    var requiereExactos = builder.dataset.requiereExactos === 'true';
    var capitanCuenta = (builder.dataset.capitanCuenta || '').toUpperCase();

    var inputBusqueda = builder.querySelector('[data-buscar-cuenta]');
    var botonBuscar = builder.querySelector('[data-buscar-boton]');
    var panelResultado = builder.querySelector('[data-resultado-busqueda]');
    var grid = builder.querySelector('[data-grid-integrantes]');
    var contador = builder.querySelector('[data-contador-integrantes]');
    var camposOcultos = builder.querySelector('[data-campos-ocultos]');
    var errorGeneral = builder.querySelector('[data-error-integrantes]');
    var formulario = builder.closest('form');
    var botonSubmit = formulario ? formulario.querySelector('[data-equipo-submit]') : null;

    if (!inputBusqueda || !botonBuscar || !panelResultado || !grid) {
        return;
    }

    var agregados = [];

    function claveDe(item) {
        return contexto === 'deportivo' ? item.numero_cuenta + '|' + item.tipo : item.numero_cuenta;
    }

    function actualizarEstado() {
        if (contador) {
            contador.textContent = agregados.length + '/' + maximo;
        }
        var lleno = agregados.length >= maximo;
        inputBusqueda.disabled = lleno;
        botonBuscar.disabled = lleno;
        if (botonSubmit) {
            botonSubmit.disabled = requiereExactos && agregados.length !== maximo;
        }
    }

    function crearOculto(nombre, valor) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = nombre;
        input.value = valor;
        return input;
    }

    function renderizarCamposOcultos() {
        if (!camposOcultos) {
            return;
        }
        limpiarNodo(camposOcultos);
        agregados.forEach(function (item) {
            if (contexto === 'deportivo') {
                camposOcultos.appendChild(crearOculto('integrantes_tipo[]', item.tipo));
                camposOcultos.appendChild(crearOculto('integrantes_cuenta[]', item.numero_cuenta));
                camposOcultos.appendChild(crearOculto('integrantes_nombre[]', item.tipo === 'alumno' ? '' : item.nombre));
            } else {
                camposOcultos.appendChild(crearOculto('integrantes[]', item.numero_cuenta));
            }
        });
    }

    function crearIconoQuitar() {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '3');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('class', 'h-3 w-3');
        svg.setAttribute('aria-hidden', 'true');
        var trazoA = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        trazoA.setAttribute('d', 'M18 6 6 18');
        var trazoB = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        trazoB.setAttribute('d', 'm6 6 12 12');
        svg.appendChild(trazoA);
        svg.appendChild(trazoB);
        return svg;
    }

    function renderizarGrid() {
        limpiarNodo(grid);
        agregados.forEach(function (item, indice) {
            var card = document.createElement('div');
            card.className = 'relative flex flex-col items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-2 pt-4 pb-2 text-center shadow-sm';

            var quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700 active:bg-red-800';
            quitar.setAttribute('aria-label', 'Quitar integrante');
            quitar.title = 'Quitar';
            quitar.appendChild(crearIconoQuitar());
            quitar.addEventListener('click', function () {
                agregados.splice(indice, 1);
                renderizarTodo();
            });
            card.appendChild(quitar);

            if (item.foto_url) {
                var img = document.createElement('img');
                img.src = item.foto_url;
                img.alt = '';
                img.className = 'h-9 w-9 shrink-0 rounded-full border border-slate-200 object-cover';
                card.appendChild(img);
            } else {
                var etiquetaSinFoto = item.tipo === 'padre' ? 'Padre' : (item.tipo === 'madre' ? 'Madre' : 'Sin foto');
                var placeholder = document.createElement('span');
                placeholder.className = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-center text-[9px] leading-tight text-slate-500';
                placeholder.textContent = etiquetaSinFoto;
                card.appendChild(placeholder);
            }

            var nombreEl = crearSpanTexto(item.nombre, 'mt-1 line-clamp-2 w-full text-[11px] font-semibold leading-tight text-slate-800');
            card.appendChild(nombreEl);

            var detalle = item.numero_cuenta + (item.grado ? ' · ' + item.grado + '°' + item.grupo : '');
            card.appendChild(crearSpanTexto(detalle, 'text-[10px] leading-tight text-slate-500'));

            if (contexto === 'deportivo' && item.tipo !== 'alumno') {
                card.appendChild(crearSpanTexto(item.tipo === 'padre' ? 'Padre' : 'Madre', 'mt-1 rounded-full bg-slate-900 px-1.5 py-0.5 text-[9px] font-semibold text-white'));
            }

            grid.appendChild(card);
        });
    }

    function renderizarTodo() {
        renderizarGrid();
        renderizarCamposOcultos();
        actualizarEstado();
    }

    function ocultarPanel() {
        panelResultado.hidden = true;
        limpiarNodo(panelResultado);
    }

    function mostrarErrorBusqueda(mensaje) {
        limpiarNodo(panelResultado);
        panelResultado.hidden = false;
        panelResultado.className = 'mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700';
        panelResultado.textContent = mensaje;
    }

    function mostrarResultado(datos, cuenta) {
        limpiarNodo(panelResultado);
        panelResultado.hidden = false;

        if (!datos.encontrado) {
            panelResultado.className = 'mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700';
            panelResultado.textContent = MOTIVOS_BUSQUEDA_ALUMNO[datos.motivo] || MOTIVOS_BUSQUEDA_ALUMNO.no_encontrado;
            return;
        }

        panelResultado.className = 'mb-3 overflow-hidden rounded-lg border border-slate-200 bg-white';

        var alumnoDatos = datos.alumno;

        // --- Fila superior: foto + datos del alumno encontrado, sobre fondo
        // gris para distinguirla claramente del resto del panel (selección
        // de rol + botón de agregar).
        var fila = document.createElement('div');
        fila.className = 'flex items-center gap-3 border-b border-slate-200 bg-slate-50 p-3';
        if (alumnoDatos.foto_url) {
            var img = document.createElement('img');
            img.src = alumnoDatos.foto_url;
            img.alt = '';
            img.className = 'h-11 w-11 shrink-0 rounded-full border border-slate-200 object-cover';
            fila.appendChild(img);
        }
        var info = document.createElement('div');
        info.className = 'min-w-0 text-xs';
        info.appendChild(crearSpanTexto(alumnoDatos.nombre_completo, 'block truncate font-semibold text-slate-800'));
        info.appendChild(crearSpanTexto(alumnoDatos.numero_cuenta + ' · ' + alumnoDatos.grado + '°' + alumnoDatos.grupo, 'block text-slate-500'));
        fila.appendChild(info);
        panelResultado.appendChild(fila);

        // --- Resto del panel: selección de rol (solo deportivo), aviso de
        // validación y el botón para confirmar — con su propio padding para
        // no pegarse a la fila de arriba.
        var cuerpo = document.createElement('div');
        cuerpo.className = 'p-3';
        panelResultado.appendChild(cuerpo);

        var avisoEl = document.createElement('p');
        avisoEl.className = 'mb-2 text-xs font-medium text-red-600';
        avisoEl.hidden = true;

        var tipoSeleccionado = 'alumno';
        var nombreFamiliarInput = null;

        function actualizarAviso() {
            if (tipoSeleccionado === 'alumno' && cuenta === capitanCuenta) {
                avisoEl.textContent = 'Ese eres tú — ya estás en el equipo como capitán.';
                avisoEl.hidden = false;
            } else if (tipoSeleccionado === 'alumno' && !datos.puede_ser_alumno) {
                avisoEl.textContent = MOTIVOS_BUSQUEDA_ALUMNO[datos.motivo] || motivoYaEnEquipo(contexto);
                avisoEl.hidden = false;
            } else {
                avisoEl.hidden = true;
            }
        }

        if (contexto === 'deportivo') {
            var etiquetaRol = document.createElement('span');
            etiquetaRol.className = 'mb-1.5 block text-xs font-medium text-slate-600';
            etiquetaRol.textContent = '¿Quién participa?';
            cuerpo.appendChild(etiquetaRol);

            var grupoTipo = document.createElement('div');
            grupoTipo.className = 'mb-2 grid grid-cols-3 gap-1.5';
            var etiquetasTipo = { alumno: 'El alumno', padre: 'Su papá', madre: 'Su mamá' };
            ['alumno', 'padre', 'madre'].forEach(function (valor) {
                var etiqueta = document.createElement('label');
                etiqueta.className = 'flex cursor-pointer items-center justify-center rounded-lg border border-slate-300 px-2 py-1.5 text-[11px] font-medium text-slate-600 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white';
                var radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'tipo_busqueda_temp';
                radio.value = valor;
                radio.checked = valor === 'alumno';
                radio.className = 'sr-only';
                radio.addEventListener('change', function () {
                    tipoSeleccionado = valor;
                    if (nombreFamiliarInput) {
                        nombreFamiliarInput.hidden = valor === 'alumno';
                    }
                    actualizarAviso();
                });
                etiqueta.appendChild(radio);
                etiqueta.appendChild(document.createTextNode(etiquetasTipo[valor]));
                grupoTipo.appendChild(etiqueta);
            });
            cuerpo.appendChild(grupoTipo);

            nombreFamiliarInput = document.createElement('input');
            nombreFamiliarInput.type = 'text';
            nombreFamiliarInput.placeholder = 'Nombre completo del padre/madre';
            nombreFamiliarInput.maxLength = 150;
            nombreFamiliarInput.hidden = true;
            nombreFamiliarInput.className = 'mb-2 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs focus:border-slate-500 focus:outline-none';
            cuerpo.appendChild(nombreFamiliarInput);
        }

        cuerpo.appendChild(avisoEl);
        actualizarAviso();

        var botonAgregar = document.createElement('button');
        botonAgregar.type = 'button';
        botonAgregar.className = 'flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white active:bg-slate-700';
        botonAgregar.textContent = 'Agregar al equipo';
        botonAgregar.addEventListener('click', function () {
            if (tipoSeleccionado === 'alumno' && (cuenta === capitanCuenta || !datos.puede_ser_alumno)) {
                actualizarAviso();
                return;
            }
            var nombreFinal = tipoSeleccionado === 'alumno'
                ? alumnoDatos.nombre_completo
                : (nombreFamiliarInput ? nombreFamiliarInput.value.trim() : '');
            if (tipoSeleccionado !== 'alumno' && nombreFinal === '') {
                avisoEl.textContent = 'Captura el nombre completo del padre/madre.';
                avisoEl.hidden = false;
                return;
            }
            if (agregados.length >= maximo) {
                return;
            }
            var item = {
                numero_cuenta: alumnoDatos.numero_cuenta,
                tipo: tipoSeleccionado,
                nombre: nombreFinal,
                foto_url: tipoSeleccionado === 'alumno' ? alumnoDatos.foto_url : '',
                grado: alumnoDatos.grado,
                grupo: alumnoDatos.grupo
            };
            var clave = claveDe(item);
            if (agregados.some(function (existente) { return claveDe(existente) === clave; })) {
                avisoEl.textContent = 'Ya agregaste a esa persona con ese mismo rol.';
                avisoEl.hidden = false;
                return;
            }
            agregados.push(item);
            renderizarTodo();
            ocultarPanel();
            inputBusqueda.value = '';
            inputBusqueda.focus();
        });
        cuerpo.appendChild(botonAgregar);
    }

    function ejecutarBusqueda() {
        var cuenta = inputBusqueda.value.trim().toUpperCase();
        if (!/^[A-Z0-9]{8}$/.test(cuenta)) {
            mostrarErrorBusqueda('Número de cuenta inválido (8 caracteres).');
            return;
        }
        if (contexto !== 'deportivo') {
            if (cuenta === capitanCuenta) {
                mostrarErrorBusqueda('Ese eres tú — ya estás en el equipo como capitán.');
                return;
            }
            if (agregados.some(function (item) { return item.numero_cuenta === cuenta; })) {
                mostrarErrorBusqueda('Ya agregaste a esa persona.');
                return;
            }
        }

        panelResultado.hidden = false;
        limpiarNodo(panelResultado);
        panelResultado.className = 'mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500';
        panelResultado.textContent = 'Buscando…';

        var url = '/inscripciones/includes/buscar-alumno.php?numero_cuenta=' + encodeURIComponent(cuenta) +
            '&contexto=' + encodeURIComponent(contexto) +
            (idCompeticion ? '&id_competicion=' + encodeURIComponent(idCompeticion) : '');

        fetch(url)
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) { mostrarResultado(datos, cuenta); })
            .catch(function () { mostrarErrorBusqueda('Error al buscar. Intenta de nuevo.'); });
    }

    botonBuscar.addEventListener('click', ejecutarBusqueda);
    inputBusqueda.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            ejecutarBusqueda();
        }
    });

    if (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (requiereExactos && agregados.length !== maximo) {
                evento.preventDefault();
                if (errorGeneral) {
                    errorGeneral.textContent = 'Debes completar los ' + maximo + ' integrantes antes de guardar.';
                    errorGeneral.hidden = false;
                }
            } else if (errorGeneral) {
                errorGeneral.hidden = true;
            }
        });
    }

    actualizarEstado();
}

document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-equipo-builder]'), inicializarConstructorEquipo);
});
