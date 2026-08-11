# 🎓 Semana Cultural B23 — Aniversario

Planificación y sistema de apoyo para la Semana Cultural del Aniversario del **Bachillerato 23, Universidad de Colima**.

> 📌 Para el contexto completo de decisiones, convenciones y estado detallado, ver [CLAUDE.md](CLAUDE.md) — es la fuente de verdad de este proyecto.

## 📅 Fechas del evento

| Día | Fecha | Enfoque |
|---|---|---|
| 1️⃣ | Jueves 1 de Octubre | 🎓 Día Académico |
| 2️⃣ | Viernes 2 de Octubre | 🎭 Día Cultural |
| 3️⃣ | Sábado 3 de Octubre | ⚽ Día Deportivo |

## 📁 Estructura del repositorio

```
📂 00-Informacion-General/          Datos que aplican a los tres días (espacios, capacidades)
📂 01-Dia-Academico-Jueves-01-Oct/  Itinerario y logística del Día Académico
📂 02-Dia-Cultural-Viernes-02-Oct/  Itinerario y logística del Día Cultural
📂 03-Dia-Deportivo-Sabado-03-Oct/  Itinerario y logística del Día Deportivo
📂 app/                             Sistema de registro e inscripciones (PHP + JS puro + MariaDB)
📄 CLAUDE.md                        Contexto y estado del proyecto para retomar el trabajo
📄 README.md                        Este archivo
```

## ✅ Estado actual

- 🎓 **Día Académico**: itinerario completo (08:00–13:00) en [itinerario-matriz.md](01-Dia-Academico-Jueves-01-Oct/itinerario-matriz.md). Quedan detalles por definir: ponentes, catálogo final de talleres, logística del pastel de clausura.
- 🪪 **Registro de asistencia por QR**: proceso y pre-registro documentados en [registro-asistencia.md](01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md).
- 🖥️ **Sistema de registro e inscripciones**: en construcción, ver siguiente sección.
- 🎭 **Día Cultural**: pendiente.
- ⚽ **Día Deportivo**: pendiente.

## 🖥️ Sistema de registro e inscripciones (`app/`)

Aplicación propia para el control de asistencia por QR y la asignación de ponencias/talleres del Día Académico.

- **Stack**: PHP puro, JavaScript puro (sin frameworks), base de datos MariaDB.
- **Librerías de un solo propósito**: [`phpqrcode`](https://github.com/t0k4rt/phpqrcode) (generar QR), [`jsQR`](https://github.com/cozmo/jsQR) (leer QR desde cámara), [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) (envío de credenciales por SMTP).
- **Módulos**:
  - `app/registro/` — pre-registro de alumnos, generación de credencial digital (foto + datos + QR) y escaneo de asistencia el día del evento (operado por el maestro/staff).
  - `app/inscripciones/` — asignación de ponencia/taller con cupo limitado, ya sea por registro previo del encargado del evento académico o por orden de llegada el día del evento. El Concurso del Conocimiento queda fuera de este sistema.
- **Plan de construcción**: [app/PROMPTS-DESARROLLO.md](app/PROMPTS-DESARROLLO.md) — 11 prompts secuenciales, con checklist de avance.

## 🤝 Cómo continuar el trabajo

Al retomar el proyecto, revisar la sección "Estado actual" de [CLAUDE.md](CLAUDE.md) y, si se está trabajando en `app/`, el checklist de [app/PROMPTS-DESARROLLO.md](app/PROMPTS-DESARROLLO.md).
