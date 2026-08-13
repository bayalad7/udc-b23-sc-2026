# Sistema de Registro e Inscripciones — Semana Cultural B23

Registro de asistencia por QR e inscripciones para la Semana Cultural del Aniversario del Bachillerato 23, Universidad de Colima. Documentación funcional completa en [../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md) (Día Académico) y [../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md](../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md) (Día Deportivo). El roadmap de construcción, con las decisiones técnicas y los prompts usados para programar cada parte, está en [PROMPTS-DESARROLLO.md](PROMPTS-DESARROLLO.md) — es la referencia para saber qué sigue.

## Stack

- **Backend**: PHP puro 8.2 (sin frameworks tipo Laravel/Symfony), acceso a datos con PDO y consultas preparadas (sin ORM).
- **Frontend**: JavaScript puro (sin frameworks tipo React/Vue).
- **Estilos**: Tailwind CSS, compilado con el Tailwind CLI standalone (binario, sin Node/npm ni build en el servidor de producción). Un único `assets/css/tailwind.css` compartido por `registro/`, `inscripciones/` y `torneos/`.
- **Base de datos**: MariaDB 11, motor InnoDB.
- **Librerías de un solo propósito** (sin dependencias, no son frameworks de aplicación):
  - [`phpqrcode`](https://github.com/t0k4rt/phpqrcode) (`pendalff/phpqrcode` vía Composer) — generación de códigos QR en el servidor.
  - [`jsQR`](https://github.com/cozmo/jsQR) — lectura de QR desde la cámara en el navegador (pendiente de integrar, ver Prompt 7 revisado).
  - [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) vía SMTP — envío de credenciales por correo (pendiente de integrar, ver Prompt 6).
- **Extensión GD** nativa de PHP — composición de las credenciales (foto + datos + QR).
- **Hosting de producción**: VPS personal (fuera del alcance de este repo), con acceso root/SSH.

## Estructura de carpetas

```
app/
├── index.php                        Página principal — accesos a las secciones de la app
├── config/
│   ├── db.php                       Conexión PDO compartida (lee credenciales de env o de db-credenciales.php)
│   └── db-credenciales.example.php  Plantilla para despliegues fuera de Docker (copiar a db-credenciales.php, no se sube)
├── database/
│   └── schema.sql                   Esquema completo de MariaDB: sistema, alumnos, eventos,
│                                    inscripciones (ponencias/talleres, individual), equipos,
│                                    integrantes (concursos y torneos por equipo) y
│                                    asistencias_generales (entrada/salida del día, solo alumnos)
├── registro/                        Pre-registro y credencial digital
│   ├── public/
│   │   ├── index.php                Formulario de pre-registro
│   │   ├── exito.php                Pantalla de confirmación + descarga de credencial
│   │   ├── uploads/                 Fotos subidas por los alumnos (no versionado)
│   │   └── credenciales/            Credenciales PNG generadas (no versionado)
│   └── includes/
│       ├── guardar-registro.php     Valida y guarda el pre-registro (POST de index.php)
│       └── generar-credencial.php   Compone la credencial (GD) y el QR (phpqrcode)
├── asistencias/                     Escaneo QR y control de entrada/salida, unificado para los
│                                    3 días. Pide una contraseña compartida (tabla `sistema`) antes
│                                    de dejar escanear — no es de acceso público.
├── inscripciones/                   Día Académico — selección de taller/ponencia (aún no creado)
├── torneos/                         Día Deportivo — inscripción de equipos (aún no creado)
├── assets/
│   ├── css/{input.css,tailwind.css} Fuente y salida compilada de Tailwind
│   ├── js/registro.js               Validación en cliente del formulario de pre-registro
│   └── img/logo/                    Logo institucional (varias variantes de color)
├── docker/
│   ├── apache-vhost.conf            VirtualHost — bloquea acceso HTTP directo a vendor/
│   └── php.ini                      Ajustes de PHP para desarrollo (uploads, timezone, errores)
├── Dockerfile                       Imagen PHP 8.2 + Apache + GD + Composer
├── docker/tailwind.Dockerfile       Imagen que compila Tailwind en modo watch
├── docker-compose.yml               Servicios: web, db, tailwind, adminer
├── composer.json                    Dependencia: pendalff/phpqrcode
└── .env.example                     Plantilla de variables de entorno para Docker
```

## Estado actual

| Módulo | Estado |
|---|---|
| Esquema de base de datos (`sistema`, `alumnos`, `eventos`, `inscripciones`, `equipos`, `integrantes`, `asistencias_generales`) | ✅ Listo — ver Prompts 1 y 12 en [PROMPTS-DESARROLLO.md](PROMPTS-DESARROLLO.md) (⚠️ ver "Pendientes menores" ahí: un error de sintaxis SQL en `equipos` bloquea crear el esquema tal cual). |
| Conexión a base de datos (`config/db.php`) | ✅ Listo. |
| Tailwind CSS | ✅ Configurado y compilando en modo watch vía Docker. |
| Formulario de pre-registro (`registro/public/index.php`) | ✅ Listo, con validación en cliente (`assets/js/registro.js`) y servidor. |
| Guardado del pre-registro (`guardar-registro.php`) | ✅ Listo: valida, evita duplicados por número de cuenta, guarda foto y registro. |
| Generación de credencial digital + QR (`generar-credencial.php`) | ✅ Listo: compone credencial vertical (foto + nombre + grupo + QR con el número de cuenta) usando GD y phpqrcode. |
| Envío de la credencial por correo (PHPMailer) | ⬜ Pendiente — Prompt 6. |
| App de escaneo de asistencia (`app/asistencias/`, entrada/salida, los 3 días) | ✅ Listo: contraseña compartida (`evento.php`), selector de día/operador/punto de control, escaneo con cámara + jsQR, registro en `asistencias_generales` (académico/cultural) o `integrantes` (deportivo) según el día. |
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
| `web` | http://localhost:8080 (puerto configurable con `APP_PORT`) | Apache + PHP 8.2. La raíz `/` sirve `app/index.php`, la página principal con accesos a cada módulo. |
| `adminer` | http://localhost:8081 (puerto configurable con `ADMINER_PORT`) | Visor de base de datos en el navegador (usuario/contraseña de abajo, servidor `db`). Solo para desarrollo — no forma parte de la app. |
| `db` | `127.0.0.1:3306` (si se agregó el mapeo de puerto en `docker-compose.yml`) | MariaDB 11, para conectar con un cliente de escritorio (ej. Navicat). |
| `tailwind` | — (sin puerto) | Recompila `assets/css/tailwind.css` automáticamente en cada cambio. |

Credenciales de MariaDB en desarrollo local (definidas en `.env`, libres — no son las de producción):

- Base de datos: `b23_semana_cultural`
- Usuario: `b23_app` / contraseña: `cambia_esta_clave`
- Root: `root` / contraseña: `cambia_esta_clave_root`

El esquema (`database/schema.sql`) se importa automáticamente la primera vez que se crea el volumen de `db`. Si se edita el esquema después, hay que aplicarlo a mano (el volumen ya existente no se re-inicializa solo).

## Rutas públicas

Todas relativas a la raíz del sitio (`http://localhost:8080` en desarrollo). Solo páginas (GET) — los endpoints internos que las procesan (`includes/`, POST/JSON) están bloqueados por `.htaccess` y no son navegables directamente.

| Ruta | Módulo | Descripción |
|---|---|---|
| `/` | — | Página principal: accesos a las secciones de la app. |
| `/registro/public/index.php` | Registro | Formulario de pre-registro de alumnos. |
| `/registro/public/recuperar.php` | Registro | Recuperar la credencial digital ya generada (número de cuenta + correo). |
| `/registro/public/exito.php?token=<token>` | Registro | Confirmación del pre-registro y descarga de la credencial. |
| `/asistencias/public/evento.php` | Asistencias | Acceso del staff: contraseña compartida y selección de día/operador/punto de control. |
| `/asistencias/public/escaneo.php` | Asistencias | Escaneo QR (cámara + jsQR) — solo tras iniciar turno en `evento.php`. |
| `/inscripciones/public/index.php` | Inscripciones | ⬜ Pendiente — selección de ponencia/taller (Prompts 8–10). |
| `/torneos/public/inscripcion.php` | Torneos | ⬜ Pendiente — inscripción de equipos del Día Deportivo (Prompts 12–18). |
| `http://localhost:8081` | — | Adminer (visor de base de datos) — solo desarrollo, no forma parte de la app. |

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

Seguir el roadmap en orden a partir de donde quedó marcado en la sección "Estado" de [PROMPTS-DESARROLLO.md](PROMPTS-DESARROLLO.md): envío de credencial por correo (Prompt 6), módulo unificado de escaneo de asistencia `app/asistencias/` (Prompt 7 revisado, con su protección de acceso), y de ahí en adelante inscripciones y torneos.
