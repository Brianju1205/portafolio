<?php
require __DIR__ . '/lib.php';
set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

$entrada = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$ingenio = trim($entrada['ingenio'] ?? 'todos');
$zafra = trim($entrada['zafra'] ?? 'todas');
$indicador = trim($entrada['indicador'] ?? 'rend_campo');
$pregunta = trim($entrada['pregunta'] ?? '');
$usarIa = ($entrada['usar_ia'] ?? true) !== false;

if (!isset(INDICADORES[$indicador])) {
    responder(['error' => 'Indicador no válido'], 400);
}
if (mb_strlen($pregunta) < 5 || mb_strlen($pregunta) > 500) {
    responder(['error' => 'La pregunta debe tener entre 5 y 500 caracteres'], 400);
}

$datos = cargarDatos();
if (!$datos['registros']) {
    responder(['error' => 'No hay archivos CSV en la carpeta data/'], 500);
}

$info = INDICADORES[$indicador];
$unidad = $info['unidad'];
$esAcumulado = in_array($unidad, ['t', 'ha'], true);

$base = array_values(array_filter($datos['registros'], function ($r) use ($indicador) {
    return isset($r[$indicador]) && $r[$indicador] !== null && $r[$indicador] > 0;
}));

$zafrasDisponibles = array_values(array_unique(array_column($base, 'zafra')));
sort($zafrasDisponibles);
$ingeniosDisponibles = array_values(array_unique(array_column($base, 'ingenio')));

if ($zafra !== 'todas' && !in_array($zafra, $zafrasDisponibles, true)) {
    responder(['error' => 'La zafra seleccionada no tiene datos para este indicador'], 400);
}
if ($ingenio !== 'todos' && !in_array($ingenio, $ingeniosDisponibles, true)) {
    responder(['error' => 'El ingenio seleccionado no tiene datos para este indicador'], 400);
}

$parciales = [];
$semanas = array_column($datos['archivos'], 'semana_max');
sort($semanas);
$mediana = $semanas ? $semanas[intdiv(count($semanas), 2)] : 0;
foreach ($datos['archivos'] as $a) {
    if ($a['semana_max'] && $a['semana_max'] < $mediana * 0.6) {
        $parciales[$a['zafra']] = $a['semana_max'];
    }
}

$enPeriodo = array_values(array_filter($base, function ($r) use ($zafra, $esAcumulado, $parciales) {
    if ($zafra !== 'todas') return $r['zafra'] === $zafra;
    return !($esAcumulado && isset($parciales[$r['zafra']]));
}));

$subconjunto = array_values(array_filter($enPeriodo, function ($r) use ($ingenio) {
    return $ingenio === 'todos' || $r['ingenio'] === $ingenio;
}));

if (!$subconjunto) {
    responder(['error' => 'No hay registros con los filtros seleccionados'], 404);
}

$promedioGeneral = promedio(array_column($enPeriodo, $indicador));
$valoresSub = array_column($subconjunto, $indicador);
$promedioSub = promedio($valoresSub);

$mejor = null;
foreach ($subconjunto as $r) {
    if ($mejor === null || $r[$indicador] > $mejor[$indicador]) $mejor = $r;
}
$peor = null;
foreach ($subconjunto as $r) {
    if ($peor === null || $r[$indicador] < $peor[$indicador]) $peor = $r;
}

$zafrasSub = array_values(array_unique(array_column($subconjunto, 'zafra')));
sort($zafrasSub);

$porIngenio = [];
foreach ($enPeriodo as $r) {
    $porIngenio[$r['ingenio']]['valores'][] = $r[$indicador];
    $porIngenio[$r['ingenio']]['estado'] = $r['estado'];
}
$ranking = [];
foreach ($porIngenio as $nombre => $g) {
    $prom = promedio($g['valores']);
    $ranking[] = [
        'ingenio' => $nombre,
        'estado' => $g['estado'],
        'valor' => redondear($prom),
        'zafras' => count($g['valores']),
        'vs_promedio' => $promedioGeneral ? redondear(($prom / $promedioGeneral - 1) * 100, 1) : null,
    ];
}
usort($ranking, function ($a, $b) {
    return $b['valor'] <=> $a['valor'];
});
foreach ($ranking as $i => &$fila) {
    $fila['posicion'] = $i + 1;
}
unset($fila);

