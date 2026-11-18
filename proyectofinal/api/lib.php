<?php

const OLLAMA_URL = 'http://127.0.0.1:11434';
const OLLAMA_MODELO = 'llama3.2:3b';
const DIR_DATOS = __DIR__ . '/../data';

const INDICADORES = [
    'rend_campo'   => ['nombre' => 'Rendimiento de campo', 'unidad' => 't/ha', 'mayor_es_mejor' => true],
    'azucar_total' => ['nombre' => 'Azúcar producida total', 'unidad' => 't', 'mayor_es_mejor' => true],
    'cana_neta'    => ['nombre' => 'Caña molida neta', 'unidad' => 't', 'mayor_es_mejor' => true],
    'cana_bruta'   => ['nombre' => 'Caña molida bruta', 'unidad' => 't', 'mayor_es_mejor' => true],
    'superficie'   => ['nombre' => 'Superficie cosechada', 'unidad' => 'ha', 'mayor_es_mejor' => true],
    'rend_fabrica' => ['nombre' => 'Rendimiento de fábrica', 'unidad' => '%', 'mayor_es_mejor' => true],
    'eficiencia'   => ['nombre' => 'Eficiencia en fábrica', 'unidad' => '%', 'mayor_es_mejor' => true],
    'rend_agro'    => ['nombre' => 'Rendimiento agroindustrial', 'unidad' => 't/ha', 'mayor_es_mejor' => true],
];

const NOMBRES_INGENIO = [
    'azsuremex tenosique' => 'Azsuremex',
    'cuatotolapam' => 'Ciasa (Cuatotolapam)',
    'el potrero' => 'Central El Potrero',
    'jose ma martinez tala' => 'Tala',
    'tala siembra' => 'Tala',
    'jose ma morelos' => 'José María Morelos',
    'la providencia' => 'Central La Providencia',
    'la providencia central' => 'Central La Providencia',
    'lazaro cardenas' => 'Lázaro Cárdenas',
    'lazaro cardenas siembra' => 'Lázaro Cárdenas',
    'pablo machado la margarita' => 'La Margarita',
    'presidente benito juarez' => 'Presidente Benito Juárez',
    'presidente benito juarez santos' => 'Presidente Benito Juárez',
    'pujiltic cia la fe' => 'Cía. La Fe (Pujiltic)',
    'san cristobal zcmx' => 'San Cristóbal',
    'san miguelito' => 'Central San Miguelito',
    'san nicola s' => 'San Nicolás',
    'tamazula saenz' => 'Tamazula',
    'aaron saenz garza' => 'Aarón Sáenz Garza',
    'san rafael de pucte' => 'San Rafael de Pucté',
];

function responder($datos, $codigo = 200)
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function normalizar($texto)
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}

function tiene($texto, ...$palabras)
{
    foreach ($palabras as $p) {
        if (strpos($texto, $p) === false) {
            return false;
        }
    }
    return true;
}

function campoDeEncabezado($h)
{
    if ($h === '') return null;
    if ($h === 'zafra') return 'zafra';
    if (tiene($h, 'semana') || $h === 'no s zafra') return 'semana';
    if (tiene($h, 'ingenio') && !tiene($h, 'clave') && !tiene($h, 'id')) return 'ingenio';
    if (tiene($h, 'estado') || tiene($h, 'entidad')) return 'estado';
    if (tiene($h, 'agroindustrial')) return 'rend_agro';
    if (tiene($h, 'rendimiento', 'campo') || tiene($h, 'rend', 'campo')) return 'rend_campo';
    if (tiene($h, 'rendimiento', 'fabrica') || tiene($h, 'rend', 'fabrica')) return 'rend_fabrica';
    if (tiene($h, 'eficiencia')) return 'eficiencia';
    if (tiene($h, 'superficie')) return 'superficie';
    if (tiene($h, 'cana', 'bruta')) return 'cana_bruta';
    if (tiene($h, 'cana', 'neta')) return 'cana_neta';
    if (tiene($h, 'azucar')) {
        if (tiene($h, 'refin')) return 'refinada';
        if (tiene($h, 'estandar')) return 'estandar';
        if (tiene($h, 'blanca')) return 'blanca';
        if (tiene($h, 'mascabad')) return 'mascabado';
        if (tiene($h, 'total') || tiene($h, 'producid')) return 'azucar_total';
    }
    return null;
}

