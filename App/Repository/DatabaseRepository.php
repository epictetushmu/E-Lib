<?php

namespace App\Repository;

use App\Includes\Environment;
use App\Database\DatabaseInterface;
use App\Database\MongoDatabase;

/**
 * DatabaseRepository handles database connections and provides a unified interface
 * for database operations. It implements the Singleton pattern for database connection management.
 */
class DatabaseRepository
{
    private static ?self $instance = null;
    private DatabaseInterface $database;

    /**
     * Private constructor to enforce Singleton pattern
     */
    private function __construct()
    {
        $this->database = new MongoDatabase();
    }

    /**
     * Get the singleton instance of DatabaseRepository
     *
     * @param string|null $dbName Optional database name (currently unused: MongoDatabase connects
     *   to a hardcoded database name; kept for API compatibility with existing callers).
     * @return DatabaseRepository
     */
    public static function getInstance($dbName = null)
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Active MongoDB driver. Models must use this so they share the repository connection.
     */
    public function getDatabaseConnection(): DatabaseInterface
    {
        return $this->database;
    }
}
