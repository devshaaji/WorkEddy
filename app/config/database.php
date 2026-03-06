<?php

return [
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'workeddy',
    'user'     => getenv('DB_USER') ?: 'workeddy',
    'password' => getenv('DB_PASS') ?: 'workeddy',
    'charset'  => 'utf8mb4',
];