$posicionIngenio = null;
if ($ingenio !== 'todos') {
    foreach ($ranking as $fila) {
        if ($fila['ingenio'] === $ingenio) $posicionIngenio = $fila;
    }
}

$serie = [];
foreach ($zafrasDisponibles as $z) {
    $deZafra = array_filter($base, function ($r) use ($z) { return $r['zafra'] === $z; });
    $valores = array_column($deZafra, $indicador);
    $punto = ['zafra' => $z, 'promedio_nacional' => redondear(promedio($valores))];
    if ($esAcumulado) $punto['total_nacional'] = redondear(array_sum($valores));
    if ($ingenio !== 'todos') {
        $propio = array_values(array_filter($deZafra, function ($r) use ($ingenio) { return $r['ingenio'] === $ingenio; }));
        $punto['ingenio'] = $propio ? redondear($propio[0][$indicador]) : null;
    }
    $serie[] = $punto;
}

$claveSerie = $ingenio !== 'todos' ? 'ingenio' : 'promedio_nacional';
$conValor = array_values(array_filter($serie, function ($p) use ($claveSerie, $esAcumulado, $parciales) {
    return $p[$claveSerie] !== null && !($esAcumulado && isset($parciales[$p['zafra']]));
}));
$ultimas = array_slice($conValor, -5);
$tendencia = null;
if (count($ultimas) >= 2) {
    $ini = $ultimas[0][$claveSerie];
    $fin = $ultimas[count($ultimas) - 1][$claveSerie];
    $tendencia = [
        'desde' => $ultimas[0]['zafra'],
        'hasta' => $ultimas[count($ultimas) - 1]['zafra'],
        'inicio' => $ini,
        'fin' => $fin,
        'variacion' => $ini ? redondear(($fin / $ini - 1) * 100, 1) : null,
    ];
}

$kpis = [
    'ingenios' => count(array_unique(array_column($subconjunto, 'ingenio'))),
    'registros' => count($subconjunto),
    'promedio' => redondear($promedioSub),
    'promedio_general' => redondear($promedioGeneral),
    'mejor' => ['valor' => redondear($mejor[$indicador]), 'ingenio' => $mejor['ingenio'], 'zafra' => $mejor['zafra']],
    'peor' => ['valor' => redondear($peor[$indicador]), 'ingenio' => $peor['ingenio'], 'zafra' => $peor['zafra']],
    'periodo' => count($zafrasSub) > 1 ? $zafrasSub[0] . ' a ' . end($zafrasSub) : $zafrasSub[0],
];
if ($esAcumulado) {
    $kpis['total'] = redondear(array_sum($valoresSub));
}

$f = function ($n) { return number_format($n, 2, '.', ','); };
$signo = function ($n) { return ($n >= 0 ? '+' : '') . number_format($n, 1) . '%'; };

$hechos = [];
$hechos[] = "El promedio de {$info['nombre']} de todos los ingenios " . ($zafra === 'todas' ? 'en el periodo' : "en la zafra $zafra") . ' es ' . $f($promedioGeneral) . " $unidad.";
if ($ingenio === 'todos') {
    $top = array_slice($ranking, 0, 3);
    $partes = array_map(function ($r) use ($f, $signo, $unidad) {
        return "{$r['ingenio']} con " . $f($r['valor']) . " $unidad (" . $signo($r['vs_promedio']) . ')';
    }, $top);
    $que = $zafra === 'todas' ? 'promedio de todas las zafras' : "valor en la zafra $zafra";
    $hechos[] = "Los tres ingenios con mejor $que son: " . implode(', ', $partes) . '.';
    $ultimo = end($ranking);
    $hechos[] = "El ingenio con el $que más bajo es {$ultimo['ingenio']} con " . $f($ultimo['valor']) . " $unidad.";
} else {
    $dif = $promedioGeneral ? ($promedioSub / $promedioGeneral - 1) * 100 : 0;
    $hechos[] = "El promedio de $ingenio en el periodo es " . $f($promedioSub) . " $unidad, " . $signo($dif) . ' respecto al promedio de todos los ingenios.';
    if ($posicionIngenio) {
        $hechos[] = "$ingenio ocupa el lugar {$posicionIngenio['posicion']} de " . count($ranking) . ' ingenios.';
    }
}
if ($tendencia) {
    $quien = $ingenio === 'todos' ? 'el promedio de los ingenios' : $ingenio;
    $hechos[] = "En las últimas zafras $quien pasó de " . $f($tendencia['inicio']) . " $unidad en {$tendencia['desde']} a " . $f($tendencia['fin']) . " $unidad en {$tendencia['hasta']} (" . $signo($tendencia['variacion']) . ').';
}
if ($esAcumulado && $zafra !== 'todas') {
    $hechos[] = "En la zafra $zafra el ingenio con el valor más alto fue {$ranking[0]['ingenio']} con " . $f($ranking[0]['valor']) . " $unidad.";
}

