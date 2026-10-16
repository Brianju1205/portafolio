<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

const COLUMNAS = 'd.id_documento, d.no_oficio, d.fecha, d.asunto, d.remitente, d.destinatario,
                  d.estatus, d.archivo_pdf, d.creado_en, d.actualizado_en, a.tamano_bytes';

$metodo = exigirMetodo('GET', 'POST');

if ($metodo === 'GET') {
    $id = enteroPositivo($_GET, 'id');
    $id !== null ? obtenerDocumento($id) : listarDocumentos($_GET);
}

match ($_POST['accion'] ?? '') {
    'guardar'  => guardarDocumento($_POST, $_FILES['archivo'] ?? null),
    'estatus'  => cambiarEstatus($_POST),
    'eliminar' => eliminarDocumento($_POST),
    default    => fallar('Acción no válida.'),
};

function obtenerDocumento(int $id): never
{
    $documento = buscarPorId($id);
    if ($documento === null) {
        fallar('El documento no existe.', 404);
    }
    responder(['ok' => true, 'data' => $documento]);
}

function buscarPorId(int $id): ?array
{
    $sql = 'SELECT ' . COLUMNAS . '
              FROM documentos d
              LEFT JOIN documentos_archivos a ON a.id_documento = d.id_documento
             WHERE d.id_documento = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila === false ? null : normalizar($fila);
}

function listarDocumentos(array $entrada): never
{
    $condiciones = [];
    $parametros = [];

    $q = textoFiltro($entrada, 'q');
    if ($q !== '') {
        $condiciones[] = '(d.no_oficio LIKE ? OR d.asunto LIKE ? OR d.remitente LIKE ? OR d.destinatario LIKE ?)';
        array_push($parametros, ...array_fill(0, 4, comodin($q)));
    }

    foreach (['no_oficio', 'asunto', 'remitente', 'destinatario'] as $campo) {
        $valor = textoFiltro($entrada, $campo);
        if ($valor !== '') {
            $condiciones[] = "d.$campo LIKE ?";
            $parametros[] = comodin($valor);
        }
    }

    $estatus = textoFiltro($entrada, 'estatus');
    if ($estatus !== '') {
        if (!in_array($estatus, ESTATUS_VALIDOS, true)) {
            fallar('Estatus no válido.');
        }
        $condiciones[] = 'd.estatus = ?';
        $parametros[] = $estatus;
    }

    foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
        $fecha = textoFiltro($entrada, $campo);
        if ($fecha !== '') {
            if (!fechaValida($fecha)) {
                fallar("La fecha «{$campo}» no es válida.");
            }
            $condiciones[] = "d.fecha $operador ?";
            $parametros[] = $fecha;
        }
    }

    $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

    $stmt = db()->prepare("SELECT COUNT(*) FROM documentos d $where");
    $stmt->execute($parametros);
    $total = (int) $stmt->fetchColumn();

    $porPagina = min(enteroPositivo($entrada, 'por_pagina') ?? 10, 50);
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min(enteroPositivo($entrada, 'pagina') ?? 1, $paginas);
    $desplazamiento = ($pagina - 1) * $porPagina;

    $sql = 'SELECT ' . COLUMNAS . "
              FROM documentos d
              LEFT JOIN documentos_archivos a ON a.id_documento = d.id_documento
              $where
             ORDER BY d.fecha DESC, d.id_documento DESC
             LIMIT $porPagina OFFSET $desplazamiento";
    $stmt = db()->prepare($sql);
    $stmt->execute($parametros);

    responder([
        'ok'         => true,
        'data'       => array_map('normalizar', $stmt->fetchAll()),
        'total'      => $total,
        'pagina'     => $pagina,
        'paginas'    => $paginas,
        'por_pagina' => $porPagina,
    ]);
}

