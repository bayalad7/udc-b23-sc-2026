# B23 - Semana Cultural Aniversario

Planificación de la Semana Cultural del Aniversario del Bachillerato 23, Universidad de Colima.

Este archivo es la fuente de verdad para retomar el trabajo (estado, convenciones, decisiones). Para una vista general orientada a humanos/GitHub, ver [README.md](README.md).

## Repositorio

- Remoto: `https://github.com/bayalad7/udc-b23-sc-2026.git` (rama `main`).
- `config/db.php` y cualquier archivo con credenciales reales (SMTP, base de datos) **no se suben** al repositorio — ver `.gitignore` y la nota de instalación en `app/PROMPTS-DESARROLLO.md`.

## Fechas del evento

| Día | Fecha | Enfoque |
|---|---|---|
| 1 | Jueves 1 de Octubre | Día Académico |
| 2 | Viernes 2 de Octubre | Día Cultural |
| 3 | Sábado 3 de Octubre | Día Deportivo |

## Estructura de carpetas

```
00-Informacion-General/        Datos que aplican a los tres días (espacios, contactos, presupuesto)
01-Dia-Academico-Jueves-01-Oct/    Itinerario y logística del Día Académico
02-Dia-Cultural-Viernes-02-Oct/    Itinerario y logística del Día Cultural
03-Dia-Deportivo-Sabado-03-Oct/    Itinerario y logística del Día Deportivo
app/                            Sistema de registro e inscripciones (PHP puro + JS puro + MariaDB)
```

Cada carpeta de día contendrá su propio `itinerario-matriz.md` (matriz de tiempo × aulas) y los documentos de logística que se vayan necesitando (listas de invitados, materiales, responsables, etc.).

`app/` contiene el sistema que soporta el registro de asistencia por QR (ver `01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md`): `app/registro/` (pre-registro y credencial digital), `app/inscripciones/` (selección de taller/ponencia con cupo) y `app/torneos/` (inscripción de equipos del Día Deportivo). El escaneo QR y el control de entrada/salida NO viven dentro de esos módulos: es una app aparte, `app/asistencias/`, unificada para **los 3 días** del evento (Día Académico, Día Cultural y Día Deportivo — ver [Control de entrada y salida](01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md#control-de-entrada-y-salida)): el primer escaneo del día registra la entrada, cualquier escaneo posterior solo sobreescribe la salida con la hora más reciente. Al ser la app más sensible del sistema (nunca debe caer en manos del alumnado), pide una contraseña compartida (guardada hasheada en la tabla `sistema`) antes de dejar hacer nada — ver "Decisiones técnicas resueltas" en `app/PROMPTS-DESARROLLO.md`. El plan de construcción está en `app/PROMPTS-DESARROLLO.md`, como una lista de prompts secuenciales — es la referencia para saber qué sigue al retomar el desarrollo.

## Estado actual

- [x] Estructura de carpetas creada
- [x] Espacios y capacidades registrados (`00-Informacion-General/espacios-y-capacidades.md`)
- [ ] Matriz de itinerario Día Académico — todos los bloques 08:00–13:00 tienen actividad asignada (`01-Dia-Academico-Jueves-01-Oct/itinerario-matriz.md`); pendientes de detalle: ponentes/responsables de las 4 ponencias (09:00–10:00), catálogo de talleres y confirmación del concurso del conocimiento (10:30–12:30), punto de desayuno (10:00–10:30) y logística del pastel de clausura (12:30–13:00).
- [x] Proceso de registro de asistencia por QR documentado (`01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md`), incluyendo el formulario de pre-registro y el control de hora de entrada/salida (primer escaneo = entrada, escaneos posteriores solo actualizan la salida); pendientes: ubicación del punto de control, si el escaneo de salida es obligatorio, librería de QR, método de envío de correo.
- [ ] Sistema de registro e inscripciones — stack, librerías, hosting y flujo de asignación de talleres ya definidos; roadmap en `app/PROMPTS-DESARROLLO.md`. Avance real: esquema completo de base de datos escrito en `app/database/schema.sql` — `alumnos`; `eventos`/`inscripciones` (ponencias y talleres individuales por alumno, con su propio control de entrada/salida a cada evento, sin restricción de horario); `equipos`/`integrantes` (todo lo que se organiza por equipo: Concurso del Conocimiento, Concurso de Talentos y los 3 torneos deportivos — mezclan alumnos con padres/madres de familia, y aquí sí se lleva su entrada/salida); `asistencias_generales` (¿ya entró/salió del plantel ese día? — solo alumnos, separado de "a qué asiste"). **Pendiente un error de sintaxis SQL en `equipos`** (`FOREIGN KEY (id_alumno)` debería decir `id_alumno_capitan`) que bloquea crear el esquema tal cual — ver "Pendientes menores" en `app/PROMPTS-DESARROLLO.md`. `app/registro/` construido (formulario de pre-registro, guardado, generación de credencial digital con QR, recuperación de credencial) — pendiente el envío automático por correo (Prompt 6). El escaneo QR de asistencia se unificó en un módulo propio `app/asistencias/` compartido por los 3 días en vez de duplicarlo por día, protegido con una contraseña compartida guardada en la tabla `sistema` para que el alumnado no pueda usarlo — `app/asistencias/` construido (contraseña de acceso, selección de día/operador/punto de control, escaneo con cámara + jsQR); `app/inscripciones/` y `app/torneos/` sin empezar.
- [ ] Itinerario Día Cultural — pendiente; recordar que el control de entrada/salida por QR (ver arriba) también debe aplicarse aquí cuando se diseñe, ya que es un requisito de los 3 días.
- [ ] Itinerario Día Deportivo — sede Polideportivo de San Pedrito (fuera del plantel); jornada fija 07:00–12:00 (registro QR 07:00–07:30, torneos 07:30–11:30, clausura 11:30–12:00 con entrega de premios por el Director) definida (`03-Dia-Deportivo-Sabado-03-Oct/itinerario-matriz.md` y `torneos-deportivos.md`); roadmap de construcción del módulo de inscripción de equipos y control de asistencia (`app/torneos/`) agregado como Prompts 12–18 en `app/PROMPTS-DESARROLLO.md`, construcción sin empezar; pendiente: tope de equipos por torneo para que las rondas quepan en la ventana fija de 4 horas, franjas por ronda, logística de traslado y permisos del Polideportivo, proporción mínima de padres por equipo, reglas de desempate de Voleibol y Quemados, y premios definitivos.

## Convenciones

- **Formato de fecha**: día completo en español, ej. "Jueves 1 de Octubre".
- **Formato de hora**: 24 horas, ej. `08:00 – 09:00`.
- **Matrices de itinerario**: tabla Markdown con la hora en la primera columna y cada aula/espacio como columna siguiente. Los recesos generales se marcan en una fila que abarca todos los espacios.
- **Nombres de espacio**: usar siempre el mismo nombre que en `espacios-y-capacidades.md` para poder cruzar información entre documentos.
- Los documentos de cada día son independientes entre sí; no se asume que los espacios o el horario de un día se repitan en otro salvo que se indique explícitamente.

## Cómo continuar el trabajo

Al retomar este proyecto, revisar primero la sección "Estado actual" de este archivo para saber qué día/documento sigue. Actualizar esa lista conforme se completen documentos.
