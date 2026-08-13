# Torneos Deportivos — Día Deportivo (Sábado 3 de Octubre)

Tres torneos relámpago de eliminación directa, jugados en paralelo en el **Polideportivo de San Pedrito** (fuera del plantel) — una cancha asignada por deporte, según [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md): Fútbol Rápido, Voleibol y Quemados. Arrancan a las 07:30, justo después del registro (ver [matriz de itinerario](itinerario-matriz.md)).

Eje del evento: **convivencia familiar**, no solo competencia — se invita explícitamente a padres de familia a integrarse a los equipos, no solo a presenciar.

## Composición de equipos

- **10 integrantes por equipo**, mezclando alumnos y padres de familia.
- Un integrante funge como **capitán** (puede ser alumno o padre) — es el responsable de la inscripción y el punto de contacto del equipo.
- Pendiente definir una proporción mínima de padres por equipo (por ejemplo "al menos 3 de los 10"), para que la mezcla sea real y no un equipo de alumnos con un padre simbólico. Sin una regla mínima, el requisito de "mezclar" es difícil de exigir en el formulario.

## Inscripción de equipos (antes del evento)

- Se realiza **previamente por la aplicación**, con **fecha límite: martes 30 de septiembre de 2026** (mismo criterio de "día previo al cierre" que usa el pre-registro del Día Académico).
- El armado de llaves se publica el **viernes 2 de octubre de 2026** (un día antes del torneo), una vez cerradas las inscripciones.

### Formulario de inscripción de equipo (propuesta)

| Campo | Detalle |
|---|---|
| Torneo/deporte | Selección única: Fútbol Rápido, Voleibol o Quemados. Un mismo grupo de personas puede inscribir equipos distintos en más de un deporte si así lo desea — no hay restricción entre torneos. |
| Nombre del equipo | Texto libre. |
| Color de camisa | Selección de un catálogo cerrado de colores (para que el staff y los árbitros distingan equipos a simple vista). El sistema oculta/deshabilita los colores que ya haya tomado otro equipo **del mismo deporte** — no hace falta que sea único entre deportes distintos, porque no comparten cancha ni llave. |
| Capitán | Nombre completo, teléfono y correo de contacto. Si el capitán es alumno, se captura también su número de cuenta (para cruzarlo contra el padrón, igual que en el registro del Día Académico); si es padre de familia, no aplica número de cuenta. |
| Integrantes (9 restantes) | Por cada uno: nombre completo, tipo (**Alumno** / **Padre de familia**) y, si es alumno, número de cuenta y grupo. El campo "padres incorporados" que se pedía no se captura como un número aparte: se **calcula automáticamente** contando cuántos integrantes tienen tipo "Padre de familia" — evita que el número capturado no coincida con la lista real de integrantes. |
| Parentesco (solo si tipo = Padre) | Nombre del alumno familiar (si lo hay en el equipo o en el plantel), para trazabilidad — opcional si el padre participa sin tener un hijo en ese equipo específico. |
| Deslinde de responsabilidad | Casilla de aceptación — participación en actividad física de menores y adultos. Confirmar con la escuela si se requiere una versión firmada en papel además de la casilla digital. |

### Reglas de validación sugeridas

- Exactamente 10 integrantes (capitán incluido) antes de permitir enviar el formulario.
- Al menos un integrante de tipo "Alumno" y al menos un integrante de tipo "Padre de familia" (mezcla real); ajustar a la proporción mínima que se defina.
- Número de cuenta de cada alumno validado contra el padrón oficial, igual que en el pre-registro del Día Académico.
- Color de camisa único dentro del mismo deporte.
- Formulario cerrado automáticamente a partir del 30 de septiembre 23:59.

### Dónde vive esto en el sistema

Ya está en el roadmap: [app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md) agrega, a partir del **Prompt 12**, un módulo nuevo `app/torneos/` (inscripción de equipos, generación de credenciales/QR por integrante y escaneo de asistencia), reutilizando la base de datos, la conexión (`app/config/db.php`) y las mismas librerías ya elegidas para el Día Académico (phpqrcode, jsQR, PHPMailer), sin tocar las tablas del sistema individual por alumno.

## Registro de asistencia el día del evento

