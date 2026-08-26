<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
require_once __DIR__ . '/../includes/costo.php';

iniciarSesionCamisas();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

// Pantalla única del jefe de grupo: se identifica arriba y, ya dentro, ve
// SOLO a los alumnos de su propio grado+grupo para marcar quién encarga camisa
// y cuánto lleva pagado. Todo cabe en una página porque un grupo son ~45
// alumnos; el listado se pinta como tarjetas y no como tabla porque esto se usa
// en el celular, cobrando en el salón.

$jefe = jefeDeSesion($pdo);

$errores = [
    'credenciales_invalidas' => 'No pudimos identificarte como jefe de grupo. Revisa tu número de cuenta y tu correo institucional — si crees que sí eres el jefe, pídele al staff que te dé de alta.',
    'no_encontrado' => 'No se encontró ese alumno.',
    'fuera_de_grupo' => 'Ese alumno no es de tu grupo, así que no puedes modificar su pago.',
    'monto_invalido' => 'El monto no es válido: escribe una cantidad como 150 o 75.50.',
    'pago_excede' => 'El pago no puede ser mayor al costo de la camisa.',
    'pago_sin_pedido' => 'Ese alumno tiene un pago registrado. Para marcarlo como que no pide camisa, primero pon su pago en 0 (y devuélvele lo que haya abonado).',
    'sesion_expirada' => 'Tu sesión terminó. Identifícate de nuevo.',
];
$mensajeError = $errores[$_GET['error'] ?? ''] ?? null;
$mensajeExito = ($_GET['msg'] ?? '') === 'guardado' ? 'Cambios guardados.' : null;

$costo = camisaCosto($pdo);

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$estado = trim((string) ($_GET['estado'] ?? ''));
$idResaltado = isset($_GET['alumno']) ? (int) $_GET['alumno'] : 0;

$alumnos = [];
$resumen = null;
$alumnosVisibles = [];

if ($jefe !== null) {
    // Se traen de una sola vez todos los alumnos del grupo (son pocas decenas)
    // y el filtro se aplica en PHP: así el resumen de arriba siempre es el del
    // GRUPO COMPLETO aunque la lista esté filtrada — que es lo que el jefe le
    // tiene que entregar al staff — sin arriesgarse a que una segunda consulta
    // de totales diga algo distinto a lo que se ve en la lista.
    $consulta = $pdo->prepare(
        'SELECT id, numero_cuenta, nombre_completo, camisa_talla, camisa_pedir, camisa_pago
         FROM alumnos WHERE grado = :grado AND grupo = :grupo ORDER BY nombre_completo'
    );
    $consulta->execute(['grado' => $jefe['grado'], 'grupo' => $jefe['grupo']]);
    $alumnos = $consulta->fetchAll();

    $resumen = camisaResumen($alumnos, $costo);

    $alumnosVisibles = array_values(array_filter($alumnos, static function (array $a) use ($buscar, $estado, $costo): bool {
        if ($buscar !== ''
            && stripos($a['nombre_completo'], $buscar) === false
            && stripos($a['numero_cuenta'], $buscar) === false) {
            return false;
        }

        $pide = (int) $a['camisa_pedir'] === 1;
        $pago = (float) $a['camisa_pago'];

        return match ($estado) {
            'pendientes' => $pide && $pago < $costo,
            'liquidados' => $pide && $pago >= $costo,
            'sin_pagar' => $pide && $pago <= 0,
            'no_piden' => !$pide,
            default => true,
        };
    }));
}

