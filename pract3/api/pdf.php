<?php
/**
 * Entrega el PDF digitalizado de un documento.
 *
 *   GET pdf.php?id=5              → se muestra dentro del navegador (visor)
 *   GET pdf.php?id=5&descargar=1  → se descarga como archivo
 */

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

exigirMetodo('GET');

$id = enteroPositivo($_GET, 'id') ?? fallar('Documento no válido.');

$stmt = db()->prepare(
    'SELECT d.archivo_pdf, a.contenido, a.tamano_bytes, a.hash_sha256
       FROM documentos d
       JOIN documentos_archivos a ON a.id_documento = d.id_documento
      WHERE d.id_documento = ?'
);
$stmt->execute([$id]);
$pdf = $stmt->fetch() ?: fallar('El documento no tiene PDF asociado.', 404);

// Si el navegador ya tiene esta versión, no se reenvía el binario.
$etag = '"' . $pdf['hash_sha256'] . '"';
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$disposicion = isset($_GET['descargar']) ? 'attachment' : 'inline';
$nombre = basename((string) $pdf['archivo_pdf']) ?: "documento_$id.pdf";

header('Content-Type: application/pdf');
header('Content-Length: ' . $pdf['tamano_bytes']);
header("Content-Disposition: $disposicion; filename=\"$nombre\"");
header('Cache-Control: private, no-cache');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
echo $pdf['contenido'];
