# Registro de Asistencia — Día Académico

Proceso de control de acceso y registro a talleres/ponencias mediante credencial digital (QR). Ocupa el bloque **08:00–08:30** de la [matriz de itinerario](itinerario-matriz.md).

## Flujo del proceso

1. **Pre-registro (antes del evento)**: el alumno llena un formulario de registro en la nube con sus datos y foto.
2. **Credencial digital**: a partir del pre-registro se genera una credencial (imagen) que el alumno guarda/muestra desde su celular, con su foto, datos generales y un código QR.
3. **Día del evento — escaneo (08:00–08:30)**: el escaneo lo opera el **maestro/staff** en el punto de control (no es autoservicio del alumno). El alumno presenta la credencial, el maestro escanea el QR con la app y el sistema marca su asistencia con hora.
4. **Asignación de ponencia/taller — dos vías posibles**:
   - **Registro previo**: si el alumno ya eligió su ponencia/taller de antemano con el encargado de organizar el evento académico (durante el periodo de clases, antes del 1 de octubre), el sistema ya tiene su lugar reservado — no vuelve a elegir el día del evento.
   - **Por orden de llegada (el día del evento)**: si no tiene un lugar reservado previamente, justo después del escaneo elige de inmediato, con cupo limitado y **por orden de llegada** (el que se registra primero tiene más opciones disponibles): la ponencia de las 9:00–10:00 y los talleres de las Sesión 1 y 2 (10:30–12:30).
   - El **Concurso del Conocimiento** (Auditorio Principal, 10:30–12:30) queda **fuera de este mecanismo**: sus 12 equipos se organizan aparte, no por elección libre con cupo el día del evento.

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
├── registro/        Pre-registro de alumnos, generación de credencial digital
│                     (foto + datos + QR) y escaneo de asistencia el día del evento.
├── inscripciones/    Selección de taller/ponencia con cupo limitado, inmediatamente
│                     después de un escaneo exitoso en app/registro.
├── config/           Conexión compartida a la base de datos (PDO).
└── database/         Esquema SQL (MariaDB).
```

El plan de construcción, dividido en prompts secuenciales listos para ejecutarse uno por uno, está en **[app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md)** — incluye el esquema de base de datos, la conexión, el formulario, la generación de credencial/QR, el envío de correo, la app de escaneo y la inscripción a talleres con control de cupo (usando transacciones SQL para evitar sobrecupo por inscripciones simultáneas).

Puntos aún sin confirmar (detallados en ese documento): librería de generación/lectura de QR, método de envío de correo, y dónde correrá el servidor PHP/MariaDB.

## Pendientes por definir

- [ ] Ubicación física del punto de control de escaneo (entrada principal, Explanada, otro).
- [ ] Número de estaciones de escaneo (celulares/dispositivos) y personal responsable de operarlas.
- [ ] Quién programa/mantiene la aplicación (ver `app/PROMPTS-DESARROLLO.md`) y con qué tiempo de anticipación (debe estar probada antes del 1 de octubre).
- [ ] Cupo por taller/ponencia — depende de la capacidad de cada espacio (ver [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md)) y del catálogo final de ponencias/talleres, que a su vez depende de los temas de interés recogidos en el pre-registro.
- [ ] Fecha en que se cierra el vaciado de respuestas del pre-registro a un catálogo definitivo de ponencias/talleres (debe ser antes del 1 de octubre para poder armar la matriz completa).

## Relación con otros documentos

- [itinerario-matriz.md](itinerario-matriz.md) — bloque 08:00–08:30 referencia este proceso.
- [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md) — capacidades que limitarán el cupo de cada taller.
