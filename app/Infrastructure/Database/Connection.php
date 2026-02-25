<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $cfg = require dirname(__DIR__, 3) . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        $options = $cfg['options'];
        $options[PDO::ATTR_PERSISTENT] = (bool) $cfg['persistent'];

        self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
        self::$pdo->exec("SET NAMES {$cfg['charset']} COLLATE {$cfg['collation']}");

        return self::$pdo;
    }
}
