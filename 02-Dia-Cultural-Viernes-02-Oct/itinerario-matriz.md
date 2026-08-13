# Itinerario — Día Cultural (Viernes 2 de Octubre)

Espacios según [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md).

Estado: **solo estos dos bloques están definidos** (14:00–16:00 y 16:00–17:20); el resto de la jornada (hora de inicio, registro de entrada/salida QR — obligatorio los 3 días, ver [nota [1]](#nota-1) — y cualquier actividad antes de las 14:00) sigue **sin definir**. No usar esta matriz todavía como itinerario completo del día.

## Matriz de tiempo × aulas

| Hora | Aula 1 (40) | Aula 2 (40) | Aula 3 (40) | Explanada (40) |
|---|---|---|---|---|
| Pendiente | REGISTRO DE ASISTENCIA (QR) — ver [nota [1]](#nota-1) | REGISTRO DE ASISTENCIA (QR) — ver [nota [1]](#nota-1) | REGISTRO DE ASISTENCIA (QR) — ver [nota [1]](#nota-1) | REGISTRO DE ASISTENCIA (QR) — ver [nota [1]](#nota-1) |
| 14:00 – 16:00 | Taller de pintura — "El arte de Van Gogh" — ver [nota [2]](#nota-2) | Taller de cuento corto — ver [nota [2]](#nota-2) | Taller de fotografía básica — ver [nota [2]](#nota-2) | — |
| 16:00 – 17:20 | — | — | — | Escenario de Talentos "Expresa tu esencia" — ver [nota [3]](#nota-3) |

## Notas

- <a id="nota-1"></a>**[1]** Igual que el Día Académico y el Día Deportivo, el Día Cultural también lleva control de entrada/salida por QR (ver [Control de entrada y salida](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md#control-de-entrada-y-salida)) — mismo mecanismo unificado en `app/asistencias/` (`?evento=cultural`). Falta definir la hora y el punto de control de este bloque, así que se deja como "Pendiente" en la columna Hora.
- <a id="nota-2"></a>**[2]** Los 3 talleres (14:00–16:00) corren en paralelo, uno por aula. Semillas de estos eventos en [app/database/seeds.sql](../app/database/seeds.sql) (`eventos`, `dia='cultural'`, `tipo='taller'`, cupo 40 cada uno). A diferencia del Día Académico, no hay todavía una regla de exclusividad indicada para estos 3 talleres (confirmar si un alumno puede tomar más de uno, o si aplica el mismo criterio de "uno solo" — ver [Pendientes por definir](#pendientes-por-definir)). Facilitador, descripción y responsable de cada taller están como "Por definir" en la semilla.
- <a id="nota-3"></a>**[3]** El Escenario de Talentos es una **competición** (`competiciones`, `dia='cultural'`, `tipo='concurso'`), no un evento individual — se modela con `equipos`/`integrantes` como el Concurso del Conocimiento y los torneos deportivos. Reglas de inscripción (individual o en equipo, y por qué un alumno puede aparecer más de una vez) en [concurso-talentos.md](concurso-talentos.md).

## Pendientes por definir

- [ ] Hora de inicio del día, punto y hora del registro de entrada/salida (QR).
- [ ] Actividades, si las hay, antes de las 14:00.
- [ ] Si los 3 talleres de 14:00–16:00 tienen alguna regla de exclusividad (uno solo por alumno) o se puede tomar más de uno.
- [ ] Facilitador, descripción y responsable real de cada taller (hoy "Por definir" en la semilla — ver [app/database/seeds.sql](../app/database/seeds.sql)).
- [ ] Hora exacta de cierre del día (después de las 17:20 — ¿clausura, entrega de reconocimientos del show, u otra actividad?).

## Relación con otros documentos

- [concurso-talentos.md](concurso-talentos.md) — inscripción individual/por equipo al Escenario de Talentos y reglas de asignación de horario.
- [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md) — capacidades de aulas y Explanada.
- [app/database/seeds.sql](../app/database/seeds.sql) — semillas de `eventos` y `competiciones` de este día.