$lineas = [];
$lineas[] = 'HECHOS CLAVE (cifras exactas):';
foreach ($hechos as $h) {
    $lineas[] = '- ' . $h;
}
$lineas[] = '';
$lineas[] = 'DETALLE:';
$lineas[] = 'Fuente: Infocaña, CONADESUCA (datos.gob.mx). Cálculos hechos por el sistema con ' . count($datos['archivos']) . ' archivos CSV.';
$lineas[] = "Indicador: {$info['nombre']} ({$unidad}).";
$lineas[] = 'Filtro de ingenio: ' . ($ingenio === 'todos' ? 'todos los ingenios' : $ingenio) . '.';
$lineas[] = 'Periodo: ' . ($zafra === 'todas' ? 'todas las zafras (' . $zafrasDisponibles[0] . ' a ' . end($zafrasDisponibles) . ')' : "zafra $zafra") . '.';
$lineas[] = "Ingenios analizados: {$kpis['ingenios']}. Registros: {$kpis['registros']}.";
$lineas[] = 'Promedio de todos los ingenios en el periodo: ' . $f($promedioGeneral) . " $unidad.";
if ($ingenio !== 'todos') {
    $dif = $promedioGeneral ? ($promedioSub / $promedioGeneral - 1) * 100 : 0;
    $lineas[] = "Promedio de $ingenio en el periodo: " . $f($promedioSub) . " $unidad (" . ($dif >= 0 ? '+' : '') . number_format($dif, 1) . '% respecto al promedio de todos los ingenios).';
    if ($posicionIngenio) {
        $lineas[] = "Posición de $ingenio en el ranking: {$posicionIngenio['posicion']} de " . count($ranking) . '.';
    }
}
$lineas[] = 'Valor más alto en una sola zafra (no es promedio): ' . $f($mejor[$indicador]) . " $unidad ({$mejor['ingenio']}, zafra {$mejor['zafra']}).";
$lineas[] = 'Valor más bajo en una sola zafra (no es promedio): ' . $f($peor[$indicador]) . " $unidad ({$peor['ingenio']}, zafra {$peor['zafra']}).";
if ($esAcumulado) {
    $lineas[] = 'Total acumulado del subconjunto: ' . $f($kpis['total']) . " $unidad.";
}
$lineas[] = '';
$lineas[] = 'Ranking (promedio del periodo, los 10 primeros):';
foreach (array_slice($ranking, 0, 10) as $fila) {
    $lineas[] = "{$fila['posicion']}. {$fila['ingenio']}" . ($fila['estado'] ? " ({$fila['estado']})" : '') . ': promedio ' . $f($fila['valor']) . " $unidad en {$fila['zafras']} zafra(s), " . ($fila['vs_promedio'] >= 0 ? '+' : '') . "{$fila['vs_promedio']}% vs el promedio de todos.";
}
$lineas[] = '';
$lineas[] = 'Últimos lugares del ranking:';
foreach (array_slice($ranking, -3) as $fila) {
    $lineas[] = "{$fila['posicion']}. {$fila['ingenio']}: " . $f($fila['valor']) . " $unidad.";
}
$lineas[] = '';
$lineas[] = 'Serie histórica por zafra' . ($ingenio !== 'todos' ? " ($ingenio vs promedio de ingenios)" : ' (promedio de ingenios)') . ':';
foreach ($serie as $p) {
    $txt = "- {$p['zafra']}: promedio " . $f($p['promedio_nacional']) . " $unidad";
    if ($ingenio !== 'todos') {
        $txt .= ', ' . $ingenio . ': ' . ($p['ingenio'] !== null ? $f($p['ingenio']) . " $unidad" : 'sin dato');
    }
    $lineas[] = $txt;
}
if ($tendencia) {
    $lineas[] = "Variación en las últimas zafras ({$tendencia['desde']} a {$tendencia['hasta']}): " . ($tendencia['variacion'] >= 0 ? '+' : '') . "{$tendencia['variacion']}%.";
}
$notas = [];
foreach ($parciales as $z => $sem) {
    if ($zafra !== 'todas' && $zafra !== $z) continue;
    $nota = "La zafra $z sigue en curso: sus datos llegan solo hasta la semana $sem, por eso sus valores acumulados son parciales.";
    if ($esAcumulado && $zafra === 'todas') {
        $nota .= ' No se incluyó en el ranking ni en la tendencia de este indicador.';
    }
    $notas[] = $nota;
}
if ($notas) {
    $lineas[] = '';
    $lineas[] = 'Nota: ' . implode(' ', $notas);
}
$contexto = implode("\n", $lineas);

