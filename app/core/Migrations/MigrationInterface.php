<?php

declare(strict_types=1);

namespace WorkEddy\Core\Migrations;

use Doctrine\DBAL\Connection;

interface MigrationInterface
{
    public function version(): string;

    public function up(Connection $db): void;

    public function down(Connection $db): void;
}
