<?php
// Muestra o descarga el PDF de un documento

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

exigirMetodo('GET');

$id = enteroPositivo($_GET, 'id') ?? fallar('Documento no válido.');

$stmt = db()->prepare('SELECT archivo_pdf FROM documentos WHERE id_documento = ?');
$stmt->execute([$id]);
$nombre = $stmt->fetchColumn();
if ($nombre === false) {
    fallar('El documento no existe.', 404);
}
$nombre = basename((string) $nombre) ?: "documento_$id.pdf";

if (is_file(rutaPdf($nombre))) {
    $contenido = file_get_contents(rutaPdf($nombre));
} else {
    $stmt = db()->prepare('SELECT contenido FROM documentos_archivos WHERE id_documento = ?');
    $stmt->execute([$id]);
    $contenido = $stmt->fetchColumn();
}
if ($contenido === false || $contenido === null) {
    fallar('El documento no tiene PDF asociado.', 404);
}

// Evita reenviar el PDF si no cambió
$etag = '"' . hash('sha256', $contenido) . '"';
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$disposicion = isset($_GET['descargar']) ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($contenido));
header("Content-Disposition: $disposicion; filename=\"$nombre\"");
header('Cache-Control: private, no-cache');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
echo $contenido;
