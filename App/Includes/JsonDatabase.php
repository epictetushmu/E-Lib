<?php
namespace App\Includes;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Exception\Exception;

class JsonDatabase implements DatabaseInterface {
    private $dataPath;
    
    public function __construct() {
        $this->dataPath = __DIR__ . '/../../data/';
        
        // Create data directory if it doesn't exist
        if (!file_exists($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
class MongoDb {
    private static $instance = null;
    private $connection;
    private $db;

    private function __construct() {
        // Use Environment class to get configuration
        // Make sure Environment is loaded before this class is instantiated
        
        // Use environment variables to configure the MongoDB connection
        $host = Environment::get('MONGODB_HOST');
        $port = Environment::get('MONGODB_PORT');
        $username = Environment::get('MONGODB_USERNAME');
        $password = Environment::get('MONGODB_PASSWORD');
        $dbname = Environment::get('MONGODB_DATABASE');
        $authSource = Environment::get('MONGODB_AUTH_SOURCE', 'admin');

        try {
            // Create a MongoDB client with authentication if credentials are provided
            $connectionString = "mongodb://";
            if ($username && $password) {
                $connectionString .= "$username:$password@";
            }
            $connectionString .= "$host:$port/?authSource=$authSource";
            
            $this->connection = new Client($connectionString);
            $this->db = $this->connection->selectDatabase($dbname);
        } catch (\Exception $e) {
            echo 'Error connecting to MongoDB: ' . $e->getMessage();
            exit;
        }
    }
    
    private function getFilePath(string $collection): string {
        return $this->dataPath . $collection . '.json';
    }

    private function readCollection(string $collection): array {
        $filePath = $this->getFilePath($collection);
        if (!file_exists($filePath)) {
            file_put_contents($filePath, json_encode([]));
            return [];
        }
        $json = file_get_contents($filePath);
        return json_decode($json, true) ?? [];
    }

    private function writeCollection(string $collection, array $data): bool {
        $filePath = $this->getFilePath($collection);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents($filePath, $json) !== false;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getCollection(string $collectionName): Collection {
        return $this->db->selectCollection($collectionName);
    }

    public function insert(string $collection, array $data): array {
        $documents = $this->readCollection($collection);
        $data['_id'] = (string)new \MongoDB\BSON\ObjectId();
        $documents[] = $data;
        if ($this->writeCollection($collection, $documents)) {
            return ['insertedId' => $data['_id']];
        }
        return ['error' => 'Failed to insert document'];
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
