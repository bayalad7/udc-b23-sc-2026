<?php
declare(strict_types=1);

// La llave (bracket) de UNA competición: se arma de golpe con los equipos ya
// inscritos y luego se va resolviendo partido por partido. La aritmética del
// cuadro vive en includes/llaves.php; aquí solo se dibuja y se ofrecen los
// formularios.
//
// Dos estados posibles en esta página:
//   1. Sin llave todavía → tarjeta para generarla (orden por sorteo o por
//      orden de inscripción).
//   2. Con llave → el cuadro completo, una columna por ronda, con el modal de
//      captura de resultado en cada partido, más la zona para regenerarla o
//      borrarla.

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}
require __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/llaves.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=no_encontrado');
    exit;
}

$consulta = $pdo->prepare('SELECT id, dia, tipo, nombre, hora_inicio, hora_fin, max_equipos, tam_equipo FROM competiciones WHERE id = :id');
$consulta->execute(['id' => $id]);
$competicion = $consulta->fetch();
if ($competicion === false) {
    header('Location: ' . BASE_URL . '/admin/public/llaves.php?error=no_encontrado');
    exit;
}

$consultaEquipos = $pdo->prepare(
    'SELECT eq.id, eq.nombre, eq.color_camisa, eq.fecha_registro, a.nombre_completo AS capitan,
            (SELECT COUNT(*) FROM integrantes i WHERE i.id_equipo = eq.id) AS total_integrantes
     FROM equipos eq
     JOIN alumnos a ON a.id = eq.id_alumno_capitan
     WHERE eq.id_competicion = :id
     ORDER BY eq.fecha_registro, eq.id'
);
$consultaEquipos->execute(['id' => $id]);
$equipos = $consultaEquipos->fetchAll();

$porRonda = llavesPartidos($pdo, $id);
$hayLlave = $porRonda !== [];
$totalRondas = $hayLlave ? max(array_keys($porRonda)) : 0;
$campeon = llavesCampeon($porRonda);

// Los equipos inscritos DESPUÉS de generar la llave no aparecen en ella: el
// cuadro es una foto del momento en que se armó. Avisarlo es más útil que
// re-generar solo, que borraría los resultados ya capturados.
$equiposEnLlave = [];
foreach ($porRonda as $partidos) {
    foreach ($partidos as $partido) {
        foreach (['id_equipo_a', 'id_equipo_b'] as $lado) {
            if ($partido[$lado] !== null) {
                $equiposEnLlave[(int) $partido[$lado]] = true;
            }
        }
    }
}
$equiposFuera = $hayLlave
    ? array_values(array_filter($equipos, static fn (array $e): bool => !isset($equiposEnLlave[(int) $e['id']])))
    : [];

$diasLabel = ['academico' => 'Día Académico', 'cultural' => 'Día Cultural', 'deportivo' => 'Día Deportivo'];

