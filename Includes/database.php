<?php
namespace E-Lib\Includes;

use Dotenv\Dotenv;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Exception\Exception;

class Database {
    private static $instance = null;
    private $connection;
    private $db;

    private function __construct() {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Use environment variables to configure the MongoDB connection
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $dbname = $_ENV['DB_DATABASE'];

        try {
            // Create a MongoDB client
            $this->connection = new Client("mongodb://$host:$port");
            $this->db = $this->connection->selectDatabase($dbname);
        } catch (\Exception $e) {
            echo 'Error connecting to MongoDB: ' . $e->getMessage();
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getCollection(string $collectionName): Collection {
        return $this->db->selectCollection($collectionName);
    }

    public function insert(string $collection, array $data) {
        return $this->getCollection($collection)->insertOne($data);
    }

    public function find(string $collection, array $filter = [], array $options = []) {
        return $this->getCollection($collection)->find($filter, $options)->toArray();
    }

    public function findOne(string $collection, array $filter = [], array $options = []) {
        return $this->getCollection($collection)->findOne($filter, $options);
    }

    public function update(string $collection, array $filter, array $update, array $options = []) {
        return $this->getCollection($collection)->updateMany($filter, ['$set' => $update], $options);
    }

    public function delete(string $collection, array $filter, array $options = []) {
        return $this->getCollection($collection)->deleteMany($filter, $options);
    }
}
