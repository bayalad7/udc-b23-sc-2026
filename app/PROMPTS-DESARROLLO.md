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
- app/registro/      -> pre-registro de alumnos y generación de credencial digital
                         (foto + datos + QR).
- app/inscripciones/  -> asignación de taller/ponencia con cupo limitado, inmediatamente
                         después de un escaneo exitoso en app/asistencias.
- app/asistencias/    -> app de escaneo QR y control de entrada/salida, unificada para
                         los 3 días del evento (Día Académico, Día Cultural, Día Deportivo).
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
- [x] **Módulo de escaneo de asistencia — unificado para los 3 días**: en vez de duplicar la app de escaneo dentro de `app/registro/` (Día Académico) y `app/torneos/` (Día Deportivo), vive en una carpeta propia `app/asistencias/` (ver Prompt 7 revisado más abajo), con un único `escaneo.php` parametrizado por `evento` (`academico` | `cultural` | `deportivo`). Motivo: el control de entrada/salida es la misma lógica los 3 días (ver [Control de entrada y salida](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md#control-de-entrada-y-salida)). `app/registro/` y `app/torneos/` mantienen el pre-registro/inscripción de equipo, pero ya no incluyen su propio `escaneo.php`.
- [x] **`asistencias_generales` (entrada/salida del día) es una tabla aparte de "a qué asiste"** — son dos conceptos distintos y no deben mezclarse: (a) **asistencia general**: ¿ya entró/salió del plantel/evento ese día?, solo alumnos (`id_alumno` FK a `alumnos`, `dia` ENUM `'academico'|'cultural'|'deportivo'`); y (b) **participación/asistencia específica a cada evento o equipo**, que ya trae su propio control de entrada/salida en la MISMA fila: `inscripciones` (ponencias y talleres, individuales por alumno) y `equipos`/`integrantes` (concursos y torneos por equipo — ver siguiente punto). Así un alumno puede tener, el mismo día, una fila en `asistencias_generales` (¿llegó al plantel?) MÁS una fila en `inscripciones` por cada evento al que está inscrito (¿llegó a ESE taller/ponencia?) — "todas las combinaciones posibles".
- [x] **`equipos`/`integrantes` cubre TODO lo que es por equipo, no solo el Día Deportivo**: Concurso del Conocimiento (Día Académico), Concurso de Talentos (Día Cultural) y los 3 torneos deportivos (Día Deportivo) — de ahí que `equipos` tenga columna `dia`. `eventos`/`inscripciones`, en cambio, es solo para lo individual por alumno (ponencias y talleres). Un integrante (alumno, padre o madre) tiene su propio `hora_entrada`/`hora_salida` directo en `integrantes` (nace en NULL desde que se inscribió el equipo, antes del evento) — el escaneo el día del evento actualiza esa misma fila, nunca inserta una nueva. `id_alumno` en `integrantes` es siempre el alumno de la familia (el "ancla" del equipo), nunca un id propio de padre/madre: si el alumno X participa junto con su papá y su mamá, son 3 filas — `(equipo, X, 'alumno')`, `(equipo, X, 'padre')`, `(equipo, X, 'madre')` — y en las de tipo padre/madre, la columna `nombre` es el nombre de esa persona, no el del alumno.
- [x] **`eventos`/`inscripciones` no validan cruces de horario**: no hay columna de sesión/franja horaria — un alumno puede inscribirse a cuantos eventos quiera sin que el sistema valide que no se crucen en el mismo horario. Es responsabilidad de quien arma el catálogo de eventos evitar que se crucen.
- [x] **Día Cultural reutiliza alumnos y credencial del Día Académico**: no hay un registro/credencial aparte para el Día Cultural — es el mismo alumno con el mismo QR (mismo `numero_cuenta`). Lo único que cambia es que se genera una fila nueva en `asistencias_generales` por día (columna `dia`, ver esquema), porque la entrada/salida sí es independiente por día.
- [x] **Protección de acceso a `app/asistencias` (para que el alumnado no la descubra ni la use)**: no es una URL secreta ni HTTP Basic Auth — es una contraseña compartida guardada (hasheada con `password_hash`) en la tabla `sistema` (una sola fila, columna `clave_acceso`). `evento.php` la pide antes de mostrar cualquier otra cosa; al acertarla marca la sesión del turno como autorizada (ver `app/asistencias/includes/sesion.php` y `verificar-clave.php`). Se cambia actualizando esa fila (por ejemplo desde Adminer en desarrollo) — no requiere tocar `.htaccess` ni redesplegar.

> Nota de instalación: con acceso root/SSH confirmado, se usará **Composer** para gestionar `pendalff/phpqrcode` y `PHPMailer` (más fácil de mantener/actualizar que vendorizar archivos a mano). Esto se define en el Prompt 2, junto con la configuración inicial de Tailwind CLI.

## Pendientes menores (no bloquean empezar a programar)

- [ ] **`equipos` tiene un error de sintaxis SQL**: la columna se llama `id_alumno_capitan`, pero `CONSTRAINT fk_equipos_alumno_capitan FOREIGN KEY (id_alumno) REFERENCES alumnos(id)` referencia `id_alumno`, que no existe en esa tabla — el `CREATE TABLE` fallaría tal cual está. Hace falta cambiar esa línea a `FOREIGN KEY (id_alumno_capitan)` antes de poder levantar el esquema completo.
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
- asistencias_generales: asistencia GENERAL (entrada/salida) del día, SOLO
  alumnos — no confundir con "a qué asiste", que vive en inscripciones y en
  equipos/integrantes (ver más abajo, y Prompt 12). id_alumno (FK a alumnos),
  dia (ENUM 'academico'|'cultural'|'deportivo' — el mismo alumno asiste
  académico y cultural con la misma credencial/QR, así que la clave primaria
  es id_alumno+dia, no solo id_alumno), hora_entrada (datetime, se llena en
  el primer escaneo de ESE día), punto_control_entrada, escaneado_por_entrada,
  hora_salida (datetime, nullable — se sobreescribe en cada escaneo posterior
  al primero DEL MISMO día, así que siempre refleja el último escaneo de ese
  día), punto_control_salida (nullable), escaneado_por_salida (nullable).
  "escaneado_por" identifica al maestro/staff que operó el escaneo, no es
  autoservicio.
- eventos: catálogo de ponencias y talleres, individuales por alumno (sin
  equipo) — Día Académico o Día Cultural. dia (ENUM 'academico'|'cultural'),
  tipo (ENUM 'ponencia'|'taller'), facilitador, nombre, descripcion, espacio,
  cupo_maximo, cupo_disponible, responsable. Sin columna de sesión/franja
  horaria: el sistema no valida cruces de horario entre eventos.
- inscripciones: QUÉ evento tiene cada alumno, con su propio control de
  entrada/salida A ESE evento en la misma fila (no solo "está inscrito", sino
  "ya llegó a esa ponencia/taller"). id_evento (FK a eventos), id_alumno (FK
  a alumnos), origen (ENUM 'previo' | 'orden_llegada' — distingue si fue
  asignado de antemano por el encargado, o elegido el día del evento por
  orden de llegada), registrado_por (quién hizo la asignación),
  hora_entrada/punto_control_entrada/escaneado_por_entrada (nullable: la fila
  puede existir desde antes del evento si origen='previo', sin que la persona
  haya llegado todavía), hora_salida/punto_control_salida/escaneado_por_salida
  (nullable). Clave primaria (id_evento, id_alumno) — un alumno puede estar
  inscrito a varios eventos sin restricción de horario.
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

### Prompt 7 (revisado) — Módulo unificado de asistencias (escaneo QR, los 3 días)
```
Este prompt reemplaza al Prompt 7 original (que creaba app/registro/public/escaneo.php
solo para el Día Académico) y absorbe también lo que iba a ser el Prompt 17
(app/torneos/public/escaneo.php para el Día Deportivo): ahora hay UNA sola app de
escaneo para los 3 días, en app/asistencias/, en vez de duplicarla por módulo.
Ver "Decisiones técnicas resueltas" en este documento (Módulo de escaneo unificado,
Día Cultural, Protección de la URL).

1. Protección de acceso (antes que nada, porque esta es la app más sensible del
   sistema — nunca debe quedar al alcance del alumnado): tabla `sistema` (una
   fila, columna `clave_acceso` con el hash de `password_hash()`) y
   app/asistencias/public/evento.php, que pide esa contraseña antes de mostrar
   cualquier otra cosa (POST a app/asistencias/includes/verificar-clave.php,
   verificado con `password_verify()`) y marca la sesión del turno como
   autorizada al acertarla (ver app/asistencias/includes/sesion.php).

2. Una vez autorizado, evento.php muestra un formulario para elegir el día a
   escanear ('academico' | 'cultural' | 'deportivo'), el nombre de quien va a
   operar el escaneo y el punto de control — los tres se guardan en la sesión
   del turno (POST a includes/iniciar-turno.php) y de ahí en adelante viajan
   con la sesión, no por la URL. Un enlace "Cambiar turno" (limpia día/operador/
   punto de control, sigue autorizado) y "Cerrar sesión" (limpia todo, vuelve a
   pedir la contraseña) permiten pasar de un turno a otro en el mismo
   dispositivo.

3. Crea app/asistencias/public/escaneo.php: interfaz en JavaScript puro con
   Tailwind CSS para el diseño, pensada para que la opere el MAESTRO/STAFF en el
   punto de control (no es autoservicio del alumno/participante), que activa la
   cámara del dispositivo y lee el código QR con jsQR (ver "Decisiones técnicas
   resueltas"). El evento a escanear NO se manda desde el cliente: el backend
   lo toma de la sesión del turno (paso 2), así no se puede falsear.

   Para evento='academico' o evento='cultural' (QR = numero_cuenta de alumno):
   a. Verifica que numero_cuenta exista en la tabla alumnos.
   b. Muestra en pantalla su foto y nombre para verificación visual del maestro.
   c. Control de entrada/salida — busca si ya existe una fila en
      asistencias_generales para ese id_alumno Y ese valor de dia
      (evento='academico' -> dia='academico', evento='cultural' ->
      dia='cultural'; son independientes, el mismo alumno puede ya tener fila
      de 'academico' y aun así ser su primer escaneo de 'cultural'):
      - Si NO existe (para ese alumno+dia): primer escaneo de ese día. Inserta
        una fila nueva con id_alumno, dia, hora_entrada = ahora,
        punto_control_entrada y escaneado_por_entrada. Si evento='academico',
        continúa al paso d (este es el único caso que dispara la asignación
        de ponencia/taller; evento='cultural' nunca la dispara).
      - Si YA existe: escaneo posterior del mismo día. Actualiza ÚNICAMENTE
        hora_salida = ahora, punto_control_salida y escaneado_por_salida
        (sobreescribe lo que hubiera antes). Muestra "Salida registrada" con
        la hora, y no continúa al paso d.
   d. (Solo evento='academico', solo en el primer escaneo del día) Consulta si
      el alumno ya tiene alguna fila en inscripciones (sea cual sea el
      origen). Si ya tiene al menos una, muestra la confirmación de a dónde
      le toca ir (nombre y espacio de cada evento). Si no tiene ninguna,
      redirige a app/inscripciones/public/index.php pasando el numero_cuenta
      — ya no hay un número fijo de sesiones que cubrir (ver "Decisiones
      técnicas resueltas": eventos/inscripciones no valida cruces de
      horario), así que aquí solo se distingue "ya tiene algo" de "nada
      todavía", no cuántas.

   Para evento='deportivo' (QR = codigo_participante de integrantes):
   a. Verifica que codigo_participante exista en integrantes (junto con su
      equipo) y muestra en pantalla su nombre, equipo y tipo de equipo para
      verificación visual — alumnos, padres y madres por igual: aquí sí se
      lleva su entrada/salida (a diferencia de asistencias_generales, que es
      solo alumnos).
   b. Control de entrada/salida sobre la fila de integrantes de esa persona
      (id_equipo + id_alumno + tipo): como la fila ya existe desde que se
      inscribió el equipo (hora_entrada nace en NULL), aquí SIEMPRE es
      UPDATE, nunca INSERT. Si hora_entrada es NULL: actualiza hora_entrada,
      punto_control_entrada y escaneado_por_entrada (esto fue la entrada). Si
      hora_entrada ya tiene valor: actualiza solo hora_salida,
      punto_control_salida y escaneado_por_salida (esto fue la salida). No
      hay paso siguiente de elegir taller ni equipo: ya están definidos desde
      la inscripción (Concurso del Conocimiento, Concurso de Talentos o
      torneo deportivo, según equipos.tipo) o, en el caso de los torneos, por
      las llaves publicadas el 2 de octubre.

La pantalla debe distinguir siempre con claridad si el escaneo se registró
como "Entrada" o como "Salida (actualizada)". Debe mostrar también, de forma
visible, para cuál de los 3 eventos está operando ese punto de control.
```

### Prompt 8 — Selección de ponencia/taller por orden de llegada
```
Crea app/inscripciones/public/index.php: recibe el numero_cuenta desde
app/asistencias/public/escaneo.php (solo cuando el alumno todavía no tiene
ninguna fila en inscripciones — ver Prompt 7 revisado, paso d). Muestra la
lista de eventos disponibles (tipo='ponencia'|'taller', dia='academico') con
su cupo disponible actualizado (consulta a la tabla eventos vía un endpoint
PHP, refrescado con JavaScript puro — sin frameworks JS), con clases de
Tailwind CSS para el diseño, y permite elegir uno o varios (ya no hay un
número fijo de sesiones que cubrir — ver "Decisiones técnicas resueltas":
eventos/inscripciones no valida cruces de horario). Deshabilita en la
interfaz los eventos sin cupo disponible. El Concurso del Conocimiento no
aparece aquí: se modela como equipo (equipos/integrantes), no como evento
individual — ver Prompt 12.
```

### Prompt 9 — Backend de inscripción (control de concurrencia)
```
Crea app/inscripciones/includes/inscribir.php: recibe la elección de
evento(s), y dentro de una transacción SQL, descuenta el cupo de forma
atómica (por ejemplo UPDATE eventos SET cupo_disponible = cupo_disponible - 1
WHERE id = ? AND cupo_disponible > 0, comprobando filas afectadas antes de
insertar en inscripciones) para evitar sobrecupo cuando dos alumnos se
inscriben al mismo evento al mismo tiempo. Guarda origen='orden_llegada' y
registrado_por (hora_entrada/punto_control_entrada/escaneado_por_entrada se
quedan NULL hasta que la persona realmente llegue a ESE evento — no
confundir con la inscripción). Este mismo backend lo reutiliza el Prompt 10
para las asignaciones previas (con origen='previo').
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

## Contexto para pegar antes del Prompt 12 (equipos — los 3 días)

```
Ahora voy a extender el mismo sistema con todo lo que se organiza por EQUIPO
en vez de individualmente por alumno: el Concurso del Conocimiento (Día
Académico), el Concurso de Talentos (Día Cultural) y los 3 torneos
deportivos — Fútbol Rápido, Voleibol, Quemados — (Día Deportivo, sede
Polideportivo de San Pedrito). Sigue viviendo dentro de la carpeta app/ y
reutiliza la misma base de datos, la conexión app/config/db.php y las
mismas decisiones de stack y librerías ya fijadas en este documento (PHP
puro, JS puro, MariaDB, phpqrcode, jsQR, PHPMailer vía SMTP, Tailwind CSS
para el diseño — nada de frameworks de aplicación). Usa el mismo archivo
compilado app/assets/css/tailwind.css del Prompt 2, no generes uno nuevo.

Esto es un sistema de datos distinto al de eventos/inscripciones (Prompt 1):
la unidad no es un alumno individual sino un EQUIPO de integrantes,
mezclando alumnos y padres/madres de familia (los padres no tienen número
de cuenta). No reutilices ni modifiques las tablas alumnos/eventos/
inscripciones — son independientes, aunque compartan base de datos.

Estructura nueva dentro del proyecto:
- app/torneos/public/     -> formulario de inscripción de equipo (torneos
                              deportivos; el Concurso del Conocimiento y el
                              Concurso de Talentos pueden vivir en su propia
                              carpeta o reutilizar ésta, a definir). El
                              escaneo de asistencia del día del evento NO
                              vive aquí: ver app/asistencias/ (módulo
                              unificado para los 3 días, Prompt 7 revisado).
- app/torneos/includes/    -> guardado del equipo, generación de credenciales
                              QR por integrante y envío por correo.
- app/torneos/admin/       -> reportes internos (sin autenticación compleja).

Reglas de negocio (ver torneos-deportivos.md para el detalle de los torneos
deportivos; el Concurso del Conocimiento y el Concurso de Talentos siguen
sin documento de planificación propio — pendiente):
- Fecha límite de inscripción de equipos de torneos deportivos: 30 de
  septiembre de 2026 (confirmar si aplica igual al Concurso del Conocimiento
  y al Concurso de Talentos).
- Cada equipo de torneo deportivo tiene exactamente 10 integrantes: alumnos
  y padres/madres de familia mezclados (proporción mínima aún sin definir),
  uno de ellos marcado como capitán. Tamaño de equipo del Concurso del
  Conocimiento y del Concurso de Talentos: pendiente definir.
- Cada integrante recibe su propia credencial/ticket con QR (sin foto, a
  diferencia de la credencial del Día Académico) que codifica ÚNICAMENTE un
  código de participante generado por la app — nunca datos personales
  dentro del QR.
- El escaneo de asistencia lo opera el maestro/staff en el punto de control,
  igual que en eventos/inscripciones — no es autoservicio del participante.
  Mismo criterio de entrada/salida: el primer escaneo del día registra la
  entrada, cualquier escaneo posterior solo sobreescribe la salida con la
  hora más reciente. A diferencia de ponencias/talleres, aquí el escaneo NO
  desencadena una elección: el equipo y, en el caso de los torneos
  deportivos, su primer partido, ya quedaron definidos de antemano (llaves
  publicadas el 2 de octubre para los torneos — fuera del alcance de estos
  prompts).

Si te falta información para tomar una decisión de negocio (no técnica),
pregúntame en vez de asumir.
```

### Prompt 12 — Esquema de base de datos para equipos
```
Agrega a app/database/schema.sql las tablas para todo lo que se organiza por
equipo (Concurso del Conocimiento, Concurso de Talentos y torneos deportivos):
- equipos: id, dia (ENUM 'academico','cultural','deportivo'), tipo (ENUM
  'concurso','futbol_rapido','voleibol','quemados' — 'concurso' cubre tanto
  el Concurso del Conocimiento como el de Talentos, distinguibles por dia),
  nombre, id_alumno_capitan (FK a alumnos — el capitán es un alumno),
  color_camisa (nullable — solo aplica a los torneos deportivos), fecha_registro.
  UNIQUE sobre (dia, tipo, color_camisa) para que no se repita el color
  dentro del mismo tipo de equipo del mismo día.
- integrantes: id_equipo (FK a equipos), id_alumno (FK a alumnos — SIEMPRE
  el alumno de la familia, el "ancla": si un padre o madre participa, su fila
  reutiliza el id_alumno de su hijo/a, no un id propio), tipo (ENUM
  'alumno','padre','madre'), nombre (el de la persona de ESA fila: el del
  alumno si tipo='alumno', el del padre/madre si no), codigo_participante
  (varchar, único, generado por la app — NO reutiliza numero_cuenta porque
  los padres no tienen uno), hora_entrada/punto_control_entrada/
  escaneado_por_entrada (nullable — la fila existe desde que se inscribió el
  equipo, antes del evento, sin que la persona haya llegado todavía),
  hora_salida/punto_control_salida/escaneado_por_salida (nullable). Clave
  primaria (id_equipo, id_alumno, tipo).
No hace falta una tabla de asistencia aparte para equipos: el control de
entrada/salida vive directo en integrantes (ver arriba) — a diferencia de
asistencias_generales (solo alumnos, Prompt 1), aquí SÍ se lleva la de
padres y madres.
Usa InnoDB, claves foráneas, e índices en codigo_participante y en las
claves foráneas. No toques las tablas alumnos/eventos/inscripciones/
asistencias_generales: son independientes.
```

### Prompt 13 — Formulario de inscripción de equipo (torneos deportivos)
```
Crea app/torneos/public/inscripcion.php: formulario de inscripción de
equipo con los campos de torneos-deportivos.md — tipo de torneo
(futbol_rapido/voleibol/quemados), nombre del equipo, color de camisa (el
select solo debe ofrecer los colores del catálogo que sigan disponibles
para ese tipo dentro de dia='deportivo', consultando los equipos ya
registrados con ese tipo) y los 10 integrantes, uno marcado como capitán
(el capitán es siempre un alumno — equipos.id_alumno_capitan es FK a
alumnos), cada uno con nombre completo, tipo (alumno/padre/madre), número
de cuenta y grupo si es alumno, y teléfono/correo del capitán. Validación
en JavaScript puro: exactamente 10 integrantes, al menos un alumno y un
padre/madre, color de camisa no repetido dentro del mismo tipo de torneo.
Usa las clases de Tailwind CSS (app/assets/css/tailwind.css, mismo archivo
compilado que el resto de la app, ver Prompt 2) para el diseño; sin
frameworks JS. El formulario deja de aceptar envíos a partir del 30 de
septiembre 23:59 (fecha límite de inscripción).
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
[FUSIONADO con el Prompt 7 revisado — ver "Módulo unificado de asistencias" más
arriba.] La app de escaneo del Día Deportivo ya no es un archivo aparte en
app/torneos/: es app/asistencias/public/escaneo.php con ?evento=deportivo,
compartiendo interfaz, protección de acceso y punto de entrada con los otros
dos días. Este prompt se deja como referencia histórica de la lógica que ya
quedó incorporada al Prompt 7 revisado; no se ejecuta por separado.
```

### Prompt 18 — Reporte de asistencia por equipo (opcional)
```
Crea una vista simple (definir ruta, ej. app/torneos/admin/asistencia.php),
con clases de Tailwind CSS para el diseño, que muestre, por tipo de torneo y
equipo, cuántos de los 10 integrantes (alumnos, padres y madres por igual —
aquí sí se lleva asistencia de todos, a diferencia de asistencias_generales)
ya tienen hora_entrada y cuántos ya tienen también hora_salida (para saber
quién sigue en el Polideportivo al final de la jornada). Consulta la tabla
integrantes (su propio hora_entrada/hora_salida, ver Prompt 12) haciendo
JOIN con equipos. Sin autenticación compleja; mismo criterio de acceso
simple que el Prompt 11 del Día Académico.
```

## Pendientes menores — equipos (no bloquean empezar a programar)

- [ ] Proporción mínima de padres/madres por equipo (regla de "mezcla" concreta) — afecta la validación del Prompt 13.
- [ ] Si el número de cuenta es obligatorio para todos los integrantes tipo "alumno" o si se permite inscribir sin haberse pre-registrado antes en el Día Académico.
- [ ] Si el correo es obligatorio para los padres/madres de familia (afecta si todos reciben ticket por correo o si algunos necesitan otra vía).
- [ ] Catálogo cerrado de colores de camisa disponibles para el select del Prompt 13.
- [ ] Reglas de negocio del Concurso del Conocimiento y del Concurso de Talentos como equipo (tamaño de equipo, fecha límite de inscripción, si aplican los Prompts 13-16 tal cual o necesitan su propia interfaz) — torneos-deportivos.md solo documenta los torneos deportivos hoy.

## Estado

- [x] Prompt 1 — Esquema de base de datos (incluye ya la generalización de `asistencias_generales`/`eventos`/`inscripciones` y las tablas de equipos del Prompt 12 — ver `app/database/schema.sql`; **pendiente corregir un error de sintaxis en `equipos`, ver "Pendientes menores" arriba**)
- [X] Prompt 2 — Conexión a base de datos
- [X] Prompt 3 — Formulario de pre-registro
- [X] Prompt 4 — Guardado del pre-registro
- [X] Prompt 5 — Generación de credencial digital y QR
- [ ] Prompt 6 — Envío de la credencial por correo
- [x] Prompt 7 (revisado) — Módulo unificado de asistencias (escaneo QR + protección de acceso, los 3 días)
- [ ] Prompt 8 — Selección de ponencia/taller por orden de llegada
- [ ] Prompt 9 — Backend de inscripción (control de concurrencia)
- [ ] Prompt 10 — Herramienta del encargado para asignación previa
- [ ] Prompt 11 — Reporte de asistencia y ocupación (opcional)
- [x] Prompt 12 — Esquema de base de datos para equipos — fusionado en el Prompt 1, ver `app/database/schema.sql`
- [ ] Prompt 13 — Formulario de inscripción de equipo
- [ ] Prompt 14 — Guardado de la inscripción del equipo
- [ ] Prompt 15 — Generación de credencial/QR por integrante
- [ ] Prompt 16 — Envío de credenciales del equipo por correo
- [x] Prompt 17 — Fusionado con el Prompt 7 revisado (ya no es un prompt aparte)
- [ ] Prompt 18 — Reporte de asistencia por equipo (opcional)
