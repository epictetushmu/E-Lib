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
    private static $instance = null;
    private $database;
    private $databaseName;

    /**
     * Private constructor to enforce Singleton pattern
     *
     * @param string|null $dbName Optional database name, defaults to value from environment
     */
    private function __construct($dbName = null)
    {
        $this->databaseName = $dbName ? $dbName : Environment::get('DB_NAME', 'LibraryDb');

        $this->database = new MongoDatabase();
    }

    /**
     * Get the singleton instance of DatabaseRepository
     *
     * @param string|null $dbName Optional database name
     * @return DatabaseRepository
     */
    public static function getInstance($dbName = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($dbName);
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

    /**
     * Insert a new document into a collection
     *
     * @param string $collection The collection name
     * @param array $data The data to insert
     * @return array The operation result
     */
    public function insert(string $collection, array $data): array
    {
        return $this->database->insert($collection, $data);
    }

    /**
     * Find documents in a collection
     *
     * @param string $collection The collection name
     * @param array $filter The filter criteria
     * @return array The matching documents
     */
    public function find(string $collection, array $filter = []): array
    {
        return $this->database->find($collection, $filter);
    }

    /**
     * Find a single document in a collection
     *
     * @param string $collection The collection name
     * @param array $filter The filter criteria
     * @return array|null The matching document or null
     */
    public function findOne(string $collection, array $filter = [])
    {
        return $this->database->findOne($collection, $filter);
    }

    /**
     * Update documents in a collection
     *
     * @param string $collection The collection name
     * @param array $filter The filter criteria
     * @param array $update The update operations
     * @return array The operation result
     */
    public function update(string $collection, array $filter, array $update): array
    {
        return $this->database->update($collection, $filter, $update);
    }

    /**
     * Delete documents from a collection
     *
     * @param string $collection The collection name
     * @param array $filter The filter criteria
     * @return array The operation result
     */
    public function delete(string $collection, array $filter): array
    {
        return $this->database->delete($collection, $filter);
    }

    /**
     * Perform an aggregation pipeline on a collection
     *
     * @param string $collection The collection name
     * @param array $pipeline The aggregation pipeline
     * @return array The aggregation results
     */
    public function aggregate(string $collection, array $pipeline): array
    {
        return $this->database->aggregate($collection, $pipeline);
    }

    /**
     * Get featured books using the aggregation pipeline
     *
     * @param string $collection The collection name
     * @param array $pipeline The aggregation pipeline
     * @return array The featured books
     */
    public function getFeatured(string $collection, array $pipeline): array
    {
        return $this->database->aggregate($collection, $pipeline);
    }

    /**
     * Get the type of database being used
     *
     * @return string The database implementation class name
     */
    public function getDatabaseType(): string
    {
        return get_class($this->database);
    }
}
