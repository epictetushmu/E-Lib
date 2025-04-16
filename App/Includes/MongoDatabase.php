<?php
namespace App\Includes;

use MongoDB\Client;
use MongoDB\Driver\ServerApi;
use Exception;

class MongoDatabase implements DatabaseInterface {
    protected $client;
    protected $db;
    
    /**
     * Constructor 
     *
     * @param string $dbName Database name
     * @param array $options Connection options
     */
    public function __construct($dbName, $options = [])
    {
        // Set default options if not provided
        $defaultOptions = [
            'serverSelectionTimeoutMS' => 30000, // 30 seconds default
            'connectTimeoutMS' => 30000
        ];
        
        // Merge with user-provided options
        $options = array_merge($defaultOptions, $options);
        
        // Get MongoDB connection string from environment variables or use default
        $connectionString = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
        
        $this->client = new \MongoDB\Client($connectionString, $options);
        $this->db = $this->client->selectDatabase($dbName);
        
        // Test the connection by executing a simple command - this will throw an exception if it fails
        $this->db->command(['ping' => 1]);
    }

    public function ping(){
        try {
            $respo = $this->client->selectDatabase('admin')->command(['ping' => 1]);
            return $respo->getServer()->getHost();
        } catch (Exception $e) {
            error_log("MongoDB Ping Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function insert(string $collection, array $data): array {
        try {
            $result = $this->db->selectCollection($collection)->insertOne($data);
            return ['insertedId' => (string)$result->getInsertedId()];
        } catch (Exception $e) {
            error_log("MongoDB Insert Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function find(string $collection, array $filter = []): array {
        try {
            $cursor = $this->db->selectCollection($collection)->find($filter);
            return $cursor->toArray();
        } catch (Exception $e) {
            error_log("MongoDB Find Error: " . $e->getMessage());
            return [];
        }
    }

    public function findOne(string $collection, array $filter = []) {
        try {
            $document = $this->db->selectCollection($collection)->findOne($filter);
            return $document ? (array)$document : null;
        } catch (Exception $e) {
            error_log("MongoDB FindOne Error: " . $e->getMessage());
            return null;
        }
    }

    public function update(string $collection, array $filter, array $update): array {
        try {
            $result = $this->db->selectCollection($collection)->updateMany(
                $filter,
                ['$set' => $update]
            );
            return ['modifiedCount' => $result->getModifiedCount()];
        } catch (Exception $e) {
            error_log("MongoDB Update Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function delete(string $collection, array $filter): array {
        try {
            $result = $this->db->selectCollection($collection)->deleteMany($filter);
            return ['deletedCount' => $result->getDeletedCount()];
        } catch (Exception $e) {
            error_log("MongoDB Delete Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}