function guardarDocumento(array $entrada, ?array $archivo): never
{
    $id = enteroPositivo($entrada, 'id');
    $existente = $id !== null ? buscarPorId($id) : null;
    if ($id !== null && $existente === null) {
        fallar('El documento que intentas editar no existe.', 404);
    }

    $datos = validarCampos($entrada);
    $pdf = leerPdf($archivo, obligatorio: $existente === null || $existente['tamano_bytes'] === null);
    $datos['archivo_pdf'] = 'oficio_' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $datos['no_oficio']) . '.pdf';
    $anterior = $existente['archivo_pdf'] ?? null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($existente === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO documentos (no_oficio, fecha, asunto, remitente, destinatario, estatus, archivo_pdf)
                 VALUES (:no_oficio, :fecha, :asunto, :remitente, :destinatario, :estatus, :archivo_pdf)'
            );
            $stmt->execute($datos);
            $id = (int) $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare(
                'UPDATE documentos
                    SET no_oficio = :no_oficio, fecha = :fecha, asunto = :asunto, remitente = :remitente,
                        destinatario = :destinatario, estatus = :estatus, archivo_pdf = :archivo_pdf
                  WHERE id_documento = :id'
            );
            $stmt->execute($datos + ['id' => $id]);
        }

        if ($pdf !== null && guardarEnCarpeta()) {
            if (file_put_contents(rutaPdf($datos['archivo_pdf']), $pdf) === false) {
                throw new RuntimeException('No se pudo escribir el PDF en la carpeta pdf/.');
            }
            $pdo->prepare('DELETE FROM documentos_archivos WHERE id_documento = ?')->execute([$id]);
        } elseif ($pdf !== null) {
            $stmt = $pdo->prepare(
                'REPLACE INTO documentos_archivos (id_documento, contenido, tamano_bytes, hash_sha256)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->bindValue(1, $id, PDO::PARAM_INT);
            $stmt->bindValue(2, $pdf, PDO::PARAM_LOB);
            $stmt->bindValue(3, strlen($pdf), PDO::PARAM_INT);
            $stmt->bindValue(4, hash('sha256', $pdf));
            $stmt->execute();
        }

        if ($anterior !== null && $anterior !== $datos['archivo_pdf'] && is_file(rutaPdf($anterior))) {
            $pdf === null ? rename(rutaPdf($anterior), rutaPdf($datos['archivo_pdf'])) : unlink(rutaPdf($anterior));
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            fallar('Ya existe un documento con el No. de Oficio «' . $datos['no_oficio'] . '».', 409, ['campo' => 'no_oficio']);
        }
        throw $e;
    }

    $mensaje = $existente === null ? 'Documento registrado correctamente.' : 'Documento actualizado correctamente.';
    responder(['ok' => true, 'mensaje' => $mensaje, 'data' => buscarPorId($id)], $existente === null ? 201 : 200);
}

function cambiarEstatus(array $entrada): never
{
    $id = enteroPositivo($entrada, 'id') ?? fallar('Documento no válido.');
    $estatus = trim((string) ($entrada['estatus'] ?? ''));
    if (!in_array($estatus, ESTATUS_VALIDOS, true)) {
        fallar('Estatus no válido.');
    }

    $stmt = db()->prepare('UPDATE documentos SET estatus = ? WHERE id_documento = ?');
    $stmt->execute([$estatus, $id]);
    $documento = buscarPorId($id) ?? fallar('El documento no existe.', 404);

    responder(['ok' => true, 'mensaje' => "Estatus actualizado a «{$estatus}».", 'data' => $documento]);
}

function eliminarDocumento(array $entrada): never
{
    $id = enteroPositivo($entrada, 'id') ?? fallar('Documento no válido.');

    $documento = buscarPorId($id) ?? fallar('El documento no existe.', 404);

    db()->prepare('DELETE FROM documentos WHERE id_documento = ?')->execute([$id]);
    if ($documento['archivo_pdf'] !== null && is_file(rutaPdf($documento['archivo_pdf']))) {
        unlink(rutaPdf($documento['archivo_pdf']));
    }
    responder(['ok' => true, 'mensaje' => 'Documento eliminado.']);
}

