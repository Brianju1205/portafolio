<?php
require __DIR__ . '/lib.php';

$datos = cargarDatos();

$ingenios = [];
$zafras = [];
foreach ($datos['registros'] as $r) {
    $ingenios[$r['ingenio']] = true;
    $zafras[$r['zafra']] = true;
}
$ingenios = array_keys($ingenios);
$zafras = array_keys($zafras);
sort($ingenios, SORT_LOCALE_STRING);
sort($zafras);

$indicadores = [];
foreach (INDICADORES as $clave => $info) {
    foreach ($datos['registros'] as $r) {
        if (isset($r[$clave]) && $r[$clave] !== null) {
            $indicadores[] = ['clave' => $clave, 'nombre' => $info['nombre'], 'unidad' => $info['unidad']];
            break;
        }
    }
}

responder([
    'ingenios' => $ingenios,
    'zafras' => $zafras,
    'indicadores' => $indicadores,
    'archivos' => $datos['archivos'],
    'total_registros' => count($datos['registros']),
]);
