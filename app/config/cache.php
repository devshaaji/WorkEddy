<?php

return [
    'driver'  => getenv('CACHE_DRIVER') ?: 'redis',
    'prefix'  => getenv('CACHE_PREFIX') ?: 'cache:',
    'ttl'     => (int) (getenv('CACHE_TTL') ?: 3600),
    'path'    => getenv('CACHE_FILE_PATH') ?: __DIR__ . '/../../storage/cache',
];
