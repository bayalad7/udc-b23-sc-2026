# Roadmap de prompts — Sistema de Registro e Inscripciones

Lista de prompts secuenciales para construir el sistema descrito en [../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md](../01-Dia-Academico-Jueves-01-Oct/registro-asistencia.md). Están pensados para ejecutarse **uno por uno, en orden**, con quien vaya a programar (usándolos aquí con Claude Code o con cualquier otra herramienta/desarrollador).

## Contexto para pegar antes del primer prompt

```
Estoy construyendo el sistema de registro e inscripciones para el Día Académico
(Jueves 1 de Octubre) de la Semana Cultural del Bachillerato 23, Universidad de Colima.

Stack obligatorio: PHP puro (sin frameworks tipo Laravel/Symfony), JavaScript puro
(sin frameworks tipo React/Vue), base de datos MariaDB. Sin frameworks de frontend
ni backend. Se permiten librerías de un solo propósito y sin dependencias
(por ejemplo, para generar/leer códigos QR) cuando no es razonable escribir esa
funcionalidad desde cero — pero no frameworks de aplicación completos.

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

Cómo se asigna el alumno a una ponencia/taller (ver registro-asistencia.md):
- El escaneo el día del evento lo opera el MAESTRO/STAFF en el punto de control,
  no es autoservicio del alumno.
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

- [x] **Librería de QR — generación (servidor)**: [`phpqrcode`](https://github.com/t0k4rt/phpqrcode). Es un solo conjunto de archivos PHP sin dependencias, no requiere Composer ni acceso a línea de comandos en el servidor (solo se sube por FTP/panel de hosting junto con el resto del código), y usa la extensión GD que ya se necesita para componer la credencial. Encaja con "PHP puro": es una utilidad de un solo propósito, no un framework.
- [x] **Librería de QR — lectura (cámara, cliente)**: [`jsQR`](https://github.com/cozmo/jsQR). Un solo archivo JavaScript sin dependencias, decodifica QR a partir de los frames de video de la cámara vía `<canvas>`. Encaja con "JS puro" por la misma razón: es una utilidad, no un framework.
- [x] **Envío de correo**: [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) vía SMTP, en vez de `mail()` nativo. Motivo: `mail()` depende de que el servidor tenga un MTA bien configurado y con muchos hostings compartidos los correos terminan en spam o no salen; PHPMailer usando SMTP (por ejemplo el relay de Google Workspace de ucol.mx, o el servidor SMTP que tenga la universidad) es mucho más confiable para algo tan importante como que cada alumno reciba su credencial. Sigue siendo una librería de un solo propósito, no un framework de aplicación.
- [x] **Hosting/servidor**: VPS personal (de la empresa del organizador), no un servidor institucional. Esto implica acceso root/SSH, por lo que hay control total: se puede instalar PHP, MariaDB y Composer sin restricciones de un hosting compartido.

> Nota de instalación: con acceso root/SSH confirmado, se usará **Composer** para gestionar `phpqrcode` y `PHPMailer` (más fácil de mantener/actualizar que vendorizar archivos a mano). Esto se define en el Prompt 2.

## Pendientes menores (no bloquean empezar a programar)

- [ ] Dominio/subdominio donde vivirá la app en el VPS (ej. `registro.b23.mx` o similar) — solo afecta configuración final de despliegue, no el código.
- [ ] Credenciales SMTP a usar en PHPMailer para el envío de credenciales: ¿se envía desde una cuenta de correo institucional (ucol.mx) o desde el dominio/correo del VPS/empresa? Definir antes del Prompt 6.
- [ ] Confirmar que el VPS tenga acceso HTTPS (certificado SSL) antes del evento — necesario porque los navegadores solo permiten acceso a la cámara (para leer el QR) en páginas servidas por HTTPS.

## Prompts

### Prompt 1 — Esquema de base de datos
```
Diseña y escribe el esquema de MariaDB en app/database/schema.sql para este sistema.
Tablas necesarias:
- alumnos: numero_cuenta (char(8), único), nombre_completo, grupo, correo_institucional,
  foto_path, tema_interes (texto libre del pre-registro), fecha_registro,
  credencial_generada (booleano), fecha_envio_credencial (nullable).
