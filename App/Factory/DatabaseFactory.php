<?php

namespace App\Factory;

use App\Database\DatabaseInterface;
use App\Repository\DatabaseRepository;

/**
 * Same MongoDB connection as DatabaseRepository (singleton).
 */
class DatabaseFactory
{
    private static ?DatabaseInterface $database = null;

    public static function getDatabase(): DatabaseInterface
    {
        if (self::$database === null) {
            self::$database = DatabaseRepository::getInstance()->getDatabaseConnection();
        }
        return self::$database;
    }
}
