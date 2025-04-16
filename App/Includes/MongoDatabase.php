<?php
namespace App\Includes;

use MongoDB\Client;
use MongoDB\Driver\ServerApi;
use Exception;

class MongoDatabase implements DatabaseInterface {
    private $client;
    private $database;
    
    public function __construct(string $databaseName) {
        try {
            // Get connection string using Environment class
            $uri = Environment::get('MONGODB_URI', 'mongodb://localhost:27017');
            
            // Replace password placeholder if needed
            $password = Environment::get('MONGO_PASSWORD');
            if ($password) {
                $uri = str_replace('<db_password>', $password, $uri);
            }
            
            // Create server API options
            $serverApi = new ServerApi(ServerApi::V1);
            $options = [
                'serverApi' => $serverApi,
            ];
            
            $this->client = new Client($uri, $options);
            
            $this->database = $this->client->selectDatabase($databaseName);
            
            $this->client->selectDatabase('admin')->command(['ping' => 1]);
        } catch (Exception $e) {
            error_log("MongoDB Connection Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function insert(string $collection, array $data): array {
        try {
            $result = $this->database->selectCollection($collection)->insertOne($data);
            return ['insertedId' => (string)$result->getInsertedId()];
        } catch (Exception $e) {
            error_log("MongoDB Insert Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function find(string $collection, array $filter = []): array {
        try {
            $cursor = $this->database->selectCollection($collection)->find($filter);
            return $cursor->toArray();
        } catch (Exception $e) {
            error_log("MongoDB Find Error: " . $e->getMessage());
            return [];
        }
    }

    public function findOne(string $collection, array $filter = []) {
        try {
            $document = $this->database->selectCollection($collection)->findOne($filter);
            return $document ? (array)$document : null;
        } catch (Exception $e) {
            error_log("MongoDB FindOne Error: " . $e->getMessage());
            return null;
        }
    }

    public function update(string $collection, array $filter, array $update): array {
        try {
            $result = $this->database->selectCollection($collection)->updateMany(
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
            $result = $this->database->selectCollection($collection)->deleteMany($filter);
            return ['deletedCount' => $result->getDeletedCount()];
        } catch (Exception $e) {
            error_log("MongoDB Delete Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}