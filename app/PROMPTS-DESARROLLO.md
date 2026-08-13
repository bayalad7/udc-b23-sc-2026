# Roadmap de prompts — Sistema de Registro e Inscripciones

Lista de prompts secuenciales para construir el sistema descrito en [../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md) (Prompts 1–11) y, a partir del Prompt 12, la inscripción de equipos y el control de asistencia del Día Deportivo descritos en [../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md](../03-Dia-Deportivo-Sabado-03-Oct/torneos-deportivos.md). Están pensados para ejecutarse **uno por uno, en orden**, con quien vaya a programar (usándolos aquí con Claude Code o con cualquier otra herramienta/desarrollador).

## Contexto para pegar antes del primer prompt

```
Estoy construyendo el sistema de registro e inscripciones para el Día Académico
(Jueves 1 de Octubre) de la Semana Cultural del Bachillerato 23, Universidad de Colima.

Stack obligatorio: PHP puro (sin frameworks tipo Laravel/Symfony), JavaScript puro
(sin frameworks tipo React/Vue), base de datos MariaDB. Sin frameworks de frontend
ni backend. Se permiten librerías de un solo propósito y sin dependencias
(por ejemplo, para generar/leer códigos QR) cuando no es razonable escribir esa
funcionalidad desde cero — pero no frameworks de aplicación completos.

Para el diseño visual se usa **Tailwind CSS**, compilado con el **Tailwind CLI
standalone** (binario descargable, sin Node/npm ni pipeline de build en el
servidor — ver "Decisiones técnicas resueltas" en este documento). Es la única
excepción framework-css al criterio "sin frameworks de frontend", porque no
agrega JavaScript ni dependencias en tiempo de ejecución: solo compila un
archivo .css estático que se sube junto con el resto del código.

Estructura del proyecto:
- app/registro/      -> pre-registro de alumnos, generación de credencial digital
                         (foto + datos + QR) y escaneo de asistencia el día del evento.
- app/inscripciones/  -> asignación de taller/ponencia con cupo limitado, inmediatamente
                         después de un escaneo exitoso en app/registro.
- app/config/         -> conexión compartida a la base de datos.
- app/database/       -> esquema SQL.

El QR de la credencial codifica ÚNICAMENTE el número de cuenta del alumno
(8 caracteres, formato XXXXXXXX) — nunca la foto ni otros datos, que se
consultan en la base de datos al escanear.

Control de entrada y salida (ver registro-asistencia.md#control-de-entrada-y-salida):
el escaneo el día del evento lo opera el MAESTRO/STAFF en el punto de control,
no es autoservicio del alumno. El PRIMER escaneo del día registra la hora de
entrada; CUALQUIER escaneo posterior (segundo, tercero...) solo sobreescribe
la hora de salida con la más reciente, sin crear una fila nueva ni repetir la
asignación de ponencia/taller.

Cómo se asigna el alumno a una ponencia/taller (ver registro-asistencia.md),
solo en el primer escaneo (el de entrada):
- Hay dos vías para que un alumno tenga lugar en una ponencia/taller:
  (a) Registro previo: el encargado de organizar el evento académico ya le asignó
      un lugar de antemano, durante el periodo de clases (antes del 1 de octubre).
  (b) Por orden de llegada: si no tiene lugar previo, justo después de que el
      maestro lo escanea el día del evento, elige de inmediato entre lo que
      todavía tenga cupo disponible (ponencia 9:00-10:00 y talleres de Sesión 1
      y Sesión 2, 10:30-12:30).
- El Concurso del Conocimiento (Auditorio Principal, 10:30-12:30) NO pasa por
  este sistema de cupo/orden de llegada: sus 12 equipos se organizan aparte y
  están fuera del alcance de app/inscripciones.

Si te falta información para tomar una decisión de negocio (no técnica),
pregúntame en vez de asumir.
```

## Decisiones técnicas resueltas

