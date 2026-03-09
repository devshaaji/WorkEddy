<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class Database
{
    private static ?Connection $instance = null;

    public static function connection(): Connection
    {
        if (self::$instance === null) {
            self::$instance = DriverManager::getConnection([
                'dbname'   => getenv('DB_NAME')  ?: 'workeddy',
                'user'     => getenv('DB_USER')  ?: 'workeddy',
                'password' => getenv('DB_PASS')  ?: 'workeddy',
                'host'     => getenv('DB_HOST')  ?: '127.0.0.1',
                'port'     => (int) (getenv('DB_PORT') ?: 3306),
                'driver'   => 'pdo_mysql',
                'charset'  => 'utf8mb4',
            ]);
        }

        return self::$instance;
    }
}
