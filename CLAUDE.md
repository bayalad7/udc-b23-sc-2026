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

`app/` contiene el sistema que soporta el registro de asistencia por QR (ver `01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md`): `app/registro/` (pre-registro, credencial digital, escaneo) y `app/inscripciones/` (selección de taller/ponencia con cupo). El plan de construcción está en `app/PROMPTS-DESARROLLO.md`, como una lista de prompts secuenciales — es la referencia para saber qué sigue al retomar el desarrollo.

## Estado actual

- [x] Estructura de carpetas creada
- [x] Espacios y capacidades registrados (`00-Informacion-General/espacios-y-capacidades.md`)
- [ ] Matriz de itinerario Día Académico — todos los bloques 08:00–13:00 tienen actividad asignada (`01-Dia-Academico-Jueves-01-Oct/itinerario-matriz.md`); pendientes de detalle: ponentes/responsables de las 4 ponencias (09:00–10:00), catálogo de talleres y confirmación del concurso del conocimiento (10:30–12:30), punto de desayuno (10:00–10:30) y logística del pastel de clausura (12:30–13:00).
- [x] Proceso de registro de asistencia por QR documentado (`01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md`), incluyendo el formulario de pre-registro; pendientes: ubicación del punto de control, librería de QR, método de envío de correo.
- [ ] Sistema de registro e inscripciones — estructura de carpetas creada (`app/`); stack (PHP+JS puro+MariaDB), librerías (phpqrcode, jsQR, PHPMailer/SMTP), hosting (VPS personal) y flujo de asignación de talleres (previo por el encargado vs. orden de llegada el día del evento; Concurso fuera del sistema) ya definidos; construcción sin empezar, roadmap de 11 prompts listo en `app/PROMPTS-DESARROLLO.md`.
- [ ] Itinerario Día Cultural — pendiente
- [ ] Itinerario Día Deportivo — pendiente

## Convenciones

- **Formato de fecha**: día completo en español, ej. "Jueves 1 de Octubre".
- **Formato de hora**: 24 horas, ej. `08:00 – 09:00`.
- **Matrices de itinerario**: tabla Markdown con la hora en la primera columna y cada aula/espacio como columna siguiente. Los recesos generales se marcan en una fila que abarca todos los espacios.
- **Nombres de espacio**: usar siempre el mismo nombre que en `espacios-y-capacidades.md` para poder cruzar información entre documentos.
- Los documentos de cada día son independientes entre sí; no se asume que los espacios o el horario de un día se repitan en otro salvo que se indique explícitamente.

## Cómo continuar el trabajo

Al retomar este proyecto, revisar primero la sección "Estado actual" de este archivo para saber qué día/documento sigue. Actualizar esa lista conforme se completen documentos.
