<?php
/**
 * Copia este archivo como config.local.php y ajusta tus credenciales.
 * config.local.php está excluido de Git; nunca subas contraseñas al repositorio.
 *
 * En Vercel o Docker estos valores se toman de variables de entorno.
 */
return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'gestion_documentos',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_SSL'  => 'false', // 'true' para bases en la nube (TiDB Cloud, Aiven…)
];
