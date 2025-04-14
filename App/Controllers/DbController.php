<?php
namespace App\Controllers;

use Exception;
use App\Includes\JsonDatabase;
use App\Includes\MongoDatabase;

class DbController {
    private static $instance = null;
    private $database;
    private $databaseName = 'e_library';

    private function __construct() {
        try {
            $this->database = new MongoDatabase($this->databaseName);
        } catch (Exception $e) {
            error_log("MongoDB Connection Error: " . $e->getMessage());
            // Create a fallback to JSON files if MongoDB connection fails
            $this->database = new JsonDatabase();
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function insert(string $collection, array $data): array {
        return $this->database->insert($collection, $data);
    }

    public function find(string $collection, array $filter = []): array {
        return $this->database->find($collection, $filter);
    }

    public function findOne(string $collection, array $filter = []) {
        return $this->database->findOne($collection, $filter);
    }

    public function update(string $collection, array $filter, array $update): array {
        return $this->database->update($collection, $filter, $update);
    }

    public function delete(string $collection, array $filter): array {
        return $this->database->delete($collection, $filter);
    }
}