- asistencia: alumno (FK a alumnos), fecha_hora del escaneo, punto_control,
  escaneado_por (identifica al maestro/staff que operó el escaneo, no es autoservicio).
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

### Prompt 2 — Conexión a base de datos
```
Crea app/config/db.php: conexión PDO a MariaDB en modo excepción (ERRMODE_EXCEPTION),
UTF-8, con las credenciales leídas de variables de entorno o de un archivo de
configuración separado que NO se suba a control de versiones. No uses ningún ORM
ni framework de acceso a datos.
```

### Prompt 3 — Formulario de pre-registro
```
Crea app/registro/public/index.php: formulario de pre-registro con los campos
definidos en registro-asistencia.md (nombre completo, número de cuenta, grupo,
correo institucional, foto tipo carnet, comentario de temas de interés).
Validación en el cliente con JavaScript puro (formato del número de cuenta,
campos obligatorios, tipo/tamaño de la foto) y en el servidor en PHP (nunca
confiar solo en la validación del cliente). Sin frameworks CSS ni JS.
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
credencial (usando la extensión GD nativa de PHP) con foto + nombre + grupo + QR.
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
Crea app/registro/public/escaneo.php: interfaz en JavaScript puro, pensada para que
la opere el MAESTRO/STAFF en el punto de control (no es autoservicio del alumno),
que activa la cámara del dispositivo y lee el código QR de la credencial con jsQR
(ver "Decisiones técnicas resueltas" en este documento). Al leer un número de
cuenta válido:
1. Verifica que exista en la tabla alumnos.
2. Muestra en pantalla su foto y nombre para verificación visual del maestro.
3. Si no tiene asistencia registrada aún hoy, la inserta en la tabla asistencia
   con fecha_hora, punto_control y escaneado_por.
4. Consulta si el alumno ya tiene inscripciones con origen='previo' (asignadas
   de antemano por el encargado del evento académico, ver Prompt 10). Si ya
   tiene lugar para todas las sesiones (ponencia, Sesión 1, Sesión 2), muestra
   la confirmación de a dónde le toca ir y NO lo manda a elegir de nuevo.
   Si le falta alguna sesión por asignar, redirige a
   app/inscripciones/public/index.php pasando el numero_cuenta y qué sesiones
   le faltan.
Maneja el caso de un QR ya escaneado antes (mostrar aviso, no duplicar asistencia).
```

### Prompt 8 — Selección de ponencia/taller por orden de llegada
```
Crea app/inscripciones/public/index.php: recibe el numero_cuenta y las sesiones
pendientes desde app/registro/public/escaneo.php (puede ser una o varias de:
ponencia 9:00-10:00, Sesión 1, Sesión 2). Para cada sesión pendiente, muestra la
lista de talleres/ponencias con su cupo disponible actualizado (consulta a la
tabla talleres vía un endpoint PHP, refrescado con JavaScript puro — sin
frameworks de frontend) y permite elegir uno. Deshabilita en la interfaz los
talleres sin cupo disponible. El Concurso del Conocimiento no aparece aquí:
está fuera de este sistema (ver contexto de este documento).
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
Crea una interfaz (definir ruta, ej. app/inscripciones/admin/) para que el
encargado de organizar el evento académico asigne de antemano, durante el
periodo de clases (antes del 1 de octubre), la ponencia/taller de un alumno.
Debe: buscar al alumno por número de cuenta o nombre, mostrar las
sesiones pendientes de asignar, listar talleres con cupo disponible por
sesión, y al confirmar, reutilizar el backend del Prompt 9 con
origen='previo' y registrado_por = identificador del encargado. Requiere un
mecanismo simple de acceso (no es de uso público) — definir con el equipo
qué tan simple (contraseña compartida vs. usuario/contraseña por persona).
```

### Prompt 11 — Reporte de asistencia y ocupación (opcional)
```
Crea una vista simple (definir ruta) que muestre: total de asistentes registrados
y ocupación (inscritos/cupo) por taller y sesión, distinguiendo cuántas
inscripciones son de origen='previo' vs 'orden_llegada', consultando
directamente las tablas asistencia e inscripciones. Sin autenticación compleja;
acceso restringido solo si se define un mecanismo simple de acceso para el
staff organizador.
```

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
