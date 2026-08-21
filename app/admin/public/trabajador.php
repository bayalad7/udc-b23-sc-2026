<?php
declare(strict_types=1);

require __DIR__ . '/../includes/sesion.php';
require __DIR__ . '/../includes/iconos.php';
require_once __DIR__ . '/../../trabajadores/includes/catalogo.php';
iniciarSesionAdmin();
if (!adminAutorizado()) {
    header('Location: ' . BASE_URL . '/admin/public/index.php');
    exit;
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../../config/db.php';

$esNuevo = isset($_GET['nuevo']);
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

$trabajador = [
    'id' => null,
    'tipo' => '',
    'numero_trabajador' => '',
    'nombre_completo' => '',
    'camisa_corte' => 'Unisex',
    'camisa_talla' => '',
    'fecha_registro' => null,
];

if (!$esNuevo) {
    if ($id === null || $id <= 0) {
        header('Location: ' . BASE_URL . '/admin/public/trabajadores.php?error=no_encontrado');
        exit;
    }
    $consulta = $pdo->prepare('SELECT * FROM trabajadores WHERE id = :id');
    $consulta->execute(['id' => $id]);
    $fila = $consulta->fetch();
    if ($fila === false) {
        header('Location: ' . BASE_URL . '/admin/public/trabajadores.php?error=no_encontrado');
        exit;
    }
    $trabajador = $fila;
}

require __DIR__ . '/../includes/layout.php';

$mensajesExito = ['creado' => 'Personal registrado correctamente.', 'actualizado' => 'Cambios guardados.'];
$mensajeExito = $mensajesExito[$_GET['msg'] ?? ''] ?? null;

$mensajesError = [
    'campos_incompletos' => 'Revisa que todos los campos obligatorios estén completos y con formato válido.',
    'numero_invalido' => 'El número de trabajador debe ser alfanumérico (hasta 20 caracteres, sin espacios).',
    'trabajador_duplicado' => 'Ese número de trabajador ya está registrado por otra persona.',
    'error_servidor' => 'Ocurrió un error al guardar. Intenta de nuevo.',
];
$mensajeError = $mensajesError[$_GET['error'] ?? ''] ?? null;

layoutAdminAbrir($esNuevo ? 'Nuevo registro de personal' : 'Personal', 'trabajadores');

if ($mensajeExito) {
    bannerAdmin('exito', $mensajeExito);
}
if ($mensajeError) {
    bannerAdmin('error', $mensajeError);
}
?>

<a href="<?= BASE_URL ?>/admin/public/trabajadores.php" class="mb-4 inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
    <?= icono('atras', 'h-3.5 w-3.5') ?>
    Volver al listado
</a>

<div class="max-w-2xl rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-4 text-base font-semibold"><?= $esNuevo ? 'Datos del personal' : 'Editar datos' ?></h2>

    <form action="<?= BASE_URL ?>/admin/includes/guardar-trabajador.php" method="post" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <?php if (!$esNuevo): ?>
        <input type="hidden" name="id" value="<?= (int) $trabajador['id'] ?>">
        <?php endif; ?>

        <div class="sm:col-span-2">
            <label for="nombre_completo" class="mb-1 block text-sm font-medium">Nombre completo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('persona', 'h-4 w-4') ?></span>
                <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="150"
                       value="<?= htmlspecialchars((string) $trabajador['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label for="tipo" class="mb-1 block text-sm font-medium">Tipo de personal</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('maletin', 'h-4 w-4') ?></span>
                <select id="tipo" name="tipo" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Elige...</option>
                    <?php foreach (TRABAJADOR_TIPOS as $t): ?>
                    <option value="<?= $t ?>" <?= $trabajador['tipo'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="numero_trabajador" class="mb-1 block text-sm font-medium">Número de trabajador</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('numeral', 'h-4 w-4') ?></span>
                <input type="text" id="numero_trabajador" name="numero_trabajador" required maxlength="20" autocapitalize="characters"
                       value="<?= htmlspecialchars((string) $trabajador['numero_trabajador'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm uppercase focus:border-slate-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label for="camisa_corte" class="mb-1 block text-sm font-medium">Corte de camisa</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camisa', 'h-4 w-4') ?></span>
                <select id="camisa_corte" name="camisa_corte" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <?php foreach (TRABAJADOR_CAMISA_CORTES as $corte): ?>
                    <option value="<?= $corte ?>" <?= $trabajador['camisa_corte'] === $corte ? 'selected' : '' ?>><?= $corte ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="camisa_talla" class="mb-1 block text-sm font-medium">Talla de camisa</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400"><?= icono('camisa', 'h-4 w-4') ?></span>
                <select id="camisa_talla" name="camisa_talla" required class="w-full rounded-lg border border-slate-300 py-2 pl-8 pr-3 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Elige...</option>
                    <?php foreach (TRABAJADOR_CAMISA_TALLAS as $tallaClave => $etiqueta): ?>
                    <option value="<?= $tallaClave ?>" <?= $trabajador['camisa_talla'] === $tallaClave ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="sm:col-span-2">
            <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white active:bg-slate-700">
                <?= icono('verificado', 'h-4 w-4') ?>
                <?= $esNuevo ? 'Registrar personal' : 'Guardar cambios' ?>
            </button>
        </div>
    </form>

    <?php if (!$esNuevo): ?>
    <p class="mt-4 text-xs text-slate-400">Registrado el <?= htmlspecialchars((string) $trabajador['fecha_registro'], ENT_QUOTES, 'UTF-8') ?></p>

    <?php
    // A diferencia de alumnos/eventos/competiciones, `trabajadores` no tiene
    // ninguna tabla que dependa de ella (ver schema.sql: sin FKs entrantes),
    // así que aquí no hace falta el chequeo de dependientes antes de borrar.
    ?>
    <form action="<?= BASE_URL ?>/admin/includes/eliminar-trabajador.php" method="post" class="mt-4 border-t border-slate-100 pt-4"
          onsubmit="return confirm('¿Eliminar este registro de personal de forma permanente? Esta acción no se puede deshacer.');">
        <input type="hidden" name="id" value="<?= (int) $trabajador['id'] ?>">
        <button type="submit" class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
            <?= icono('eliminar', 'h-4 w-4') ?>
            Eliminar registro
        </button>
    </form>
    <?php endif; ?>
</div>

<?php layoutAdminCerrar(); ?>
