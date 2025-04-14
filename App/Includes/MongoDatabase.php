<?php
namespace App\Includes;

use Dotenv\Dotenv;
use MongoDB\Client;
use MongoDB\Driver\ServerApi;
use Exception;

class MongoDatabase implements DatabaseInterface {
    private $client;
    private $database;
    
    public function __construct(string $databaseName) {
        // Configure the connection string
        $uri = Dotenv::createImmutable(__DIR__ . '/../..');
        $uri->load();
        $uri = $_ENV['MONGODB_URI'];
        $uri = str_replace('<db_password>', $_ENV['MONGO_PASSWORD'], $uri);
       
        // Create server API options
        $serverApi = new ServerApi(ServerApi::V1);
        $options = [
            'serverApi' => $serverApi,
        ];
        
        // Create the client
        $this->client = new Client($uri, $options);
        
        // Select the database
        $this->database = $this->client->selectDatabase($databaseName);
        
        // Test connection
        $this->client->selectDatabase('admin')->command(['ping' => 1]);
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