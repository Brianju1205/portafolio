<?php
require __DIR__ . '/lib.php';

$ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
$resp = @file_get_contents(OLLAMA_URL . '/api/tags', false, $ctx);

if ($resp === false) {
    responder(['ollama' => false, 'modelo' => OLLAMA_MODELO, 'instalado' => false]);
}

$json = json_decode($resp, true);
$instalado = false;
foreach ($json['models'] ?? [] as $m) {
    if (($m['name'] ?? '') === OLLAMA_MODELO || ($m['model'] ?? '') === OLLAMA_MODELO) {
        $instalado = true;
    }
}

responder(['ollama' => true, 'modelo' => OLLAMA_MODELO, 'instalado' => $instalado]);
