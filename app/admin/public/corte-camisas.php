<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
require_once __DIR__ . '/../../camisas/includes/costo.php';
require_once __DIR__ . '/../../camisas/includes/cortes.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$grado = trim((string) ($_GET['grado'] ?? ''));
$grupo = trim((string) ($_GET['grupo'] ?? ''));

if (!in_array($grado, ['1', '3', '5'], true) || !in_array($grupo, ['A', 'B', 'C'], true)) {
    header('Location: ' . BASE_URL . '/admin/public/camisas.php?error=grupo_invalido');
    exit;
}

$costo = camisaCosto($pdo);

$consultaAlumnos = $pdo->prepare(
    'SELECT camisa_pedir, camisa_pago FROM alumnos WHERE grado = :grado AND grupo = :grupo'
);
$consultaAlumnos->execute(['grado' => $grado, 'grupo' => $grupo]);
$resumen = camisaResumen($consultaAlumnos->fetchAll(), $costo);

$consultaJefe = $pdo->prepare(
    'SELECT nombre_completo FROM alumnos WHERE grado = :grado AND grupo = :grupo AND es_jefe = 1'
);
$consultaJefe->execute(['grado' => $grado, 'grupo' => $grupo]);
$jefe = $consultaJefe->fetch();

$cortes = camisaCortesListar($pdo, $grado, $grupo);
$encuadre = camisaCortesEncuadre($resumen['recaudado'], $cortes);

$idResaltado = isset($_GET['id']) ? (int) $_GET['id'] : 0;

require __DIR__ . '/../includes/layout.php';

$mensajesExito = ['corte_guardado' => 'Corte registrado. Descarga el recibo para que lo firmen ambas partes.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;

$mensajesError = [
    'monto_invalido' => 'El monto no es válido: escribe una cantidad mayor a cero, como 150 o 1350.50.',
    'campos_incompletos' => 'Escribe quién entrega y quién recibe el dinero.',
    'fecha_invalida' => 'La fecha de entrega no es válida.',
    'fecha_futura' => 'La fecha de entrega no puede ser posterior a hoy.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir('Corte de camisas — ' . $grado . '°' . $grupo, 'camisas');
if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<a href="<?= BASE_URL ?>/admin/public/camisas.php" class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
    <?= icono('atras', 'h-3.5 w-3.5') ?>
    Volver al listado
</a>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="flex items-center gap-3 rounded-lg border-l-4 border-emerald-500 bg-emerald-50 p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><?= icono('dinero', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs font-medium text-emerald-700">Recaudado (sistema)</span>
            <span class="block text-xl font-bold text-emerald-900"><?= camisaMoneda($resumen['recaudado']) ?></span>
        </div>
    </div>

    <div class="flex items-center gap-3 rounded-lg border-l-4 border-slate-300 bg-white p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500"><?= icono('verificado', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs font-medium text-slate-500">Entregado en cortes</span>
            <span class="block text-xl font-bold text-slate-900"><?= camisaMoneda($encuadre['entregado']) ?></span>
        </div>
    </div>

    <?php
    $diferencia = $encuadre['diferencia'];
    if (abs($diferencia) < 0.01) {
        $claseEncuadre = ['borde' => 'border-slate-300', 'fondo' => 'bg-white', 'icono' => 'bg-slate-100 text-slate-500', 'texto' => 'text-slate-500', 'valor' => 'text-slate-900'];
        $etiquetaEncuadre = 'Cuadra exacto';
    } elseif ($diferencia > 0) {
        $claseEncuadre = ['borde' => 'border-amber-500', 'fondo' => 'bg-amber-50', 'icono' => 'bg-amber-100 text-amber-600', 'texto' => 'text-amber-700', 'valor' => 'text-amber-900'];
        $etiquetaEncuadre = 'Aún debe entregar';
    } else {
        $claseEncuadre = ['borde' => 'border-red-500', 'fondo' => 'bg-red-50', 'icono' => 'bg-red-100 text-red-600', 'texto' => 'text-red-700', 'valor' => 'text-red-900'];
        $etiquetaEncuadre = 'Entregó de más — revisar capturas';
    }
    ?>
    <div class="flex items-center gap-3 rounded-lg border-l-4 <?= $claseEncuadre['borde'] ?> <?= $claseEncuadre['fondo'] ?> p-3 shadow-sm">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?= $claseEncuadre['icono'] ?>"><?= icono('alerta', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <span class="block truncate text-xs font-medium <?= $claseEncuadre['texto'] ?>"><?= $etiquetaEncuadre ?></span>
            <span class="block text-xl font-bold <?= $claseEncuadre['valor'] ?>"><?= camisaMoneda(abs($diferencia)) ?></span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <div class="lg:col-span-1">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-base font-semibold">Registrar corte</h2>
            <form action="<?= BASE_URL ?>/admin/includes/guardar-corte-camisas.php" method="post" class="grid grid-cols-1 gap-4">
                <input type="hidden" name="grado" value="<?= htmlspecialchars($grado, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="monto" class="mb-1 block text-sm font-medium">Monto entregado</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-sm text-slate-400">$</span>
                        <input type="text" inputmode="decimal" id="monto" name="monto" required placeholder="0.00"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-6 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Lo que el jefe trae en efectivo, aunque no coincida con lo recaudado.</p>
                </div>

                <div>
                    <label for="fecha_movimiento" class="mb-1 block text-sm font-medium">Fecha de entrega</label>
                    <input type="date" id="fecha_movimiento" name="fecha_movimiento" required
                           value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-slate-500 focus:outline-none">
                </div>

                <div>
                    <label for="entregado_por" class="mb-1 block text-sm font-medium">Entregó</label>
                    <input type="text" id="entregado_por" name="entregado_por" required maxlength="150"
                           value="<?= htmlspecialchars($jefe['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-slate-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-400">Normalmente el jefe de grupo — puede editarse si entrega alguien más.</p>
                </div>

                <div>
                    <label for="recibido_por" class="mb-1 block text-sm font-medium">Recibió</label>
                    <input type="text" id="recibido_por" name="recibido_por" required maxlength="150" placeholder="Nombre de quien recibe"
                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-slate-500 focus:outline-none">
                </div>

                <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white active:bg-slate-700">
                    <?= icono('verificado', 'h-4 w-4') ?>
                    Registrar corte
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold">
                <?= icono('camisa', 'h-4 w-4 text-slate-400') ?>
                Histórico de cortes
            </h2>
            <?php if ($cortes === []): ?>
            <p class="flex items-center gap-2 text-sm text-slate-500">
                Todavía no hay cortes registrados para este grupo.
            </p>
            <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">Folio</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2 text-right">Monto</th>
                            <th class="px-3 py-2">Entregó</th>
                            <th class="px-3 py-2">Recibió</th>
                            <th class="px-3 py-2 text-center">Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cortes as $corte): ?>
                        <tr class="border-b border-slate-100 last:border-0 <?= $idResaltado === (int) $corte['id'] ? 'bg-emerald-50' : '' ?>">
                            <td class="px-3 py-2 font-mono text-xs text-slate-400">#<?= (int) $corte['id'] ?></td>
                            <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($corte['fecha_movimiento'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-right font-medium"><?= camisaMoneda((float) $corte['monto']) ?></td>
                            <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($corte['entregado_por'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($corte['recibido_por'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 text-center">
                                <a href="<?= BASE_URL ?>/admin/includes/exportar-corte-camisas.php?id=<?= (int) $corte['id'] ?>" title="Descargar recibo (PDF)"
                                   class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                                    <?= icono('descargar', 'h-4 w-4') ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php layoutAdminCerrar(); ?>
