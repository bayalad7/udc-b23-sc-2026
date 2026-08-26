<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$esNuevo = isset($_GET['nuevo']);
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

require_once __DIR__ . '/../../camisas/includes/costo.php';
$costoCamisa = camisaCosto($pdo);

$alumno = [
    'id' => null,
    'numero_cuenta' => '',
    'nombre_completo' => '',
    'grado' => '',
    'grupo' => '',
    'correo_institucional' => '',
    'camisa_corte' => '',
    'camisa_talla' => '',
    'camisa_pedir' => 1,
    'camisa_pago' => '0.00',
    'es_jefe' => 0,
    'foto_path' => null,
    'credencial_generada' => 0,
    'fecha_registro' => null,
];

if (!$esNuevo) {
    if ($id === null || $id <= 0) {
        header('Location: ' . BASE_URL . '/admin/public/alumnos.php?error=no_encontrado');
        exit;
    }
    $consulta = $pdo->prepare('SELECT * FROM alumnos WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $fila = $consulta->fetch();
    if ($fila === false) {
        header('Location: ' . BASE_URL . '/admin/public/alumnos.php?error=no_encontrado');
        exit;
    }
    $alumno = $fila;
}

// Quién lleva hoy el control de camisas de ese grado+grupo. Se muestra junto a
// la casilla de jefe para que el staff no tenga que ir a buscarlo al listado
// antes de reasignar el cargo (solo hay uno por grupo — ver uq_alumnos_jefe_grupo).
$jefeDelGrupo = null;
if ($alumno['grado'] !== '' && $alumno['grupo'] !== '') {
    $consultaJefe = $pdo->prepare(
        'SELECT id, nombre_completo FROM alumnos WHERE grado = :grado AND grupo = :grupo AND es_jefe = 1'
    );
    $consultaJefe->execute(['grado' => $alumno['grado'], 'grupo' => $alumno['grupo']]);
    $filaJefe = $consultaJefe->fetch();
    $jefeDelGrupo = $filaJefe === false ? null : $filaJefe;
}

require __DIR__ . '/../includes/layout.php';

$mensajesExito = [
    'actualizado' => 'Cambios guardados.',
    'credencial_regenerada' => 'Credencial regenerada correctamente.',
];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;

$mensajesError = [
    'campos_incompletos' => 'Revisa que todos los campos obligatorios estén completos y con formato válido.',
    'numero_cuenta_invalido' => 'El número de cuenta debe tener 8 caracteres (letras/números).',
    'foto_invalida' => 'La fotografía es obligatoria y debe ser JPG o PNG de máximo 5 MB.',
    'cuenta_duplicada' => 'Ese número de cuenta ya está registrado por otro alumno.',
    'jefe_duplicado' => 'Ese grado y grupo ya tiene jefe: ' . htmlspecialchars((string) ($_GET['detalle'] ?? 'otro alumno'), ENT_QUOTES, 'UTF-8') . '. Quítale el cargo antes de nombrar a alguien más.',
    'monto_invalido' => 'El monto pagado no es válido: escribe una cantidad como 150 o 75.50.',
    'pago_excede' => 'El pago no puede ser mayor al costo de la camisa (' . camisaMoneda($costoCamisa) . ').',
    'pago_sin_pedido' => 'No se puede dejar un pago registrado a nombre de quien no encarga camisa: pon el pago en 0 o vuelve a marcar la casilla.',
    'error_servidor' => 'Ocurrió un error al guardar. Intenta de nuevo.',
    'tiene_dependientes' => 'No se puede eliminar: el alumno todavía tiene ' . htmlspecialchars((string) ($_GET['detalle'] ?? 'registros relacionados'), ENT_QUOTES, 'UTF-8') . '.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir($esNuevo ? 'Nuevo alumno' : 'Alumno', 'alumnos');

if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<a href="<?= BASE_URL ?>/admin/public/alumnos.php" class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
    <?= icono('atras', 'h-3.5 w-3.5') ?>
    Volver al listado
</a>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <div class="lg:col-span-1">
        <div class="rounded-xl bg-white p-5 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-32 w-32 items-center justify-center">
                <?php fotoMiniatura($esNuevo ? null : $alumno['foto_path'], (string) $alumno['nombre_completo'], 'h-32 w-32', 'h-12 w-12'); ?>
            </div>

            <?php if (!$esNuevo): ?>
            <span class="block text-lg font-bold"><?= htmlspecialchars($alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="mt-1 block text-sm text-slate-500">No. cuenta <?= htmlspecialchars($alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?></span>

            <div class="mt-4 flex justify-center">
                <?php if ($alumno['credencial_generada']): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700"><?= icono('exito', 'h-3.5 w-3.5') ?> Credencial generada</span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700"><?= icono('alerta', 'h-3.5 w-3.5') ?> Credencial pendiente</span>
                <?php endif; ?>
            </div>

            <?php if ($alumno['credencial_generada'] && $alumno['credencial_path']): ?>
            <a href="<?= BASE_URL ?>/registro/public/<?= htmlspecialchars((string) $alumno['credencial_path'], ENT_QUOTES, 'UTF-8') ?>"
               download="credencial-<?= htmlspecialchars((string) $alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>.png"
               class="mt-4 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                <?= icono('descargar', 'h-4 w-4') ?>
                Descargar credencial
            </a>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/admin/includes/regenerar-credencial.php" method="post" class="mt-4">
                <input type="hidden" name="id" value="<?= (int) $alumno['id'] ?>">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <?= icono('cambiar', 'h-4 w-4') ?>
                    Regenerar credencial
                </button>
            </form>

            <p class="mt-3 text-xs text-slate-400">Registrado el <?= htmlspecialchars((string) $alumno['fecha_registro'], ENT_QUOTES, 'UTF-8') ?></p>

            <form action="<?= BASE_URL ?>/admin/includes/eliminar-alumno.php" method="post" class="mt-6 border-t border-slate-100 pt-4"
                  onsubmit="return confirm('¿Eliminar a este alumno de forma permanente? Esta acción no se puede deshacer.');">
                <input type="hidden" name="id" value="<?= (int) $alumno['id'] ?>">
                <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                    <?= icono('eliminar', 'h-4 w-4') ?>
                    Eliminar alumno
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-base font-semibold"><?= $esNuevo ? 'Datos del alumno' : 'Editar datos' ?></h2>
            <form action="<?= BASE_URL ?>/admin/includes/guardar-alumno.php" method="post" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php if (!$esNuevo): ?>
                <input type="hidden" name="id" value="<?= (int) $alumno['id'] ?>">
                <?php endif; ?>

                <div class="sm:col-span-2">
                    <label for="nombre_completo" class="mb-1 block text-sm font-medium">Nombre completo</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('persona', 'h-4 w-4') ?></span>
                        <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="150"
                               value="<?= htmlspecialchars((string) $alumno['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="numero_cuenta" class="mb-1 block text-sm font-medium">Número de cuenta</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('numeral', 'h-4 w-4') ?></span>
                        <input type="text" id="numero_cuenta" name="numero_cuenta" required maxlength="8" minlength="8"
                               pattern="[A-Za-z0-9]{8}" autocapitalize="characters"
                               value="<?= htmlspecialchars((string) $alumno['numero_cuenta'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm uppercase focus:border-slate-500 focus:outline-none">
                    </div>
                    <?php if (!$esNuevo): ?>
                    <p class="mt-1 text-xs text-amber-600">Si lo cambias, regenera la credencial — el QR ya impreso quedaría desactualizado.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="correo_institucional" class="mb-1 block text-sm font-medium">Correo institucional</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('correo', 'h-4 w-4') ?></span>
                        <input type="email" id="correo_institucional" name="correo_institucional" required maxlength="150"
                               value="<?= htmlspecialchars((string) $alumno['correo_institucional'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="grado" class="mb-1 block text-sm font-medium">Grado</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('academico', 'h-4 w-4') ?></span>
                        <select id="grado" name="grado" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="">Elige...</option>
                            <?php foreach (['1', '3', '5'] as $g): ?>
                            <option value="<?= $g ?>" <?= $alumno['grado'] === $g ? 'selected' : '' ?>><?= $g ?>°</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="grupo" class="mb-1 block text-sm font-medium">Grupo</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('usuarios', 'h-4 w-4') ?></span>
                        <select id="grupo" name="grupo" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="">Elige...</option>
                            <?php foreach (['A', 'B', 'C'] as $gr): ?>
                            <option value="<?= $gr ?>" <?= $alumno['grupo'] === $gr ? 'selected' : '' ?>><?= $gr ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="camisa_corte" class="mb-1 block text-sm font-medium">Corte de camisa</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camisa', 'h-4 w-4') ?></span>
                        <select id="camisa_corte" name="camisa_corte" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="Unisex" selected>Unisex</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="camisa_talla" class="mb-1 block text-sm font-medium">Talla de camisa</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camisa', 'h-4 w-4') ?></span>
                        <select id="camisa_talla" name="camisa_talla" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="">Elige...</option>
                            <?php
                            $tallasCamisaLabels = [
                                'XS' => 'Extra Chica',
                                'S' => 'Chica',
                                'M' => 'Mediana',
                                'L' => 'Grande',
                                'XL' => 'Extra Grande',
                                '2XL' => 'Doble Extra Grande',
                                '3XL' => 'Triple Extra Grande',
                            ];
                            foreach ($tallasCamisaLabels as $talla => $etiqueta): ?>
                            <option value="<?= $talla ?>" <?= $alumno['camisa_talla'] === $talla ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Camisa del aniversario: el día a día de estos tres campos
                     lo lleva el jefe de grupo desde app/camisas; aquí están
                     para que el staff pueda corregir y para nombrar al jefe. -->
                <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <?= icono('camisa', 'h-4 w-4 text-slate-400') ?>
                        Camisa del aniversario
                    </h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 has-[:checked]:border-slate-900 has-[:checked]:font-semibold has-[:checked]:text-slate-900">
                            <input type="checkbox" name="camisa_pedir" value="1" <?= (int) $alumno['camisa_pedir'] === 1 ? 'checked' : '' ?>
                                   class="h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-slate-900">
                            Encarga camisa
                        </label>

                        <div>
                            <label for="camisa_pago" class="mb-1 block text-sm font-medium">Ha pagado</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-sm text-slate-400">$</span>
                                <input type="text" inputmode="decimal" id="camisa_pago" name="camisa_pago"
                                       value="<?= number_format((float) $alumno['camisa_pago'], 2, '.', '') ?>"
                                       class="w-full rounded-lg border border-slate-300 py-2 pl-6 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Costo de la camisa: <?= camisaMoneda($costoCamisa) ?>.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 has-[:checked]:border-slate-900 has-[:checked]:font-semibold has-[:checked]:text-slate-900">
                                <input type="checkbox" name="es_jefe" value="1" <?= (int) $alumno['es_jefe'] === 1 ? 'checked' : '' ?>
                                       class="h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 accent-slate-900">
                                Jefe de grupo
                            </label>
                            <p class="mt-1 text-xs text-slate-500">
                                Podrá entrar a <span class="font-mono">camisas/public/index.php</span> con su número de cuenta y correo institucional,
                                y llevar los pagos de su grado y grupo. Solo puede haber uno por grupo.
                                <?php if ($jefeDelGrupo !== null && (int) $jefeDelGrupo['id'] !== (int) $alumno['id']): ?>
                                <span class="mt-1 block text-amber-600">
                                    Hoy el jefe de <?= htmlspecialchars((string) $alumno['grado'], ENT_QUOTES, 'UTF-8') ?>°<?= htmlspecialchars((string) $alumno['grupo'], ENT_QUOTES, 'UTF-8') ?>
                                    es <a href="<?= BASE_URL ?>/admin/public/alumno.php?id=<?= (int) $jefeDelGrupo['id'] ?>" class="underline"><?= htmlspecialchars($jefeDelGrupo['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></a>;
                                    quítale el cargo antes de nombrar a este alumno.
                                </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label for="foto" class="mb-1 block text-sm font-medium">Fotografía <?= $esNuevo ? '' : '(déjalo vacío para conservar la actual)' ?></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camara', 'h-4 w-4') ?></span>
                        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png" <?= $esNuevo ? 'required' : '' ?>
                               class="w-full cursor-pointer rounded-lg border border-slate-300 bg-white py-2 pl-8 pr-3 text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-white focus:border-slate-500 focus:outline-none">
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white active:bg-slate-700">
                        <?= icono('verificado', 'h-4 w-4') ?>
                        <?= $esNuevo ? 'Registrar alumno' : 'Guardar cambios' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php layoutAdminCerrar(); ?>