function aNumero($valor)
{
    $v = trim(str_replace(['%', ' ', "\xc2\xa0"], '', $valor));
    if ($v === '' || $v === '-') return null;
    $coma = strrpos($v, ',');
    $punto = strrpos($v, '.');
    if ($coma !== false && $punto !== false && $coma > $punto) {
        $v = str_replace(['.', ','], ['', '.'], $v);
    } elseif ($coma !== false && $punto === false && !preg_match('/^-?\d{1,3}(,\d{3})+$/', $v)) {
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return is_numeric($v) ? (float) $v : null;
}

function zafraDeArchivo($nombre)
{
    if (preg_match('/(\d{2})[_\-](\d{2})/', $nombre, $m)) {
        return '20' . $m[1] . '-20' . $m[2];
    }
    if (preg_match('/(20\d{2})[_\-](20\d{2})/', $nombre, $m)) {
        return $m[1] . '-' . $m[2];
    }
    return pathinfo($nombre, PATHINFO_FILENAME);
}

function leerCsv($ruta)
{
    $contenido = file_get_contents($ruta);
    if (substr($contenido, 0, 3) === "\xEF\xBB\xBF") {
        $contenido = substr($contenido, 3);
    }
    if (!mb_check_encoding($contenido, 'UTF-8')) {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }
    $lineas = preg_split('/\r\n|\n|\r/', $contenido);
    $primera = '';
    foreach ($lineas as $l) {
        if (trim($l) !== '') { $primera = $l; break; }
    }
    $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

    $filas = [];
    foreach ($lineas as $l) {
        if (trim($l) === '') continue;
        $filas[] = str_getcsv($l, $sep);
    }

    $mapa = null;
    $registros = [];
    $zafra = zafraDeArchivo(basename($ruta));

    foreach ($filas as $fila) {
        if ($mapa === null) {
            $candidato = [];
            foreach ($fila as $i => $celda) {
                $campo = campoDeEncabezado(normalizar($celda));
                if ($campo !== null && !isset($candidato[$campo])) {
                    $candidato[$campo] = $i;
                }
            }
            if (isset($candidato['ingenio']) && count($candidato) >= 3) {
                $mapa = $candidato;
            }
            continue;
        }

        $nombre = trim($fila[$mapa['ingenio']] ?? '');
        $n = normalizar($nombre);
        if ($n === '' || tiene($n, 'total') || tiene($n, 'nacional') || tiene($n, 'fuente')) continue;

        if (isset(NOMBRES_INGENIO[$n])) {
            $nombre = NOMBRES_INGENIO[$n];
            $n = normalizar($nombre);
        } else {
            $nombre = mb_convert_case(mb_strtolower($nombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            $nombre = preg_replace_callback('/ (De|Del) /u', function ($m) {
                return ' ' . mb_strtolower($m[1], 'UTF-8') . ' ';
            }, $nombre);
        }

        $z = isset($mapa['zafra']) ? trim($fila[$mapa['zafra']] ?? '') : '';
        $r = [
            'clave' => $n,
            'ingenio' => $nombre,
            'zafra' => preg_match('/^\d{4}-\d{4}$/', $z) ? $z : $zafra,
            'estado' => isset($mapa['estado']) ? trim($fila[$mapa['estado']] ?? '') : '',
            'semana' => isset($mapa['semana']) ? (int) aNumero($fila[$mapa['semana']] ?? '') : 0,
        ];
        foreach ($mapa as $campo => $i) {
            if (in_array($campo, ['ingenio', 'estado', 'zafra', 'semana'], true)) continue;
            $r[$campo] = aNumero($fila[$i] ?? '');
        }

        $llave = $r['clave'] . '|' . $r['zafra'];
        if (isset($registros[$llave]) && $registros[$llave]['semana'] > $r['semana']) continue;
        $registros[$llave] = $r;
    }

    $finales = [];
    foreach ($registros as $r) {

        if (!isset($r['azucar_total']) || $r['azucar_total'] === null) {
            $suma = 0; $hay = false;
            foreach (['refinada', 'estandar', 'blanca', 'mascabado'] as $t) {
                if (isset($r[$t]) && $r[$t] !== null) { $suma += $r[$t]; $hay = true; }
            }
            $r['azucar_total'] = $hay ? $suma : null;
        }
        if ((!isset($r['rend_campo']) || $r['rend_campo'] === null) && !empty($r['superficie'])) {
            $cana = $r['cana_neta'] ?? $r['cana_bruta'] ?? null;
            $r['rend_campo'] = $cana !== null ? $cana / $r['superficie'] : null;
        }
        if ((!isset($r['rend_agro']) || $r['rend_agro'] === null) && !empty($r['superficie']) && $r['azucar_total'] !== null) {
            $r['rend_agro'] = $r['azucar_total'] / $r['superficie'];
        }

        $tieneDatos = false;
        foreach (array_keys(INDICADORES) as $k) {
            if (isset($r[$k]) && $r[$k] !== null && $r[$k] != 0) { $tieneDatos = true; break; }
        }
        if ($tieneDatos) $finales[] = $r;
    }

    return $finales;
}

function cargarDatos()
{
    $archivos = glob(DIR_DATOS . '/*.csv') ?: [];
    $cache = DIR_DATOS . '/.cache.json';
    $firma = '';
    foreach ($archivos as $a) {
        $firma .= basename($a) . filesize($a) . filemtime($a);
    }
    $firma = md5($firma);

    if (is_file($cache)) {
        $guardado = json_decode(file_get_contents($cache), true);
        if (($guardado['firma'] ?? '') === $firma) {
            return $guardado;
        }
    }

    $registros = [];
    $leidos = [];
    foreach ($archivos as $a) {
        $filas = leerCsv($a);
        $semanas = array_column($filas, 'semana');
        $leidos[] = [
            'archivo' => basename($a),
            'zafra' => $filas ? $filas[0]['zafra'] : zafraDeArchivo(basename($a)),
            'registros' => count($filas),
            'semana_max' => $semanas ? max($semanas) : 0,
        ];
        $registros = array_merge($registros, $filas);
    }

    $nombres = [];
    foreach ($registros as $r) {
        $actual = $nombres[$r['clave']] ?? '';
        if ($actual === '' || (strlen($r['ingenio']) > strlen($actual))) {
            $nombres[$r['clave']] = $r['ingenio'];
        }
    }
    foreach ($registros as &$r) {
        $r['ingenio'] = $nombres[$r['clave']];
        unset($r['clave']);
    }
    unset($r);

    $resultado = ['firma' => $firma, 'archivos' => $leidos, 'registros' => $registros];
    @file_put_contents($cache, json_encode($resultado, JSON_UNESCAPED_UNICODE));
    return $resultado;
}

function promedio($valores)
{
    return count($valores) ? array_sum($valores) / count($valores) : null;
}

function redondear($n, $d = 2)
{
    return $n === null ? null : round($n, $d);
}