$filtrosActuales = array_filter(['buscar' => $buscar, 'estado' => $estado]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Camisas del grupo — Semana Acádemica, Cultural y Deportiva B23</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-8">

    <div class="mb-6 flex flex-col items-center text-center">
        <img src="<?= BASE_URL ?>/assets/img/logo/UdeC_2L%20izq%20Negro.png" alt="Universidad de Colima" class="mb-4 h-16 w-auto">
        <h1 class="text-xl font-bold">Bachillerato 23</h1>
        <h1 class="text-xl font-bold">Semana Académica, Cultural y Deportiva</h1>
        <p>&nbsp;</p>
        <p class="text-sm text-slate-600"><strong>Camisas del grupo: </strong>Control de quién encarga la camisa oficial del aniversario y cuánto lleva pagado. Solo para el jefe de cada grado y grupo.</p>
        <p class="mt-1 text-sm text-slate-600">Aniversario #45</p>
    </div>

    <?php if ($mensajeError): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= icono('alerta', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <?php if ($mensajeExito): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <?= icono('exito', 'mt-0.5 h-4 w-4 shrink-0') ?>
        <span><?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <?php if ($jefe === null): ?>

        <div class="mb-4 flex items-start gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <?= icono('usuario', 'mt-0.5 h-4 w-4 shrink-0') ?>
            <span>Esta sección es solo para el jefe de grupo. Identifícate con tu número de cuenta y tu correo institucional para ver la lista de tu grado y grupo.</span>
        </div>

        <form action="<?= BASE_URL ?>/camisas/includes/identificar.php" method="post" novalidate class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm">
            <div>
                <label for="numero_cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('credencial') ?></span>
                    <input type="text" id="numero_cuenta" name="numero_cuenta" required maxlength="8" minlength="8"
                           pattern="[A-Za-z0-9]{8}" placeholder="XXXXXXXX" autocapitalize="characters" autofocus
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base uppercase focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">El mismo número de cuenta de tu credencial digital.</p>
            </div>
            <div>
                <label for="correo_institucional" class="mb-1 block text-sm font-medium">Correo institucional</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('correo') ?></span>
                    <input type="email" id="correo_institucional" name="correo_institucional" required maxlength="150" placeholder="mi_correo@ucol.mx"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-base focus:border-slate-500 focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">El mismo correo que usaste al pre-registrarte.</p>
            </div>
            <button type="submit"
                    class="flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white active:bg-slate-700">
                <?= icono('usuario', 'h-4 w-4 shrink-0') ?>
                Identificarme
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500">
            El costo de la camisa es de <strong><?= camisaMoneda($costo) ?></strong>.
        </p>

    <?php else: ?>

        <div class="mb-4 flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white"><?= icono('camisa', 'h-5 w-5') ?></span>
            <div class="min-w-0 text-sm">
                <span class="block text-xs text-slate-500">Jefe de grupo</span>
                <span class="block truncate font-semibold"><?= htmlspecialchars($jefe['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="mt-0.5 block text-slate-500"><?= htmlspecialchars($jefe['grado'], ENT_QUOTES, 'UTF-8') ?>° <?= htmlspecialchars($jefe['grupo'], ENT_QUOTES, 'UTF-8') ?> · camisa a <?= camisaMoneda($costo) ?></span>
            </div>
        </div>

        <!-- Corte de caja del GRUPO COMPLETO (no del listado filtrado): es lo
             que el jefe tiene que cuadrar contra el dinero que trae. -->
        <div class="mb-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl border-l-4 border-emerald-500 bg-white p-4 shadow-sm">
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><?= icono('dinero', 'h-3.5 w-3.5') ?> Recaudado</span>
                <span class="mt-1 block text-xl font-bold text-emerald-700"><?= camisaMoneda($resumen['recaudado']) ?></span>
                <span class="block text-[11px] text-slate-400">de <?= camisaMoneda($resumen['esperado']) ?> esperados</span>
            </div>
            <div class="rounded-xl border-l-4 <?= $resumen['pendiente'] > 0 ? 'border-amber-500' : 'border-slate-300' ?> bg-white p-4 shadow-sm">
                <span class="flex items-center gap-1.5 text-xs text-slate-500"><?= icono('alerta', 'h-3.5 w-3.5') ?> Por cobrar</span>
                <span class="mt-1 block text-xl font-bold <?= $resumen['pendiente'] > 0 ? 'text-amber-700' : 'text-slate-500' ?>"><?= camisaMoneda($resumen['pendiente']) ?></span>
                <span class="block text-[11px] text-slate-400"><?= $resumen['liquidados'] ?> de <?= $resumen['piden'] ?> ya liquidaron</span>
            </div>
        </div>

        <p class="mb-4 text-xs text-slate-500">
            <?= count($alumnos) ?> alumnos en <?= htmlspecialchars($jefe['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars($jefe['grupo'], ENT_QUOTES, 'UTF-8') ?> ·
            <?= $resumen['piden'] ?> encargan camisa · <?= $resumen['no_piden'] ?> no la quieren
        </p>

        <form method="get" class="mb-4 flex flex-wrap items-end gap-2 rounded-xl bg-white p-4 shadow-sm">
            <div class="min-w-0 flex-1">
                <label for="buscar" class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('buscar', 'h-4 w-4') ?></span>
                    <input type="text" id="buscar" name="buscar" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o número de cuenta"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label for="estado" class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                <select id="estado" name="estado" class="rounded-lg border border-slate-300 py-2 pl-3 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <?php foreach ([
                        '' => 'Todos',
                        'pendientes' => 'Deben algo',
                        'sin_pagar' => 'Sin pagar nada',
                        'liquidados' => 'Ya liquidaron',
                        'no_piden' => 'No piden camisa',
                    ] as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white active:bg-slate-700">
                <?= icono('filtro', 'h-4 w-4') ?>
                Filtrar
            </button>
        </form>

        <?php if ($alumnosVisibles === []): ?>
        <p class="rounded-xl bg-white px-4 py-10 text-center text-sm text-slate-500 shadow-sm">
            No hay alumnos que coincidan con ese filtro.
        </p>
        <?php endif; ?>

        <div class="flex flex-col gap-3">
            <?php foreach ($alumnosVisibles as $alumno):
                $pide = (int) $alumno['camisa_pedir'] === 1;
                $pago = (float) $alumno['camisa_pago'];
                $estadoPago = camisaEstadoPago($alumno, $costo);
                $liquidado = $pide && $pago >= $costo;
            ?>
            <form action="<?= BASE_URL ?>/camisas/includes/guardar-pago.php" method="post" novalidate
                  class="rounded-xl bg-white p-4 shadow-sm <?= $idResaltado === (int) $alumno['id'] ? 'ring-2 ring-emerald-400' : '' ?>">
                <input type="hidden" name="id_alumno" value="<?= (int) $alumno['id'] ?>">
                <input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="estado" value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-3 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <span class="block font-semibold leading-tight"><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="mt-0.5 block text-xs text-slate-500">
                            No. cuenta <?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?> ·
                            talla <?= htmlspecialchars($alumno['camisa_talla'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium <?= $estadoPago['clases'] ?>">
                        <?= htmlspecialchars($estadoPago['etiqueta'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 has-[:checked]:border-slate-900 has-[:checked]:font-semibold has-[:checked]:text-slate-900">
                        <input type="checkbox" name="camisa_pedir" value="1" <?= $pide ? 'checked' : '' ?>
                               class="h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-slate-900">
                        Pide camisa
                    </label>

                    <div class="w-28">
                        <label for="pago-<?= (int) $alumno['id'] ?>" class="mb-1 block text-xs font-medium text-slate-500">Ha pagado</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-sm text-slate-400">$</span>
                            <input type="text" inputmode="decimal" id="pago-<?= (int) $alumno['id'] ?>" name="camisa_pago"
                                   value="<?= number_format($pago, 2, '.', '') ?>"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-6 pr-2 text-sm focus:border-slate-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="flex flex-1 justify-end gap-2">
                        <?php if (!$liquidado): ?>
                        <button type="submit" name="accion" value="liquidar" title="Marcar como pagada completa (<?= camisaMoneda($costo) ?>)"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 active:bg-emerald-100">
                            <?= icono('exito', 'h-4 w-4 shrink-0') ?>
                            Pagó todo
                        </button>
                        <?php endif; ?>
                        <button type="submit" name="accion" value="guardar"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white active:bg-slate-700">
                            <?= icono('guardar', 'h-4 w-4 shrink-0') ?>
                            Guardar
                        </button>
                    </div>
                </div>
            </form>
            <?php endforeach; ?>
        </div>

        <a href="<?= BASE_URL ?>/camisas/includes/salir.php" class="mt-6 flex cursor-pointer items-center justify-center gap-1.5 text-center text-xs font-medium text-slate-500 underline">
            <?= icono('salir', 'h-3.5 w-3.5 shrink-0') ?>
            Cerrar sesión en este dispositivo
        </a>

    <?php endif; ?>

</div>
</body>
</html>