El escaneo de **entrada** se concentra en el bloque 07:00–07:30 de la [matriz de itinerario](itinerario-matriz.md), mismo mecanismo de escaneo por maestro/staff que el Día Académico, ahora en el Polideportivo de San Pedrito. El escaneo de **salida** puede darse en cualquier momento posterior (mismo criterio de entrada/salida que el Día Académico, ver [Control de entrada y salida](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md#control-de-entrada-y-salida)): el primer escaneo del día registra la hora de entrada; cualquier escaneo posterior solo sobreescribe la hora de salida con la más reciente. Diferencia clave frente al Día Académico: no todos los participantes son alumnos con credencial digital previa.

- **Alumnos**: se les genera un código QR propio al inscribir el equipo (no reutiliza directamente la credencial del Día Académico, aunque comparte la misma base de datos).
- **Padres de familia**: no están en el padrón de alumnos. Se resolvió generarles, igual que a los alumnos, un ticket/credencial simple con QR al inscribir el equipo (sin foto, solo nombre + equipo + deporte), enviado por correo — así hay un solo mecanismo de escaneo para los 10 integrantes de cada equipo, sin distinguir alumno de padre en el punto de control. Detalle técnico en los Prompts 12 y 17 de [app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md).

## Formato de llaves

- Eliminación directa (single elimination) en los tres torneos.
- El número de equipos por deporte se desconoce hasta el cierre de inscripciones (30 de septiembre) — si no es una potencia de 2 (4, 8, 16...), algunos equipos necesitarán un "bye" (pase directo) en la primera ronda. Quien arme las llaves el 2 de octubre debe considerarlo.
- Las llaves se publican el **2 de octubre**, un día antes del evento, para que los equipos sepan su primer rival y horario aproximado.

## Reglas por deporte

| Deporte | Formato del partido | Desempate |
|---|---|---|
| Fútbol Rápido | 10 minutos por partido | Empate → 3 penales directos por equipo; si persiste el empate → muerte súbita a 1 tiro |
| Voleibol | 3 sets | Puntos por set y regla de desempate no definidos por el usuario — pendiente (formato estándar de referencia: sets a 25 puntos, tercer set decisivo a 15, con diferencia mínima de 2) |
| Quemados | 3 rondas de 5 participantes (5 contra 5 por ronda) | Pendiente confirmar si gana el equipo que gane 2 de las 3 rondas, o si se suman puntos/eliminados de las 3 rondas |

## Premios sugeridos

La entrega de premios de 1° y 2° lugar de los 3 torneos es a cargo del **Director**, en la clausura de las 11:30 (ver [matriz de itinerario](itinerario-matriz.md)). Como el objetivo declarado es convivencia, no solo competencia, conviene que el reconocimiento no sea puramente monetario y que también valore la participación familiar:

- **1er lugar (cada uno de los 3 torneos)**: trofeo/copa para el equipo + medalla individual para los 10 integrantes (alumnos y padres por igual) + playera o algo conmemorativo del equipo campeón ("Campeón B23 2026"). Si aplica institucionalmente, un reconocimiento simbólico adicional para los alumnos del equipo (por ejemplo, mención en el acto de clausura).
- **2do lugar (cada uno de los 3 torneos)**: medalla individual para los 10 integrantes + diploma/reconocimiento para el equipo.
- **Reconocimiento adicional sugerido**: "Equipo con mejor espíritu deportivo/convivencia familiar" (uno por torneo o uno general), premiado con algo simbólico — refuerza el objetivo de convivencia por encima de solo ganar.
- Costo: medallas y trofeos económicos son suficientes dado el enfoque recreativo; no hay un documento de presupuesto todavía en `00-Informacion-General/` — definir el gasto de premiación cuando exista uno.

## Pendientes por definir

- [ ] Ubicación del/de los punto(s) de control de salida y si es obligatorio escanear al retirarse.
- [ ] Proporción mínima de padres por equipo (regla de "mezcla" concreta) — afecta la validación del formulario de inscripción ([app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md), Prompt 13).
- [ ] Puntos por set y desempate en Voleibol.
- [ ] Forma de determinar ganador en Quemados (mejor de 3 rondas vs. puntaje acumulado).
- [ ] Qué pasa con equipos "bye" si el número de inscritos no es potencia de 2.
- [ ] Presupuesto y proveedor de medallas/trofeos.
- [ ] Confirmar catálogo de colores de camisa disponibles.

## Relación con otros documentos

- [itinerario-matriz.md](itinerario-matriz.md) — bloque de registro y de torneos.
- [espacios-y-capacidades.md](../00-Informacion-General/espacios-y-capacidades.md) — canchas y capacidades.
- [registro-asistencia.md](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md) — mecanismo de credencial digital/QR del que este proceso toma como referencia.
- [app/PROMPTS-DESARROLLO.md](../app/PROMPTS-DESARROLLO.md) — roadmap de construcción del sistema; Prompts 1–11 Día Académico, Prompts 12–18 inscripción de equipos y control de asistencia del Día Deportivo (módulo `app/torneos/`).
