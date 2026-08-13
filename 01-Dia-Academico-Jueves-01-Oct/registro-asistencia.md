# Registro de Asistencia — Día Académico

Proceso de control de acceso y registro a talleres/ponencias mediante credencial digital (QR). El escaneo de **entrada** ocupa el bloque **08:00–08:30** de la [matriz de itinerario](itinerario-matriz.md); el escaneo de **salida** puede ocurrir en cualquier momento posterior del evento (ver [Control de entrada y salida](#control-de-entrada-y-salida)).

## Flujo del proceso

1. **Pre-registro (antes del evento)**: el alumno llena un formulario de registro en la nube con sus datos y foto.
2. **Credencial digital**: a partir del pre-registro se genera una credencial (imagen) que el alumno guarda/muestra desde su celular, con su foto, datos generales y un código QR.
3. **Día del evento — escaneo de entrada (08:00–08:30)**: el escaneo lo opera el **maestro/staff** en el punto de control (no es autoservicio del alumno). El alumno presenta la credencial, el maestro escanea el QR con la app y el sistema marca su hora de entrada.
4. **Asignación de ponencia/taller — dos vías posibles** (se dispara únicamente en el escaneo de entrada, el primero del día — ver [Control de entrada y salida](#control-de-entrada-y-salida)), respetando la exclusividad por franja horaria de la sección [Reglas de inscripción por franja horaria](#reglas-de-inscripción-por-franja-horaria):
   - **Registro previo**: si el alumno ya eligió su ponencia/taller de antemano con el encargado de organizar el evento académico (durante el periodo de clases, antes del 1 de octubre), el sistema ya tiene su lugar reservado — no vuelve a elegir el día del evento.
   - **Por orden de llegada (el día del evento)**: si no tiene un lugar reservado previamente, justo después del escaneo elige de inmediato, con cupo limitado y **por orden de llegada** (el que se registra primero tiene más opciones disponibles): la ponencia de las 9:00–10:00 y los talleres de las Sesión 1 y 2 (10:30–12:30).
   - El **Concurso del Conocimiento** (Auditorio Principal, 10:30–12:30) queda **fuera de este mecanismo**: sus 12 equipos se organizan aparte, no por elección libre con cupo el día del evento. Pero sí cuenta para la exclusividad de la franja 10:30–12:30: un alumno que ya es integrante de un equipo del concurso no puede además tomar un taller en ese mismo horario, y viceversa.
5. **Escaneo(s) de salida**: cualquier escaneo posterior al primero del día se registra como salida (ver detalle abajo). No vuelve a disparar la asignación de ponencia/taller del punto 4.

## Reglas de inscripción por franja horaria

El catálogo del Día Académico (ver semillas en [app/database/seeds.sql](../app/database/seeds.sql)) tiene dos franjas con actividades simultáneas, y en ambas el alumno elige **una sola opción**, nunca más de una:

- **09:00–10:00** (4 ponencias: Auditorio Principal, Aula 1, Aula 2, Aula 3): un alumno solo puede inscribirse a **una** de las 4.
- **10:30–12:30** (4 talleres — Aula 1, Aula 2, Aula 3, Explanada — **o** el Concurso del Conocimiento en el Auditorio Principal): un alumno solo puede inscribirse a **un** taller **o** participar en el concurso, nunca ambos ni más de un taller.

Esto aplica igual a las dos vías de asignación del punto 4 de arriba (registro previo y orden de llegada): el encargado no debe reservar a un alumno en dos ponencias de las 9:00, ni en un taller y el concurso a la vez; y la app de orden de llegada debe descartar del catálogo las opciones ya no disponibles para ese alumno en esa franja (incluido ocultar los talleres 10:30–12:30 si el alumno ya es integrante de un equipo del Concurso del Conocimiento).

> **Nota de esquema**: `eventos` y `competiciones` tienen columnas `hora_inicio`/`hora_fin` (ver `schema.sql`) precisamente para esto: antes de guardar una inscripción o una membresía de equipo, la app valida que no exista ya, para ese alumno y ese día, otra fila (en `inscripciones` o en `equipos`/`integrantes`) cuyo horario se traslape. Físicamente el alumno no puede estar en dos lugares a la vez, así que dos filas con horario traslapado el mismo día es siempre un error, sin importar si son dos ponencias, un taller y el concurso, o cualquier otra combinación de `eventos`/`competiciones`. **Excepción**: el Día Deportivo NO aplica esta validación entre sus 3 torneos — ahí se permite deliberadamente que un alumno se inscriba a más de uno aunque compartan la misma ventana horaria (ver [Reglas de inscripción a más de un torneo](../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md#reglas-de-inscripción-a-más-de-un-torneo)).

## Control de entrada y salida

La aplicación debe llevar, para los **3 días del evento** (Día Académico, Día Cultural y Día Deportivo — ver también [torneos-deportivos.md](../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md#registro-de-asistencia-el-día-del-evento) para el Día Deportivo), el control de hora de llegada y hora de salida de cada persona, con esta regla:

- **Primer escaneo del día**: registra la **hora de entrada**.
- **Segundo escaneo en adelante** (sin importar cuántas veces se repita — segundo, tercero, cuarto...): **no crea un registro nuevo**, solo **sobreescribe la hora de salida** con la hora de ese escaneo. Así, la hora de salida guardada siempre es la del **último** escaneo del día, sin necesidad de que el alumno o el staff distingan "este es mi escaneo de salida definitivo" — cualquier entrada y salida intermedia (por ejemplo, si el alumno sale y vuelve a entrar varias veces) simplemente se sobreescribe hasta la última vez que pasa por el punto de control.
- La pantalla del maestro/staff debe indicar claramente si el escaneo que acaba de hacer se registró como **Entrada** o como **Salida (actualizada)**, para evitar confusión sobre qué pasó.

Detalle técnico (esquema de base de datos y lógica de la app de escaneo) en los Prompts 1 y 7 de [app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md).

## Contenido de la credencial digital vs. contenido del QR

Un QR tiene capacidad de datos muy limitada: no puede "contener" una fotografía de forma práctica (el código resultaría enorme y poco confiable al escanear). Por eso se separan dos cosas:

| Elemento | Qué contiene | Dónde vive |
|---|---|---|
| Credencial digital (la imagen completa que ve el alumno) | Foto, nombre y datos generales, **más** el código QR | Se genera una vez y se envía/descarga al celular del alumno |
| Código QR (dentro de la credencial) | Únicamente el **número de cuenta** (8 caracteres, `XXXXXXXX`) como identificador único | Se escanea el día del evento |

Al escanear, el sistema usa el número de cuenta como llave para buscar en la base de datos y mostrar en pantalla la foto y el nombre del alumno (verificación visual por el staff), y para marcar la asistencia.

## Pre-registro (formulario)

Formulario propio (ver [Diseño técnico recomendado](#diseño-técnico-recomendado) abajo), abierto hasta el **martes 30 de septiembre** (día previo al evento). Campos:

| Campo | Detalle |
|---|---|
| Nombre completo | — |
| Número de cuenta | 8 caracteres, `XXXXXXXX` — identificador único (clave del QR) |
| Grupo/grado | Ej. `1° A` — sirve también para el desayuno por grupo (ver nota [4] de la matriz) y reportes |
| Correo institucional | Se usa para enviar la credencial digital generada |
| Fotografía tipo carnet ("tamaño infantil", solo rostro) | El alumno puede descargar su foto ya existente en **SICEUC** y adjuntarla, o subir una nueva que cumpla el mismo formato (rostro, tamaño infantil) |
| Comentario abierto: temas de interés para ponencias/talleres | **No es una selección de un catálogo ya definido** — es un insumo de opinión; a partir de las respuestas se arma el catálogo final de ponencias (09:00–10:00) y talleres (Sesiones 1 y 2). Ver notas [3] y [5] de la [matriz de itinerario](itinerario-matriz.md) |

Notas de diseño:
- La selección real de a qué ponencia/taller asistir (con cupo limitado) ocurre **el día del evento, después del escaneo del QR** (ver flujo arriba) — no en el pre-registro, porque el catálogo final depende de las respuestas del campo de temas de interés.
- Se recomienda validar el número de cuenta contra el padrón oficial de alumnos al recibir cada respuesta, para detectar errores de captura o duplicados antes de generar la credencial.
- Generar y enviar la credencial de cada alumno apenas se recibe su registro, en lugar de esperar al cierre del formulario — evita un cuello de botella de última hora el 30 de septiembre.

## Diseño técnico recomendado

Se descartó la opción inicial (Google Sheets + Forms + Apps Script) a favor de una **aplicación propia**, por decisión explícita del equipo: **PHP puro, JavaScript puro (sin frameworks de frontend ni backend) y base de datos MariaDB**.

Estructura del proyecto (carpeta `app/` en la raíz):

```
app/
├── registro/        Pre-registro de alumnos y generación de credencial digital
│                     (foto + datos + QR).
├── asistencias/      App de escaneo QR y control de entrada/salida, unificada
│                     para los 3 días del evento (ver "Control de entrada y
│                     salida" arriba). URL protegida con HTTP Basic Auth + token
│                     secreto — no es autoservicio ni de acceso público.
├── inscripciones/    Selección de taller/ponencia con cupo limitado, inmediatamente
│                     después de un escaneo exitoso en app/asistencias.
├── config/           Conexión compartida a la base de datos (PDO).
└── database/         Esquema SQL (MariaDB).
```

El plan de construcción, dividido en prompts secuenciales listos para ejecutarse uno por uno, está en **[app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md)** — incluye el esquema de base de datos, la conexión, el formulario, la generación de credencial/QR, el envío de correo, la app de escaneo y la inscripción a talleres con control de cupo (usando transacciones SQL para evitar sobrecupo por inscripciones simultáneas).

Puntos aún sin confirmar (detallados en ese documento): librería de generación/lectura de QR, método de envío de correo, y dónde correrá el servidor PHP/MariaDB.

## Pendientes por definir

- [ ] Ubicación física del punto de control de escaneo, tanto para entrada como para salida (entrada principal, Explanada, otro — puede ser el mismo punto para ambas).
- [ ] Si el escaneo de salida es obligatorio (¿se exige a todos los alumnos escanear antes de retirarse?) o solo se registra cuando ocurre de manera natural (alumno vuelve a pasar por el punto de control).
- [ ] Número de estaciones de escaneo (celulares/dispositivos) y personal responsable de operarlas.
- [ ] Quién programa/mantiene la aplicación (ver `app/PROMPTS-DESARROLLO.md`) y con qué tiempo de anticipación (debe estar probada antes del 1 de octubre).
- [ ] Cupo por taller/ponencia — depende de la capacidad de cada espacio (ver [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md)) y del catálogo final de ponencias/talleres, que a su vez depende de los temas de interés recogidos en el pre-registro.
- [ ] Fecha en que se cierra el vaciado de respuestas del pre-registro a un catálogo definitivo de ponencias/talleres (debe ser antes del 1 de octubre para poder armar la matriz completa).
- [ ] Cómo distribuir de forma segura al staff, antes de cada día, la URL de escaneo (con su token) y la contraseña compartida de acceso (ver "Decisiones técnicas resueltas" en `app/PROMPTS-DESARROLLO.md`) — por ejemplo un mensaje directo por WhatsApp al grupo de maestros/staff justo antes del punto de control, no un canal público.

## Relación con otros documentos

- [itinerario-matriz.md](itinerario-matriz.md) — bloque 08:00–08:30 referencia este proceso.
- [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md) — capacidades que limitarán el cupo de cada taller.
