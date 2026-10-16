<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Mexico_City');

const ESTATUS_VALIDOS = ['Recibido', 'En proceso', 'Atendido'];
const PDF_TAMANO_MAXIMO = 4 * 1024 * 1024;
const CARPETA_PDF = __DIR__ . '/../../pdf';

function guardarEnCarpeta(): bool
{
    return is_dir(CARPETA_PDF) && is_writable(CARPETA_PDF);
}

function rutaPdf(string $nombre): string
{
    return CARPETA_PDF . '/' . basename($nombre);
}

function config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = [];
    $archivoLocal = __DIR__ . '/../config.local.php';
    if (is_file($archivoLocal)) {
        $local = require $archivoLocal;
    }

    $leer = static function (string $clave, string $porDefecto) use ($local): string {
        $valor = getenv($clave);
        if ($valor !== false && $valor !== '') {
            return $valor;
        }
        return (string) ($local[$clave] ?? $porDefecto);
    };

    $config = [
        'host'   => $leer('DB_HOST', '127.0.0.1'),
        'port'   => (int) $leer('DB_PORT', '3306'),
        'nombre' => $leer('DB_NAME', 'gestion_documentos'),
        'usuario' => $leer('DB_USER', 'root'),
        'clave'  => $leer('DB_PASS', ''),
        'ssl'    => filter_var($leer('DB_SSL', 'false'), FILTER_VALIDATE_BOOLEAN),
    ];
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $c = config();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['nombre']);
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($c['ssl']) {
        $ca = rutaCertificadosCA();
        if ($ca !== null) {
            $opciones[atributoMysql('ATTR_SSL_CA')] = $ca;
        }
        $opciones[atributoMysql('ATTR_SSL_VERIFY_SERVER_CERT')] = true;
    }

    $pdo = new PDO($dsn, $c['usuario'], $c['clave'], $opciones);
    return $pdo;
}

function atributoMysql(string $nombre): int
{
    if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::' . $nombre)) {
        return constant('Pdo\Mysql::' . $nombre);
    }
    return constant('PDO::MYSQL_' . $nombre);
}

function rutaCertificadosCA(): ?string
{
    $candidatos = [
        getenv('DB_SSL_CA') ?: '',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
    ];
    foreach ($candidatos as $ruta) {
        if ($ruta !== '' && is_file($ruta)) {
            return $ruta;
        }
    }
    return null;
}

function responder(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function fallar(string $mensaje, int $codigo = 400, array $extra = []): never
{
    responder(['ok' => false, 'error' => $mensaje] + $extra, $codigo);
}

function exigirMetodo(string ...$metodos): string
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($metodo, $metodos, true)) {
        header('Allow: ' . implode(', ', $metodos));
        fallar('Método no permitido.', 405);
    }
    return $metodo;
}

function enteroPositivo(array $origen, string $clave): ?int
{
    $valor = filter_var($origen[$clave] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $valor === false ? null : $valor;
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[gestion-documentos] ' . $e);
    $mensaje = $e instanceof PDOException
        ? 'No se pudo conectar o consultar la base de datos.'
        : 'Ocurrió un error inesperado en el servidor.';
    responder(['ok' => false, 'error' => $mensaje], 500);
});