$mensajesExito = [
    'llave_generada' => 'Llave generada con los equipos inscritos.',
    'resultado_guardado' => 'Resultado guardado.',
    'partido_actualizado' => 'Partido actualizado.',
];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;
$mensajesError = [
    'sin_equipos' => 'Se necesitan al menos 2 equipos inscritos para armar la llave.',
    'ya_existe' => 'Esta competición ya tiene una llave. Usa "Regenerar" si quieres rehacerla desde cero.',
    'partido_no_encontrado' => 'No se encontró el partido.',
    'ganador_invalido' => 'El ganador debe ser uno de los dos equipos del partido.',
    'partido_incompleto' => 'Todavía no se conocen los dos equipos de ese partido.',
    'es_bye' => 'Un pase directo (bye) no se juega: su ganador ya está definido.',
    'marcador_invalido' => 'Los marcadores deben ser números de 0 en adelante.',
    'hora_invalida' => 'La hora del partido no tiene un formato válido.',
    'error_generando' => 'No se pudo generar la llave. Revisa el log del servidor.',
    'error_guardando' => 'No se pudo guardar el partido. Revisa el log del servidor.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

/** Nombre visible de un lado del partido, según en qué estado esté esa casilla. */
function llaveEtiquetaLado(array $partido, string $lado, int $ronda): string
{
    $nombre = $partido[$lado === 'a' ? 'nombre_a' : 'nombre_b'];
    if ($nombre !== null) {
        return $nombre;
    }

    return llavesEsBye($partido) ? 'Pase directo (bye)' : ($ronda === 1 ? 'Sin rival' : 'Por definir');
}

layoutAdminAbrir('Llave — ' . $competicion['nombre'], 'llaves');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<a href="<?= BASE_URL ?>/admin/public/llaves.php" class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
    <?= icono('atras', 'h-3.5 w-3.5') ?>
    Volver a las llaves
</a>

<div class="mb-6 rounded-xl bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold"><?= htmlspecialchars($competicion['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5">
                    <?= icono('calendario', 'h-3.5 w-3.5 text-slate-400') ?>
                    <?= $diasLabel[$competicion['dia']] ?? $competicion['dia'] ?>
                </span>
                <span class="flex items-center gap-1.5">
                    <?= icono('reloj', 'h-3.5 w-3.5 text-slate-400') ?>
                    <?= substr((string) $competicion['hora_inicio'], 0, 5) ?>–<?= substr((string) $competicion['hora_fin'], 0, 5) ?>
                </span>
                <span class="flex items-center gap-1.5">
                    <?= icono('usuarios', 'h-3.5 w-3.5 text-slate-400') ?>
                    <?= count($equipos) ?> equipo<?= count($equipos) === 1 ? '' : 's' ?> inscrito<?= count($equipos) === 1 ? '' : 's' ?>
                </span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($hayLlave): ?>
            <a href="<?= BASE_URL ?>/admin/includes/exportar-llave.php?id=<?= (int) $competicion['id'] ?>"
               class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                <?= icono('descargar', 'h-3.5 w-3.5') ?>
                Descargar la llave (PDF)
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/public/competicion.php?id=<?= (int) $competicion['id'] ?>"
               class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                <?= icono('trofeo', 'h-3.5 w-3.5') ?>
                Ver la competición
            </a>
        </div>
    </div>

    <?php if ($campeon !== null): ?>
    <div class="mt-4 flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
        <?= icono('trofeo', 'h-4 w-4 shrink-0') ?>
        Campeón: <?= htmlspecialchars((string) $campeon['nombre'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <?php if ($equiposFuera !== []): ?>
    <div class="mt-4 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span>
            <?= count($equiposFuera) ?> equipo<?= count($equiposFuera) === 1 ? '' : 's' ?> se inscribió después de armar la llave y no aparece<?= count($equiposFuera) === 1 ? '' : 'n' ?> en ella:
            <?= htmlspecialchars(implode(', ', array_column($equiposFuera, 'nombre')), ENT_QUOTES, 'UTF-8') ?>.
            Para incluirlo<?= count($equiposFuera) === 1 ? '' : 's' ?> hay que regenerar la llave, lo que borra los resultados ya capturados.
        </span>
    </div>
    <?php endif; ?>
</div>

<?php if (!$hayLlave): ?>

    <div class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 text-base font-semibold">Armar la llave</h2>
        <p class="mb-4 text-sm text-slate-500">
            Se arma un cuadro de eliminación directa con los <?= count($equipos) ?> equipos inscritos.
            <?php if (count($equipos) >= 2):
                $cuadro = llavesTamanoCuadro(count($equipos));
                $byes = $cuadro - count($equipos);
            ?>
            Quedará un cuadro de <?= $cuadro ?> con <?= llavesRondasNecesarias(count($equipos)) ?> ronda<?= llavesRondasNecesarias(count($equipos)) === 1 ? '' : 's' ?><?= $byes > 0 ? ' y ' . $byes . ' pase' . ($byes === 1 ? '' : 's') . ' directo' . ($byes === 1 ? '' : 's') . ' (bye) en la primera ronda' : '' ?>.
            <?php endif; ?>
        </p>

        <?php if (count($equipos) < 2): ?>
        <p class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
            Se necesitan al menos 2 equipos inscritos para armar una llave. Hoy hay <?= count($equipos) ?>.
        </p>
        <?php else: ?>

        <form action="<?= BASE_URL ?>/admin/includes/generar-llaves.php" method="post" class="grid grid-cols-1 gap-4">
            <input type="hidden" name="id_competicion" value="<?= (int) $competicion['id'] ?>">

            <fieldset>
                <legend class="mb-2 text-sm font-medium">¿En qué orden entran los equipos al cuadro?</legend>
                <label class="mb-2 flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50">
                    <input type="radio" name="orden" value="sorteo" checked class="mt-0.5 cursor-pointer">
                    <span>
                        <span class="font-medium">Sorteo aleatorio</span>
                        <span class="block text-xs text-slate-500">Los equipos se revuelven al azar. Es lo normal cuando no hay ranking previo — se puede volver a sortear regenerando la llave.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50">
                    <input type="radio" name="orden" value="inscripcion" class="mt-0.5 cursor-pointer">
                    <span>
                        <span class="font-medium">Orden de inscripción</span>
                        <span class="block text-xs text-slate-500">El primero en inscribirse queda como sembrado 1, y por lo tanto es de los primeros en recibir un pase directo si sobran lugares.</span>
                    </span>
                </label>
            </fieldset>

            <div>
                <p class="mb-2 text-sm font-medium">Equipos que entran (<?= count($equipos) ?>)</p>
                <ul class="rounded-lg border border-slate-200">
                    <?php foreach ($equipos as $equipo): ?>
                    <li class="flex items-center justify-between border-b border-slate-100 px-3 py-2 text-sm last:border-0">
                        <span><?= htmlspecialchars($equipo['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-xs text-slate-400">
                            <?= (int) $equipo['total_integrantes'] ?> integrantes
                            <?php if ($equipo['color_camisa']): ?>
                            · <?= htmlspecialchars($equipo['color_camisa'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white active:bg-slate-700">
                <?= icono('sorteo', 'h-4 w-4') ?>
                Generar la llave
            </button>
        </form>

        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="mb-6 overflow-x-auto rounded-xl bg-white p-5 shadow-sm">
        <div class="flex gap-4">
            <?php foreach ($porRonda as $ronda => $partidos): ?>
            <div class="flex w-64 shrink-0 flex-col">
                <h3 class="mb-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <?= llavesNombreRonda((int) $ronda, $totalRondas) ?>
                </h3>
                <div class="flex flex-1 flex-col justify-center gap-3">
                    <?php foreach ($partidos as $partido):
                        $esBye = llavesEsBye($partido);
                        $ganador = $partido['id_equipo_ganador'] !== null ? (int) $partido['id_equipo_ganador'] : null;
                        $completo = $partido['id_equipo_a'] !== null && $partido['id_equipo_b'] !== null;
                    ?>
                    <div class="rounded-lg border <?= $ganador !== null ? 'border-slate-300' : 'border-slate-200' ?> bg-white">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-1.5 text-xs text-slate-400">
                            <span class="font-mono">#<?= (int) $ronda ?>.<?= (int) $partido['posicion'] ?></span>
                            <span class="flex items-center gap-2 truncate">
                                <?php if ($partido['hora_programada'] !== null): ?>
                                <span class="flex items-center gap-1"><?= icono('reloj', 'h-3 w-3') ?><?= substr((string) $partido['hora_programada'], 0, 5) ?></span>
                                <?php endif; ?>
                                <?php if ($partido['cancha'] !== null && $partido['cancha'] !== ''): ?>
                                <span class="flex items-center gap-1 truncate"><?= icono('espacio', 'h-3 w-3 shrink-0') ?><?= htmlspecialchars($partido['cancha'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php foreach (['a', 'b'] as $lado):
                            $idLado = $partido['id_equipo_' . $lado] !== null ? (int) $partido['id_equipo_' . $lado] : null;
                            $esGanador = $ganador !== null && $idLado === $ganador;
                            $esPerdedor = $ganador !== null && $idLado !== null && $idLado !== $ganador;
                            $marcador = $partido['marcador_' . $lado];
                        ?>
                        <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm <?= $lado === 'a' ? 'border-b border-slate-100' : '' ?> <?= $esGanador ? 'bg-emerald-50 font-semibold text-emerald-800' : ($esPerdedor ? 'text-slate-400' : ($idLado === null ? 'text-slate-300 italic' : 'text-slate-700')) ?>">
                            <span class="truncate"><?= htmlspecialchars(llaveEtiquetaLado($partido, $lado, (int) $ronda), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($marcador !== null): ?>
                            <span class="shrink-0 font-mono text-xs"><?= (int) $marcador ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <div class="border-t border-slate-100 px-3 py-1.5">
                            <?php if ($esBye): ?>
                            <span class="flex items-center gap-1 text-xs text-slate-400">
                                <?= icono('verificado', 'h-3 w-3') ?>
                                Pase directo, no se juega
                            </span>
                            <?php else: ?>
                            <button type="button" data-abrir-modal="partido-<?= (int) $partido['id'] ?>"
                                    class="flex w-full cursor-pointer items-center justify-center gap-1 rounded-lg py-1 text-xs font-medium <?= $ganador !== null ? 'text-slate-500 hover:bg-slate-100' : 'text-slate-700 hover:bg-slate-100' ?>">
                                <?= icono('editar', 'h-3 w-3') ?>
                                <?= $ganador !== null ? 'Editar resultado' : ($completo ? 'Capturar resultado' : 'Programar partido') ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php // Los <dialog> van fuera del contenedor con overflow-x-auto: dentro,
          // el modal quedaría recortado por el scroll horizontal del cuadro. ?>
    <?php foreach ($porRonda as $ronda => $partidos): ?>
        <?php foreach ($partidos as $partido):
            if (llavesEsBye($partido)) {
                continue;
            }
            $completo = $partido['id_equipo_a'] !== null && $partido['id_equipo_b'] !== null;
            $ganador = $partido['id_equipo_ganador'] !== null ? (int) $partido['id_equipo_ganador'] : null;
        ?>
        <dialog id="partido-<?= (int) $partido['id'] ?>" class="m-auto w-[90%] max-w-lg rounded-xl border-0 p-0 shadow-xl backdrop:bg-slate-900/50">
            <form action="<?= BASE_URL ?>/admin/includes/guardar-partido.php" method="post" class="max-h-[85vh] overflow-y-auto p-5">
                <input type="hidden" name="id_partido" value="<?= (int) $partido['id'] ?>">

                <span class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    <?= llavesNombreRonda((int) $ronda, $totalRondas) ?> · Partido <?= (int) $ronda ?>.<?= (int) $partido['posicion'] ?>
                </span>
                <h3 class="text-base font-semibold">
                    <?= htmlspecialchars(llaveEtiquetaLado($partido, 'a', (int) $ronda), ENT_QUOTES, 'UTF-8') ?>
                    <span class="text-slate-400">vs</span>
                    <?= htmlspecialchars(llaveEtiquetaLado($partido, 'b', (int) $ronda), ENT_QUOTES, 'UTF-8') ?>
                </h3>

                <?php if (!$completo): ?>
                <p class="mt-3 flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    <?= icono('alerta', 'mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                    Todavía no se sabe quiénes juegan aquí: los equipos llegan al resolverse la ronda anterior.
                    Mientras tanto solo se puede programar la hora y la cancha.
                </p>
                <?php endif; ?>

                <?php if ($completo): ?>
                <fieldset class="mt-4">
                    <legend class="mb-2 text-sm font-medium">¿Quién ganó y pasa a la siguiente ronda?</legend>
                    <?php foreach (['a', 'b'] as $lado):
                        $idLado = (int) $partido['id_equipo_' . $lado];
                    ?>
                    <label class="mb-2 flex cursor-pointer items-center justify-between gap-2 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50">
                        <span class="flex items-center gap-2">
                            <input type="radio" name="ganador" value="<?= $idLado ?>" <?= $ganador === $idLado ? 'checked' : '' ?> class="cursor-pointer">
                            <span class="font-medium"><?= htmlspecialchars((string) $partido['nombre_' . $lado], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="flex items-center gap-1.5 text-xs text-slate-500">
                            Marcador
                            <input type="number" name="marcador_<?= $lado ?>" min="0" max="999" inputmode="numeric"
                                   value="<?= $partido['marcador_' . $lado] !== null ? (int) $partido['marcador_' . $lado] : '' ?>"
                                   class="w-16 rounded-lg border border-slate-300 px-2 py-1 text-sm focus:border-slate-500 focus:outline-none">
                        </span>
                    </label>
                    <?php endforeach; ?>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50">
                        <input type="radio" name="ganador" value="" <?= $ganador === null ? 'checked' : '' ?> class="cursor-pointer">
                        <span>
                            <span class="font-medium">Sin resolver</span>
                            <span class="block text-xs text-slate-500">Deja el partido pendiente. Si ya había un ganador, quien haya avanzado con él sale de las rondas siguientes.</span>
                        </span>
                    </label>
                    <p class="mt-2 text-xs text-slate-400">
                        El marcador es informativo: quien avanza es el equipo marcado como ganador.
                        Así un desempate por penales o por sets (ver las reglas por deporte) no obliga a inventar un tanteador.
                    </p>
                </fieldset>
                <?php endif; ?>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label for="hora-<?= (int) $partido['id'] ?>" class="mb-1 block text-sm font-medium">Hora</label>
                        <input type="time" id="hora-<?= (int) $partido['id'] ?>" name="hora_programada"
                               value="<?= htmlspecialchars(substr((string) $partido['hora_programada'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="cancha-<?= (int) $partido['id'] ?>" class="mb-1 block text-sm font-medium">Cancha</label>
                        <input type="text" id="cancha-<?= (int) $partido['id'] ?>" name="cancha" maxlength="100" placeholder="Ej. Cancha 1"
                               value="<?= htmlspecialchars((string) $partido['cancha'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <button type="button" data-cerrar-modal="partido-<?= (int) $partido['id'] ?>"
                            class="flex-1 cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit" class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white active:bg-slate-700">
                        <?= icono('verificado', 'h-4 w-4') ?>
                        Guardar
                    </button>
                </div>
            </form>
        </dialog>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold text-red-700">
            <?= icono('alerta', 'h-4 w-4') ?>
            Rehacer o borrar la llave
        </h2>
        <p class="mb-4 text-sm text-slate-500">
            Regenerar vuelve a armar el cuadro desde cero con los equipos inscritos hoy
            (<?= count($equipos) ?>) y <strong>borra todos los resultados ya capturados</strong>.
            Úsalo antes de que empiece el torneo — por ejemplo si entró un equipo tarde o si quieres repetir el sorteo.
        </p>

        <div class="flex flex-col gap-3 sm:flex-row">
            <form action="<?= BASE_URL ?>/admin/includes/generar-llaves.php" method="post" class="flex-1"
                  onsubmit="return confirm('Se rehará la llave desde cero y se perderán los resultados capturados. ¿Continuar?');">
                <input type="hidden" name="id_competicion" value="<?= (int) $competicion['id'] ?>">
                <input type="hidden" name="regenerar" value="1">
                <input type="hidden" name="orden" value="sorteo">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <?= icono('sorteo', 'h-4 w-4') ?>
                    Regenerar con un nuevo sorteo
                </button>
            </form>
            <form action="<?= BASE_URL ?>/admin/includes/generar-llaves.php" method="post" class="flex-1"
                  onsubmit="return confirm('Se rehará la llave desde cero y se perderán los resultados capturados. ¿Continuar?');">
                <input type="hidden" name="id_competicion" value="<?= (int) $competicion['id'] ?>">
                <input type="hidden" name="regenerar" value="1">
                <input type="hidden" name="orden" value="inscripcion">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <?= icono('cambiar', 'h-4 w-4') ?>
                    Regenerar por orden de inscripción
                </button>
            </form>
            <form action="<?= BASE_URL ?>/admin/includes/eliminar-llaves.php" method="post" class="flex-1"
                  onsubmit="return confirm('Se borrará la llave completa, con sus resultados. ¿Continuar?');">
                <input type="hidden" name="id_competicion" value="<?= (int) $competicion['id'] ?>">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                    <?= icono('eliminar', 'h-4 w-4') ?>
                    Borrar la llave
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php layoutAdminCerrar(); ?>