$respuesta = null;
$errorIa = null;
$inicio = microtime(true);

if ($usarIa) {
    $sistema = 'Eres un analista de la agroindustria de la caña de azúcar en México. '
        . 'Respondes en español, de forma clara y breve (máximo 3 párrafos cortos). '
        . 'Usa únicamente las cifras del CONTEXTO, que ya fueron calculadas por el sistema; no inventes datos ni hagas cálculos nuevos. '
        . 'Basa tu respuesta en la sección HECHOS CLAVE y copia las cifras tal como aparecen ahí. '
        . 'No calcules promedios, sumas ni porcentajes nuevos. '
        . 'No confundas un promedio con el valor de una sola zafra. '
        . 'Si el contexto no alcanza para responder, dilo. '
        . 'Termina siempre con un párrafo que empiece con "Recomendación:".';

    $cuerpo = json_encode([
        'model' => OLLAMA_MODELO,
        'stream' => false,
        'options' => ['temperature' => 0.1, 'num_ctx' => 4096],
        'messages' => [
            ['role' => 'system', 'content' => $sistema],
            ['role' => 'user', 'content' => "CONTEXTO:\n$contexto\n\nPREGUNTA DEL USUARIO:\n$pregunta"],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $cuerpo,
        'timeout' => 240,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents(OLLAMA_URL . '/api/chat', false, $ctx);

    if ($resp === false) {
        $errorIa = 'No se pudo conectar con Ollama en ' . OLLAMA_URL . '. Verifica que esté abierto.';
    } else {
        $json = json_decode($resp, true);
        if (isset($json['error'])) {
            $errorIa = 'Ollama respondió: ' . $json['error'];
        } else {
            $respuesta = trim($json['message']['content'] ?? '');
            if ($respuesta === '') $errorIa = 'El modelo no devolvió texto.';
        }
    }
}

responder([
    'filtros' => ['ingenio' => $ingenio, 'zafra' => $zafra, 'indicador' => $indicador, 'pregunta' => $pregunta],
    'indicador' => ['clave' => $indicador, 'nombre' => $info['nombre'], 'unidad' => $unidad],
    'kpis' => $kpis,
    'ranking' => array_slice($ranking, 0, 10),
    'posicion_ingenio' => $posicionIngenio,
    'total_ranking' => count($ranking),
    'serie' => $serie,
    'tendencia' => $tendencia,
    'contexto' => $contexto,
    'notas' => $notas,
    'modelo' => OLLAMA_MODELO,
    'respuesta' => $respuesta,
    'error_ia' => $errorIa,
    'segundos' => round(microtime(true) - $inicio, 1),
]);