- [x] **Librería de QR — generación (servidor)**: [`phpqrcode`](https://github.com/t0k4rt/phpqrcode) original no está publicada en Packagist bajo ese nombre, así que se usa [`pendalff/phpqrcode`](https://packagist.org/packages/pendalff/phpqrcode) — un fork del mismo código con soporte Composer. Ojo al usarla: sus clases están bajo el namespace `PHPQRCode` (`\PHPQRCode\QRcode::png(...)`, no `QRcode::png(...)` a secas) y emite varios `E_DEPRECATED` en PHP 8.2 (parámetros opcionales antes que obligatorios) — inofensivos, silenciados vía `error_reporting` en `docker/php.ini`. Sigue usando la extensión GD que ya se necesita para componer la credencial. Encaja con "PHP puro": es una utilidad de un solo propósito, no un framework.
- [x] **Librería de QR — lectura (cámara, cliente)**: [`jsQR`](https://github.com/cozmo/jsQR). Un solo archivo JavaScript sin dependencias, decodifica QR a partir de los frames de video de la cámara vía `<canvas>`. Encaja con "JS puro" por la misma razón: es una utilidad, no un framework.
- [x] **Envío de correo**: [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) vía SMTP, en vez de `mail()` nativo. Motivo: `mail()` depende de que el servidor tenga un MTA bien configurado y con muchos hostings compartidos los correos terminan en spam o no salen; PHPMailer usando SMTP (por ejemplo el relay de Google Workspace de ucol.mx, o el servidor SMTP que tenga la universidad) es mucho más confiable para algo tan importante como que cada alumno reciba su credencial. Sigue siendo una librería de un solo propósito, no un framework de aplicación.
- [x] **Hosting/servidor**: VPS personal (de la empresa del organizador) para producción; en desarrollo local se usa **Docker** (ver más abajo). El VPS implica acceso root/SSH, por lo que hay control total: se puede instalar PHP, MariaDB y Composer sin restricciones de un hosting compartido.
- [x] **Diseño/CSS**: [Tailwind CSS](https://tailwindcss.com/), compilado con el [Tailwind CLI standalone](https://tailwindcss.com/blog/standalone-cli) (binario ejecutable, sin instalar Node.js ni npm). Se compila una sola vez un archivo `.css` a partir de las clases usadas en el HTML/PHP de toda la app (Día Académico y Día Deportivo comparten el mismo archivo compilado) y ese `.css` estático es lo único que se sube al VPS — no corre ningún proceso de build en el servidor. Encaja con "sin frameworks de frontend" porque no agrega JavaScript ni dependencias en tiempo de ejecución.
- [x] **Entorno de desarrollo local**: Docker Compose (`app/docker-compose.yml`), con 4 servicios: `web` (PHP 8.2 + Apache + GD + PDO MySQL), `db` (MariaDB 11, importa `database/schema.sql` automáticamente la primera vez que se crea el volumen), `tailwind` (compila `assets/css/tailwind.css` en modo watch) y `adminer` (visor de base de datos, no forma parte de la app). Dos gotchas ya resueltos, documentados como comentarios en los propios Dockerfiles: (1) el binario standalone de Tailwind está enlazado contra glibc y no corre sobre Alpine/musl — se usa `debian:bookworm-slim`; (2) `tailwindcss --watch` (sin `=always`) se detiene en cuanto detecta el stdin cerrado, que es siempre el caso en un contenedor en segundo plano — hace falta `--watch=always`.

> Nota de instalación: con acceso root/SSH confirmado, se usará **Composer** para gestionar `pendalff/phpqrcode` y `PHPMailer` (más fácil de mantener/actualizar que vendorizar archivos a mano). Esto se define en el Prompt 2, junto con la configuración inicial de Tailwind CLI.

## Pendientes menores (no bloquean empezar a programar)

- [ ] Dominio/subdominio donde vivirá la app en el VPS (ej. `registro.b23.mx` o similar) — solo afecta configuración final de despliegue, no el código.
- [ ] Credenciales SMTP a usar en PHPMailer para el envío de credenciales: ¿se envía desde una cuenta de correo institucional (ucol.mx) o desde el dominio/correo del VPS/empresa? Definir antes del Prompt 6.
- [ ] Confirmar que el VPS tenga acceso HTTPS (certificado SSL) antes del evento — necesario porque los navegadores solo permiten acceso a la cámara (para leer el QR) en páginas servidas por HTTPS.
- [ ] El binario del Tailwind CLI standalone solo hace falta en el entorno donde se compile el CSS (máquina de quien programa, o un paso de CI) — no se instala en el VPS de producción, ahí solo se sube el `.css` ya compilado.

## Prompts

### Prompt 1 — Esquema de base de datos
```
Diseña y escribe el esquema de MariaDB en app/database/schema.sql para este sistema.
Tablas necesarias:
- alumnos: numero_cuenta (char(8), único), nombre_completo, grupo, correo_institucional,
  foto_path, tema_interes (texto libre del pre-registro), fecha_registro,
  credencial_generada (booleano), fecha_envio_credencial (nullable).
- asistencia: alumno (FK a alumnos, UNIQUE — una sola fila por alumno, ya que
  el evento es de un solo día), hora_entrada (datetime, se llena en el primer
  escaneo del día), punto_control_entrada, escaneado_por_entrada, hora_salida
  (datetime, nullable — se sobreescribe en cada escaneo posterior al primero,
  así que siempre refleja el último escaneo del día), punto_control_salida
  (nullable), escaneado_por_salida (nullable). "escaneado_por" identifica al
  maestro/staff que operó el escaneo, no es autoservicio.
- talleres: nombre, espacio, sesion ('ponencia' para 9:00-10:00, '1' o '2' para
  Sesión 1/Sesión 2 de 10:30-12:30), cupo_maximo, cupo_disponible, responsable.
  El Concurso del Conocimiento NO va en esta tabla: está fuera del sistema de
  cupo/inscripciones (se organiza aparte, ver registro-asistencia.md).
- inscripciones: alumno (FK), taller (FK), sesion, fecha_hora, origen (ENUM
  'previo' | 'orden_llegada' — distingue si fue asignado de antemano por el
  encargado del evento académico, o elegido el día del evento por orden de
  llegada), registrado_por (quién hizo la asignación: el propio alumno/maestro
  en el flujo de orden de llegada, o el encargado en el registro previo).
  Un alumno solo puede tener una inscripción por sesión (restricción única
  sobre alumno+sesion), sin importar el origen.
Usa InnoDB, claves foráneas, e índices en numero_cuenta y en las claves foráneas.
```

### Prompt 2 — Conexión a base de datos y configuración de Tailwind CSS
```
Dos cosas independientes para dejar listo el resto de los prompts:

1. Crea app/config/db.php: conexión PDO a MariaDB en modo excepción
   (ERRMODE_EXCEPTION), UTF-8, con las credenciales leídas de variables de
   entorno o de un archivo de configuración separado que NO se suba a
   control de versiones. No uses ningún ORM ni framework de acceso a datos.

2. Configura Tailwind CSS con el Tailwind CLI standalone (sin Node/npm):
   descarga el binario correspondiente al sistema operativo de desarrollo,
   crea un archivo fuente app/assets/css/input.css (el CLI descargado es
   v4.x — usa `@import "tailwindcss";`, ya NO las directivas @tailwind
   base/components/utilities de Tailwind v3), y compila a
   app/assets/css/tailwind.css (este archivo compilado sí se sube al VPS
   junto con el resto del código; el binario del CLI no). Este único
   archivo CSS se comparte entre app/registro, app/inscripciones y
   app/torneos — no generes un build distinto por módulo. Documenta en un
   comentario o README corto el comando exacto para recompilar en modo
   watch durante desarrollo.
```

### Prompt 3 — Formulario de pre-registro
```
Crea app/registro/public/index.php: formulario de pre-registro con los campos
definidos en registro-asistencia.md (nombre completo, número de cuenta, grupo,
correo institucional, foto tipo carnet, comentario de temas de interés).
Validación en el cliente con JavaScript puro (formato del número de cuenta,
campos obligatorios, tipo/tamaño de la foto) y en el servidor en PHP (nunca
confiar solo en la validación del cliente). Usa las clases de Tailwind CSS
(app/assets/css/tailwind.css, ver Prompt 2) para el diseño; sin frameworks JS.
```

### Prompt 4 — Guardado del pre-registro
```
Crea app/registro/includes/guardar-registro.php: recibe el POST del formulario,
valida en servidor, verifica que el número de cuenta no esté duplicado, guarda
la foto en una carpeta del servidor (no en la base de datos) y guarda el registro
en la tabla alumnos usando la conexión de app/config/db.php con consultas
preparadas (PDO, sin SQL concatenado).
```

### Prompt 5 — Generación de credencial digital y QR
```
Crea app/registro/includes/generar-credencial.php: genera un código QR que
codifica ÚNICAMENTE el numero_cuenta del alumno, y compone una imagen de
credencial VERTICAL (formato retrato, pensada para verse completa en la
pantalla de un celular — implementado a 1080×1920) con el logo institucional
(app/assets/img/logo/UdeC_2L izq Negro.png) + foto + nombre + grupo + QR.
Guarda la imagen resultante y marca credencial_generada = true en la base de datos.
Usa la librería phpqrcode (ver "Decisiones técnicas resueltas" en este documento).
```

### Prompt 6 — Envío de la credencial por correo
```
Crea app/registro/includes/enviar-credencial.php: envía la imagen de credencial
generada en el Prompt 5 al correo institucional del alumno, adjunta la imagen,
y registra fecha_envio_credencial. Dispara este envío automáticamente justo
después de un registro exitoso (no esperar al cierre del formulario).
Usa PHPMailer vía SMTP (ver "Decisiones técnicas resueltas" en este documento).
```

### Prompt 7 — App de escaneo (día del evento, operada por el maestro/staff)
```
Crea app/registro/public/escaneo.php: interfaz en JavaScript puro con Tailwind CSS
para el diseño, pensada para que la opere el MAESTRO/STAFF en el punto de control
(no es autoservicio del alumno), que activa la cámara del dispositivo y lee el
código QR de la credencial con jsQR
(ver "Decisiones técnicas resueltas" en este documento). Al leer un número de
cuenta válido:
1. Verifica que exista en la tabla alumnos.
2. Muestra en pantalla su foto y nombre para verificación visual del maestro.
3. Control de entrada/salida — busca si el alumno ya tiene una fila en la
   tabla asistencia:
   - Si NO existe: es su primer escaneo del día. Inserta una fila nueva con
     hora_entrada = ahora, punto_control_entrada y escaneado_por_entrada.
     Continúa al paso 4 (este es el único caso que dispara la asignación de
     ponencia/taller).
   - Si YA existe: es un escaneo posterior (segundo, tercero...). Actualiza
     ÚNICAMENTE hora_salida = ahora, punto_control_salida y
     escaneado_por_salida (sobreescribe lo que hubiera antes, para que
     siempre quede la hora del último escaneo del día). Muestra en pantalla
     "Salida registrada" con la hora, y NO continúa al paso 4 — no se
     repite la asignación de ponencia/taller.
4. (Solo en el primer escaneo) Consulta si el alumno ya tiene inscripciones
   con origen='previo' (asignadas de antemano por el encargado del evento
   académico, ver Prompt 10). Si ya tiene lugar para todas las sesiones
   (ponencia, Sesión 1, Sesión 2), muestra la confirmación de a dónde le
   toca ir y NO lo manda a elegir de nuevo. Si le falta alguna sesión por
   asignar, redirige a app/inscripciones/public/index.php pasando el
   numero_cuenta y qué sesiones le faltan.
La pantalla debe distinguir siempre con claridad si el escaneo se registró
como "Entrada" o como "Salida (actualizada)".
```

### Prompt 8 — Selección de ponencia/taller por orden de llegada
```
Crea app/inscripciones/public/index.php: recibe el numero_cuenta y las sesiones
pendientes desde app/registro/public/escaneo.php (puede ser una o varias de:
ponencia 9:00-10:00, Sesión 1, Sesión 2). Para cada sesión pendiente, muestra la
lista de talleres/ponencias con su cupo disponible actualizado (consulta a la
tabla talleres vía un endpoint PHP, refrescado con JavaScript puro — sin
frameworks JS), con clases de Tailwind CSS para el diseño, y permite elegir
uno. Deshabilita en la interfaz los talleres sin cupo disponible. El Concurso
del Conocimiento no aparece aquí: está fuera de este sistema (ver contexto de
este documento).
```

### Prompt 9 — Backend de inscripción (control de concurrencia)
```
Crea app/inscripciones/includes/inscribir.php: recibe la elección de taller,
y dentro de una transacción SQL, descuenta el cupo de forma atómica
(por ejemplo UPDATE talleres SET cupo_disponible = cupo_disponible - 1
WHERE id = ? AND cupo_disponible > 0, comprobando filas afectadas antes de
insertar en inscripciones) para evitar sobrecupo cuando dos alumnos se
inscriben al mismo taller al mismo tiempo. Guarda origen='orden_llegada' y
registrado_por. Respeta la restricción de una sola inscripción por alumno y
sesión. Este mismo backend lo reutiliza el Prompt 10 para las asignaciones
previas (con origen='previo').
```

### Prompt 10 — Herramienta del encargado para asignación previa
```
Crea una interfaz (definir ruta, ej. app/inscripciones/admin/), con clases de
Tailwind CSS para el diseño, para que el encargado de organizar el evento
académico asigne de antemano, durante el periodo de clases (antes del 1 de
octubre), la ponencia/taller de un alumno.
Debe: buscar al alumno por número de cuenta o nombre, mostrar las
sesiones pendientes de asignar, listar talleres con cupo disponible por
sesión, y al confirmar, reutilizar el backend del Prompt 9 con
origen='previo' y registrado_por = identificador del encargado. Requiere un
mecanismo simple de acceso (no es de uso público) — definir con el equipo
qué tan simple (contraseña compartida vs. usuario/contraseña por persona).
```

### Prompt 11 — Reporte de asistencia y ocupación (opcional)
```
Crea una vista simple (definir ruta), con clases de Tailwind CSS para el
diseño, que muestre: total de alumnos con entrada registrada, cuántos de
ellos ya tienen también hora de salida (es decir, ya se marcharon) y
ocupación (inscritos/cupo) por taller y sesión, distinguiendo cuántas
inscripciones son de origen='previo' vs 'orden_llegada', consultando
directamente las tablas asistencia e inscripciones. Sin autenticación compleja;
acceso restringido solo si se define un mecanismo simple de acceso para el
staff organizador.
```

## Contexto para pegar antes del Prompt 12 (Día Deportivo)

```
Ahora voy a extender el mismo sistema para el Día Deportivo (Sábado 3 de
Octubre) de la Semana Cultural del Bachillerato 23, sede Polideportivo de
San Pedrito. Sigue viviendo dentro de la carpeta app/ y reutiliza la misma
base de datos, la conexión app/config/db.php y las mismas decisiones de
stack y librerías ya fijadas en este documento (PHP puro, JS puro, MariaDB,
phpqrcode, jsQR, PHPMailer vía SMTP, Tailwind CSS para el diseño — nada de
frameworks de aplicación). Usa el mismo archivo compilado
app/assets/css/tailwind.css del Prompt 2, no generes uno nuevo.

Esto es un sistema de datos distinto al del Día Académico: la unidad no es
un alumno individual sino un EQUIPO de 10 integrantes, mezclando alumnos y
padres de familia (los padres no tienen número de cuenta). No reutilices ni
modifiques las tablas alumnos/asistencia/talleres/inscripciones del Día
Académico — son independientes, aunque compartan base de datos.

Estructura nueva dentro del proyecto:
- app/torneos/public/     -> formulario de inscripción de equipo y app de
                              escaneo de asistencia el día del evento.
- app/torneos/includes/    -> guardado del equipo, generación de credenciales
                              QR por integrante y envío por correo.
- app/torneos/admin/       -> reportes internos (sin autenticación compleja).

Reglas de negocio (ver torneos-deportivos.md para el detalle completo):
- Fecha límite de inscripción de equipos: 30 de septiembre de 2026.
- Cada equipo tiene exactamente 10 integrantes: alumnos y padres de familia
  mezclados (proporción mínima aún sin definir), uno de ellos marcado como
  capitán.
- Cada integrante recibe su propia credencial/ticket con QR (sin foto, a
  diferencia de la credencial del Día Académico) que codifica ÚNICAMENTE un
  código de participante generado por la app — nunca datos personales
  dentro del QR.
- El escaneo de asistencia el 3 de octubre (entrada concentrada en
  07:00–07:30, Polideportivo de San Pedrito; salida en cualquier momento
  posterior) lo opera el maestro/staff en el punto de control, igual que en
  el Día Académico — no es autoservicio del participante. Mismo criterio de
  entrada/salida: el primer escaneo del día registra la entrada, cualquier
  escaneo posterior solo sobreescribe la salida con la hora más reciente.
- A diferencia del Día Académico, aquí el escaneo NO desencadena una
  elección de taller: el equipo y su primer partido ya quedaron definidos
  por las llaves publicadas el 2 de octubre (fuera del alcance de estos
  prompts).

Si te falta información para tomar una decisión de negocio (no técnica),
pregúntame en vez de asumir.
```

### Prompt 12 — Esquema de base de datos para equipos
```
Agrega a app/database/schema.sql las tablas para la inscripción de equipos
del Día Deportivo:
- equipos: id, deporte (ENUM 'futbol_rapido','voleibol','quemados'),
  nombre_equipo, color_camisa, fecha_registro. UNIQUE sobre
  (deporte, color_camisa) para que no se repita el color dentro del mismo
  deporte (sí puede repetirse entre deportes distintos).
- integrantes_equipo: id, equipo (FK a equipos), nombre_completo,
  tipo (ENUM 'alumno','padre'), numero_cuenta (char(8), nullable — solo
  aplica si tipo='alumno'), grupo (nullable), parentesco (nullable, solo si
  tipo='padre'), telefono, correo, es_capitan (booleano),
  codigo_participante (varchar, único, generado por la app — NO reutiliza
  numero_cuenta porque los padres no tienen uno), credencial_generada
  (booleano), fecha_envio_credencial (nullable).
- asistencia_equipos: id, integrante (FK a integrantes_equipo, UNIQUE — una
  sola fila por integrante), hora_entrada (datetime, se llena en el primer
  escaneo del día), punto_control_entrada, escaneado_por_entrada, hora_salida
  (datetime, nullable — se sobreescribe en cada escaneo posterior al primero,
  igual que en la tabla asistencia del Día Académico), punto_control_salida
  (nullable), escaneado_por_salida (nullable).
Usa InnoDB, claves foráneas, e índices en codigo_participante y en las
claves foráneas. No toques las tablas del Día Académico (alumnos,
asistencia, talleres, inscripciones): son independientes.
```

### Prompt 13 — Formulario de inscripción de equipo
```
Crea app/torneos/public/inscripcion.php: formulario de inscripción de
equipo con los campos de torneos-deportivos.md — deporte, nombre del
equipo, color de camisa (el select solo debe ofrecer los colores del
catálogo que sigan disponibles para el deporte elegido, consultando los
equipos ya registrados en ese deporte) y los 10 integrantes, uno marcado
como capitán, cada uno con nombre completo, tipo (alumno/padre), número de
cuenta y grupo si es alumno, parentesco si es padre, y teléfono/correo del
capitán. Validación en JavaScript puro: exactamente 10 integrantes, al
menos un alumno y un padre, color de camisa no repetido dentro del mismo
deporte. Usa las clases de Tailwind CSS (app/assets/css/tailwind.css,
mismo archivo compilado que el resto de la app, ver Prompt 2) para el
diseño; sin frameworks JS. El formulario deja de aceptar envíos a partir
del 30 de septiembre 23:59 (fecha límite de inscripción).
```

### Prompt 14 — Guardado de la inscripción del equipo
```
Crea app/torneos/includes/guardar-equipo.php: recibe el POST del
formulario, repite en el servidor las mismas validaciones del Prompt 13
(nunca confiar solo en el cliente), genera un codigo_participante único
para cada uno de los 10 integrantes, y guarda equipo + integrantes en una
sola transacción SQL (todo o nada) usando la conexión de app/config/db.php
con consultas preparadas (PDO, sin SQL concatenado).
```

### Prompt 15 — Generación de credencial/QR por integrante
```
Crea app/torneos/includes/generar-credenciales-equipo.php: para cada uno de
los 10 integrantes de un equipo recién guardado, genera con phpqrcode un
código QR que codifica ÚNICAMENTE su codigo_participante (igual de simple
que el QR de alumno del Día Académico) y compone un ticket/credencial
sencillo (nombre, equipo, deporte, color de camisa, QR — sin foto, a
diferencia de la credencial del Día Académico). Marca
credencial_generada = true por integrante.
```

### Prompt 16 — Envío de credenciales del equipo por correo
```
Crea app/torneos/includes/enviar-credenciales-equipo.php: envía por
PHPMailer vía SMTP el ticket generado en el Prompt 15 a cada integrante que
tenga correo capturado. Dispara este envío automáticamente justo después
de guardar el equipo (Prompt 14), igual que la credencial del Día
Académico. Registra fecha_envio_credencial por integrante.
```

### Prompt 17 — App de escaneo de asistencia en el Polideportivo
```
Crea app/torneos/public/escaneo.php: interfaz en JavaScript puro con
Tailwind CSS para el diseño, para que la opere el maestro/staff en el punto
de control del Polideportivo de San Pedrito (bloque 07:00–07:30 de la
matriz de itinerario del Día Deportivo), reutilizando jsQR para leer el QR
del ticket. Al leer un
codigo_participante válido:
1. Verifica que exista en integrantes_equipo y muestra en pantalla su
   nombre, equipo y deporte para verificación visual del maestro.
2. Control de entrada/salida — mismo criterio que el Prompt 7 del Día
   Académico: busca si el integrante ya tiene una fila en
   asistencia_equipos.
   - Si NO existe: inserta una fila nueva con hora_entrada = ahora,
     punto_control_entrada y escaneado_por_entrada.
   - Si YA existe: actualiza ÚNICAMENTE hora_salida = ahora,
     punto_control_salida y escaneado_por_salida (sobreescribe lo que
     hubiera antes, para que siempre quede la hora del último escaneo).
La pantalla debe distinguir siempre con claridad si el escaneo se registró
como "Entrada" o como "Salida (actualizada)". A diferencia del escaneo del
Día Académico, aquí NO hay paso siguiente de elegir taller: el equipo y su
partido ya están definidos por las llaves publicadas el 2 de octubre.
```

### Prompt 18 — Reporte de asistencia por equipo (opcional)
```
Crea una vista simple (definir ruta, ej. app/torneos/admin/asistencia.php),
con clases de Tailwind CSS para el diseño, que muestre, por deporte y
equipo, cuántos de los 10 integrantes ya tienen hora_entrada (para que el
staff sepa qué equipos están completos antes de arrancar cada ronda) y
cuántos ya tienen también hora_salida (para saber quién sigue en el
Polideportivo al final de la jornada). Sin autenticación compleja; mismo
criterio de acceso simple que el Prompt 11 del Día Académico.
```

## Pendientes menores — Día Deportivo (no bloquean empezar a programar)

- [ ] Proporción mínima de padres por equipo (regla de "mezcla" concreta) — afecta la validación del Prompt 13.
- [ ] Si el número de cuenta es obligatorio para todos los integrantes tipo "alumno" o si se permite inscribir sin haberse pre-registrado antes en el Día Académico.
- [ ] Si el correo es obligatorio para los padres de familia (afecta si todos reciben ticket por correo o si algunos necesitan otra vía).
- [ ] Catálogo cerrado de colores de camisa disponibles para el select del Prompt 13.

## Estado

- [ ] Prompt 1 — Esquema de base de datos
- [ ] Prompt 2 — Conexión a base de datos
- [ ] Prompt 3 — Formulario de pre-registro
- [ ] Prompt 4 — Guardado del pre-registro
- [ ] Prompt 5 — Generación de credencial digital y QR
- [ ] Prompt 6 — Envío de la credencial por correo
- [ ] Prompt 7 — App de escaneo (día del evento, operada por el maestro/staff)
- [ ] Prompt 8 — Selección de ponencia/taller por orden de llegada
- [ ] Prompt 9 — Backend de inscripción (control de concurrencia)
- [ ] Prompt 10 — Herramienta del encargado para asignación previa
- [ ] Prompt 11 — Reporte de asistencia y ocupación (opcional)
- [ ] Prompt 12 — Esquema de base de datos para equipos (Día Deportivo)
- [ ] Prompt 13 — Formulario de inscripción de equipo
- [ ] Prompt 14 — Guardado de la inscripción del equipo
- [ ] Prompt 15 — Generación de credencial/QR por integrante
- [ ] Prompt 16 — Envío de credenciales del equipo por correo
- [ ] Prompt 17 — App de escaneo de asistencia en el Polideportivo
- [ ] Prompt 18 — Reporte de asistencia por equipo (opcional)