function validarCampos(array $entrada): array
{
    $reglas = [
        'no_oficio'    => ['No. de Oficio', 50],
        'asunto'       => ['Asunto', 255],
        'remitente'    => ['Remitente', 150],
        'destinatario' => ['Destinatario', 150],
    ];

    $datos = [];
    $errores = [];

    foreach ($reglas as $campo => [$etiqueta, $maximo]) {
        $valor = trim(preg_replace('/\s+/u', ' ', (string) ($entrada[$campo] ?? '')));
        if ($valor === '') {
            $errores[$campo] = "$etiqueta es obligatorio.";
        } elseif (mb_strlen($valor) > $maximo) {
            $errores[$campo] = "$etiqueta admite como máximo $maximo caracteres.";
        }
        $datos[$campo] = $valor;
    }

    $datos['fecha'] = trim((string) ($entrada['fecha'] ?? ''));
    if (!fechaValida($datos['fecha'])) {
        $errores['fecha'] = 'Indica una fecha válida.';
    } elseif ($datos['fecha'] > date('Y-m-d', strtotime('+1 day'))) {
        $errores['fecha'] = 'La fecha de recepción no puede ser futura.';
    }

    $datos['estatus'] = trim((string) ($entrada['estatus'] ?? ''));
    if (!in_array($datos['estatus'], ESTATUS_VALIDOS, true)) {
        $errores['estatus'] = 'Selecciona un estatus válido.';
    }

    if ($errores) {
        fallar('Revisa los campos marcados.', 422, ['errores' => $errores]);
    }

    return [
        'no_oficio'    => $datos['no_oficio'],
        'fecha'        => $datos['fecha'],
        'asunto'       => $datos['asunto'],
        'remitente'    => $datos['remitente'],
        'destinatario' => $datos['destinatario'],
        'estatus'      => $datos['estatus'],
    ];
}

function leerPdf(?array $archivo, bool $obligatorio): ?string
{
    $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        if ($obligatorio) {
            fallar('Adjunta el documento digitalizado (foto o PDF).', 422, ['errores' => ['archivo' => 'El documento es obligatorio.']]);
        }
        return null;
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($archivo['size'] ?? 0) > PDF_TAMANO_MAXIMO) {
        fallar('El PDF supera el tamaño máximo de 4 MB.', 422, ['errores' => ['archivo' => 'Máximo 4 MB.']]);
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        fallar('No se pudo recibir el archivo. Inténtalo de nuevo.', 422, ['errores' => ['archivo' => 'Error al subir.']]);
    }

    $contenido = file_get_contents($archivo['tmp_name']);
    if ($contenido === false || !str_starts_with($contenido, '%PDF-')) {
        fallar('El archivo debe ser un PDF válido.', 422, ['errores' => ['archivo' => 'Solo se aceptan PDF.']]);
    }
    return $contenido;
}

function fechaValida(string $fecha): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}

function textoFiltro(array $entrada, string $clave): string
{
    return mb_substr(trim((string) ($entrada[$clave] ?? '')), 0, 100);
}

function comodin(string $texto): string
{
    return '%' . addcslashes($texto, '\\%_') . '%';
}

function normalizar(array $fila): array
{
    $fila['id_documento'] = (int) $fila['id_documento'];
    $fila['tamano_bytes'] = $fila['tamano_bytes'] === null ? null : (int) $fila['tamano_bytes'];
    if ($fila['tamano_bytes'] === null && $fila['archivo_pdf'] !== null && is_file(rutaPdf($fila['archivo_pdf']))) {
        $fila['tamano_bytes'] = filesize(rutaPdf($fila['archivo_pdf']));
    }
    return $fila;
}
