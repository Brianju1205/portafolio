<?php
/**
 * Indicadores para el panel de inicio: totales por estatus y últimos registros.
 *
 *   GET resumen.php
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

exigirMetodo('GET');

$conteos = array_fill_keys(ESTATUS_VALIDOS, 0);
foreach (db()->query('SELECT estatus, COUNT(*) AS total FROM documentos GROUP BY estatus') as $fila) {
    $conteos[$fila['estatus']] = (int) $fila['total'];
}

$recientes = db()->query(
    'SELECT id_documento, no_oficio, fecha, asunto, remitente, estatus
       FROM documentos
      ORDER BY creado_en DESC, id_documento DESC
      LIMIT 5'
)->fetchAll();

$hoy = db()->prepare('SELECT COUNT(*) FROM documentos WHERE fecha = ?');
$hoy->execute([date('Y-m-d')]);

responder([
    'ok'   => true,
    'data' => [
        'total'      => array_sum($conteos),
        'por_estatus' => $conteos,
        'hoy'        => (int) $hoy->fetchColumn(),
        'recientes'  => array_map(static fn (array $f) => ['id_documento' => (int) $f['id_documento']] + $f, $recientes),
    ],
]);
