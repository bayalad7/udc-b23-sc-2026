<?php
declare(strict_types=1);

// Generador de codigo_participante para integrantes de equipo (ver
// integrantes.codigo_participante en schema.sql) — compartido entre los tres
// módulos de equipo (Concurso del Conocimiento, Escenario de Talentos,
// torneos deportivos) para no repetir el mismo sorteo+verificación tres
// veces. No reutiliza numero_cuenta porque los padres/madres de familia
// (torneos deportivos) no tienen uno.

function generarCodigoParticipante(PDO $pdo, int $idCompeticion): string
{
    $consulta = $pdo->prepare('SELECT 1 FROM integrantes WHERE codigo_participante = :codigo');

    do {
        $codigo = 'C' . $idCompeticion . '-' . strtoupper(bin2hex(random_bytes(4)));
        $consulta->execute(['codigo' => $codigo]);
    } while ($consulta->fetch() !== false);

    return $codigo;
}
