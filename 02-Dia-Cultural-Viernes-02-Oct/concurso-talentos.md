# Escenario de Talentos "Expresa tu esencia" — Día Cultural (Viernes 2 de Octubre)

Show de talentos en la **Explanada**, bloque **16:00–17:20** (ver [matriz de itinerario](itinerario-matriz.md)). Se modela en base de datos como una fila de `competiciones` (`dia='cultural'`, `tipo='concurso'` — ver semilla en [app/database/seeds.sql](../app/database/seeds.sql)) con sus participaciones en `equipos`/`integrantes`, igual que el Concurso del Conocimiento y los torneos deportivos.

## Reglas de inscripción

- **Individual o en equipo**: a diferencia del Concurso del Conocimiento (siempre 12 equipos de 10) y los torneos deportivos (siempre equipos de 10), aquí el alumno decide si participa solo o acompañado. Un acto individual se modela igual como un `equipo` (con exactamente un integrante, el propio alumno como `id_alumno_capitan`) — no hace falta una tabla ni un flujo aparte.
- **Un alumno puede inscribirse más de una vez**: puede tener más de un acto/participación (por ejemplo, cantar en un dueto y además presentar un acto individual), porque cada inscripción recibe su propio horario de asignación dentro del show. Esto es distinto de las demás competiciones, donde un alumno pertenece a un solo equipo por competición.
- No hay restricción de mezclar alumnos entre sí en un mismo acto (a diferencia de los torneos deportivos, aquí no aplica la mezcla con padres/madres salvo que el acto mismo la incluya como parte de la presentación).
- **Fecha límite de inscripción: 30 de septiembre de 2026** (mismo criterio que las demás competiciones — ver semilla en [app/database/seeds.sql](../app/database/seeds.sql)).

## Asignación de horario dentro del show

El bloque completo dura 16:00–17:20 (80 minutos) y debe repartirse entre todos los actos inscritos, cada uno con su propio horario de presentación — de ahí que un alumno con más de una inscripción reciba más de un horario. **Pendiente definir**: cuánto dura cada acto (tiempo fijo por participación vs. variable según el tipo de talento), cómo y cuándo se publica el orden/horario final, y un tope máximo de actos para que quepan en los 80 minutos disponibles.

## Pendientes por definir

- [ ] Duración estándar (o máxima) por acto, para calcular cuántas participaciones caben en los 80 minutos del bloque.
- [ ] Tope de participaciones totales del show (y si hace falta un criterio de selección/audición previa si se exceden).
- [ ] Cómo y cuándo se publica el horario/orden de presentación de cada acto (¿mismo criterio que las llaves deportivas, un día antes?).
- [ ] Si hay jurado, criterios de evaluación y premios (a diferencia de los torneos deportivos y el Concurso del Conocimiento, no hay nada definido todavía sobre si el show es competitivo con ganador o solo exhibición).
- [ ] Requisitos técnicos por acto (audio, utilería, vestuario) y si el alumno debe declararlos al inscribirse.

## Relación con otros documentos

- [itinerario-matriz.md](itinerario-matriz.md) — bloque del show y de los talleres previos.
- [app/database/seeds.sql](../app/database/seeds.sql) — semilla de esta competición en `competiciones`.
- [app/database/schema.sql](../app/database/schema.sql) — tablas `competiciones`/`equipos`/`integrantes` que modelan las inscripciones.
