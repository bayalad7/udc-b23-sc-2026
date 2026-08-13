# Sistema de Registro e Inscripciones — Semana Cultural B23

Registro de asistencia por QR e inscripciones para la Semana Cultural del Aniversario del Bachillerato 23, Universidad de Colima. Documentación funcional completa en [../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md) (Día Académico) y [../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md](../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md) (Día Deportivo). El roadmap de construcción, con las decisiones técnicas y los prompts usados para programar cada parte, está en [PROMPTS-DESARROLLO.md](PROMPTS-DESARROLLO.md) — es la referencia para saber qué sigue.

## Stack

- **Backend**: PHP puro 8.2 (sin frameworks tipo Laravel/Symfony), acceso a datos con PDO y consultas preparadas (sin ORM).
- **Frontend**: JavaScript puro (sin frameworks tipo React/Vue).
- **Estilos**: Tailwind CSS, compilado con el Tailwind CLI standalone (binario, sin Node/npm ni build en el servidor de producción). Un único `assets/css/tailwind.css` compartido por `registro/`, `inscripciones/` y `torneos/`.
- **Base de datos**: MariaDB 11, motor InnoDB.
- **Librerías de un solo propósito** (sin dependencias, no son frameworks de aplicación):
  - [`phpqrcode`](https://github.com/t0k4rt/phpqrcode) (`pendalff/phpqrcode` vía Composer) — generación de códigos QR en el servidor.
  - [`jsQR`](https://github.com/cozmo/jsQR) — lectura de QR desde la cámara en el navegador (pendiente de integrar, ver Prompt 7).
  - [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) vía SMTP — envío de credenciales por correo (pendiente de integrar, ver Prompt 6).
- **Extensión GD** nativa de PHP — composición de las credenciales (foto + datos + QR).
- **Hosting de producción**: VPS personal (fuera del alcance de este repo), con acceso root/SSH.

## Estructura de carpetas

```
app/
├── config/
│   ├── db.php                       Conexión PDO compartida (lee credenciales de env o de db-credenciales.php)
│   └── db-credenciales.example.php  Plantilla para despliegues fuera de Docker (copiar a db-credenciales.php, no se sube)
├── database/
│   └── schema.sql                   Esquema de MariaDB (solo tabla `alumnos` por ahora)
├── registro/                        Día Académico — pre-registro, credencial digital y escaneo de asistencia
│   ├── public/
│   │   ├── index.php                 Formulario de pre-registro
│   │   ├── exito.php                 Pantalla de confirmación + descarga de credencial
│   │   ├── uploads/                  Fotos subidas por los alumnos (no versionado)
│   │   └── credenciales/             Credenciales PNG generadas (no versionado)
│   └── includes/
│       ├── guardar-registro.php      Valida y guarda el pre-registro (POST de index.php)
│       └── generar-credencial.php    Compone la credencial (GD) y el QR (phpqrcode)
├── inscripciones/                   Día Académico — selección de taller/ponencia (aún no creado)
├── torneos/                         Día Deportivo — inscripción de equipos (aún no creado)
├── assets/
│   ├── css/{input.css,tailwind.css} Fuente y salida compilada de Tailwind
│   ├── js/registro.js                Validación en cliente del formulario de pre-registro
│   └── img/logo/                     Logo institucional (varias variantes de color)
├── docker/
│   ├── apache-vhost.conf             VirtualHost — bloquea acceso HTTP directo a vendor/
│   └── php.ini                       Ajustes de PHP para desarrollo (uploads, timezone, errores)
├── Dockerfile                        Imagen PHP 8.2 + Apache + GD + Composer
├── docker/tailwind.Dockerfile        Imagen que compila Tailwind en modo watch
├── docker-compose.yml                Servicios: web, db, tailwind, adminer
├── composer.json                     Dependencia: pendalff/phpqrcode
└── .env.example                      Plantilla de variables de entorno para Docker
```

## Estado actual

| Módulo | Estado |
|---|---|
| Esquema de base de datos (`alumnos`) | ✅ Listo. Faltan las tablas `asistencia`, `talleres`, `inscripciones` (Día Académico) y `equipos`, `integrantes_equipo`, `asistencia_equipos` (Día Deportivo) — ver Prompts 1 y 12. |
| Conexión a base de datos (`config/db.php`) | ✅ Listo. |
| Tailwind CSS | ✅ Configurado y compilando en modo watch vía Docker. |
| Formulario de pre-registro (`registro/public/index.php`) | ✅ Listo, con validación en cliente (`assets/js/registro.js`) y servidor. |
| Guardado del pre-registro (`guardar-registro.php`) | ✅ Listo: valida, evita duplicados por número de cuenta, guarda foto y registro. |
| Generación de credencial digital + QR (`generar-credencial.php`) | ✅ Listo: compone credencial vertical (foto + nombre + grupo + QR con el número de cuenta) usando GD y phpqrcode. |
| Envío de la credencial por correo (PHPMailer) | ⬜ Pendiente — Prompt 6. |
| App de escaneo de asistencia (entrada/salida) | ⬜ Pendiente — Prompt 7. |
| Selección de taller/ponencia (`app/inscripciones/`) | ⬜ Pendiente — Prompts 8–10. |
| Reporte de asistencia y ocupación | ⬜ Pendiente — Prompt 11 (opcional). |
| Inscripción de equipos (`app/torneos/`) | ⬜ Pendiente — Prompts 12–18. |

Checklist detallado prompt por prompt en la sección "Estado" de [PROMPTS-DESARROLLO.md](PROMPTS-DESARROLLO.md).

## Levantar el entorno de desarrollo (Docker)

Requisito: Docker Desktop.

```bash
cd app
cp .env.example .env      # ajustar puertos/contraseñas si hace falta
docker compose up -d
```

Servicios expuestos al host:

| Servicio | URL / conexión | Descripción |
|---|---|---|
| `web` | http://localhost:8080 (puerto configurable con `APP_PORT`) | Apache + PHP 8.2. La raíz `/` no tiene `index.php` (da 403 a propósito); entrar directo al módulo, ej. **http://localhost:8080/registro/public/index.php** |
| `adminer` | http://localhost:8081 (puerto configurable con `ADMINER_PORT`) | Visor de base de datos en el navegador (usuario/contraseña de abajo, servidor `db`). Solo para desarrollo — no forma parte de la app. |
| `db` | `127.0.0.1:3306` (si se agregó el mapeo de puerto en `docker-compose.yml`) | MariaDB 11, para conectar con un cliente de escritorio (ej. Navicat). |
| `tailwind` | — (sin puerto) | Recompila `assets/css/tailwind.css` automáticamente en cada cambio. |

Credenciales de MariaDB en desarrollo local (definidas en `.env`, libres — no son las de producción):

- Base de datos: `b23_semana_cultural`
- Usuario: `b23_app` / contraseña: `cambia_esta_clave`
- Root: `root` / contraseña: `cambia_esta_clave_root`

El esquema (`database/schema.sql`) se importa automáticamente la primera vez que se crea el volumen de `db`. Si se edita el esquema después, hay que aplicarlo a mano (el volumen ya existente no se re-inicializa solo).

## Rutas

Todas relativas a la raíz del sitio (`http://localhost:8080` en desarrollo). La raíz `/` no tiene `index.php` y responde 403 a propósito — no es una ruta de la app.

### Día Académico — `registro/`

| Ruta | Método | Descripción |
|---|---|---|
| `/registro/public/index.php` | GET | Formulario de pre-registro (punto de entrada de la app). Acepta `?error=<código>` para mostrar el mensaje de error correspondiente tras un intento fallido. |
| `/registro/includes/guardar-registro.php` | POST | Recibe el formulario anterior, valida, guarda alumno + foto, genera la credencial y redirige a `exito.php`. No es una página — accederla por GET redirige al formulario; el resto del directorio `includes/` está bloqueado por `.htaccess`. |
| `/registro/public/exito.php` | GET | Confirmación del pre-registro y descarga de la credencial digital. Requiere `?token=<token_descarga>` (hex de 32 caracteres) generado al guardar el registro. |
| `/registro/public/uploads/<archivo>` | GET | Fotos originales subidas por los alumnos (estático, no versionado). |
| `/registro/public/credenciales/<archivo>` | GET | Credenciales PNG ya compuestas (estático, no versionado). |
| `/registro/public/escaneo.php` | — | ⬜ Pendiente (Prompt 7) — app de escaneo de asistencia (entrada/salida) operada por el maestro/staff. |

### Día Académico — `inscripciones/` (⬜ pendiente, Prompts 8–10)

| Ruta prevista | Descripción |
|---|---|
| `/inscripciones/public/index.php` | Selección de taller/ponencia por orden de llegada, tras un escaneo de entrada. |
| `/inscripciones/includes/inscribir.php` | Backend de inscripción con control de concurrencia sobre el cupo. |
| `/inscripciones/admin/` | Herramienta del encargado para asignar taller/ponencia de antemano (`origen='previo'`). |

### Día Deportivo — `torneos/` (⬜ pendiente, Prompts 12–18)

| Ruta prevista | Descripción |
|---|---|
| `/torneos/public/inscripcion.php` | Formulario de inscripción de equipo (10 integrantes, alumnos + padres). |
| `/torneos/includes/guardar-equipo.php` | Guarda equipo + integrantes y genera credenciales/QR por integrante. |
| `/torneos/public/escaneo.php` | App de escaneo de asistencia (entrada/salida) en el Polideportivo de San Pedrito. |
| `/torneos/admin/asistencia.php` | Reporte de asistencia por equipo (opcional). |

### Otras rutas de desarrollo (no forman parte de la app)

| Ruta | Descripción |
|---|---|
| `http://localhost:8081` (Adminer) | Visor de base de datos en el navegador, servicio aparte en `docker-compose.yml`. |

## Base de datos fuera de Docker (VPS de producción)

`config/db.php` detecta automáticamente el entorno: si existen las variables de entorno `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASSWORD` (como las define `docker-compose.yml`), las usa; si no, busca `config/db-credenciales.php` (copiar desde `config/db-credenciales.example.php`, **no se sube al repositorio** — ver `.gitignore`).

## Convenciones del código

- Nunca confiar solo en la validación del cliente: todo lo que valida `assets/js/registro.js` se vuelve a validar en PHP.
- Acceso a datos siempre con PDO en modo `ERRMODE_EXCEPTION` y consultas preparadas — nunca SQL concatenado.
- El QR de cada credencial codifica **únicamente** un identificador (número de cuenta del alumno, o `codigo_participante` en equipos del Día Deportivo) — nunca foto ni datos personales; esos se consultan en la base de datos al escanear.
- El control de entrada/salida (transversal a los 3 días, ver [registro-asistencia.md](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md#control-de-entrada-y-salida)) lo opera el maestro/staff en el punto de control, no es autoservicio: el primer escaneo del día registra la entrada; cualquier escaneo posterior solo sobreescribe la hora de salida.
- `vendor/` (dependencias de Composer) nunca debe ser accesible por HTTP — bloqueado en `docker/apache-vhost.conf`.
- Un único archivo Tailwind compilado (`assets/css/tailwind.css`) para toda la app — no generar un build distinto por módulo.

## Próximos pasos

Seguir el roadmap en orden a partir de donde quedó marcado en la sección "Estado" de [PROMPTS-DESARROLLO.md](PROMPTS-DESARROLLO.md): envío de credencial por correo (Prompt 6), app de escaneo de asistencia (Prompt 7), y de ahí en adelante inscripciones y torneos.
