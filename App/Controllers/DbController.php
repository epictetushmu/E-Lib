<?php
namespace App\Controllers;

use App\Includes\Environment;
use Exception;
use App\Database\JsonDatabase;
use App\Database\MongoDatabase;

class DbController {
    private static $instance = null;
    private $database;
    private $databaseName;

    private function __construct($dbName = null) { 
    
        $this->databaseName = $dbName ? $dbName : Environment::get('DB_NAME', 'LibraryDb');   
        try {
            $this->database = new MongoDatabase();
        } catch (Exception $e) {
            echo("MongoDB Connection Error: " . $e->getMessage());
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

    public function getFeatured(string $collection, $pipeline): array {
        return $this->database->aggregate($collection, $pipeline);
    }
}