<?php

namespace App\Integration\Database;

use App\Database\JsonDatabase;
use MongoDB\Driver\ServerApi;
use MongoDB\Client;

class MongoConnectionFactory{
    
    private static $mongoClient = null;

    /**
     * Create a database connection with fallback options
     * 
     * @param string $type The type of database to connect to (mongo, json)
     * @param array $options Connection options
     * @return mixed connection with mongo object or json object
     */
    public static function create($type = 'mongo', $options = [])
    {
        // Set default options
        $defaults = [
            'dbName' => 'LibraryDb',
            'mongoOptions' => [
                'serverSelectionTimeoutMS' => 5000, // 5 seconds timeout
                'connectTimeoutMS' => 5000
            ]
        ];
        
        $config = array_merge($defaults, $options);
        
        if ($type === 'mongo') {
            try {
                // Get MongoDB connection

                $mongoDb = self::getMongoConnection($config['dbName'], $config['mongoOptions']);
                
                // Create and return the MongoDB wrapper
                error_log("Connected to MongoDB successfully");
                return $mongoDb;
            } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
                error_log("MongoDB connection failed: " . $e->getMessage());
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    error_log("Falling back to JsonDatabase");
                    return new JsonDatabase();
                }
                
                // Re-throw if no fallback requested
                throw $e;
            } catch (\Exception $e) {
                error_log("Database error: " . $e->getMessage());
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    error_log("Falling back to JsonDatabase due to error");
                    return new JsonDatabase();
                }
                
                // Re-throw if no fallback requested
                throw $e;
            }
        } elseif ($type === 'json') {
            return new JsonDatabase();
        }
        
        throw new \InvalidArgumentException("Unsupported database type: $type");
    }

    /**
     * Get a MongoDB database connection
     * 
     * @param string $dbName Database name
     * @param array $options Connection options
     * @return \MongoDB\Database
     */
    private static function getMongoConnection($dbName, $options = [])
    {
        // Get MongoDB connection string from environment variables or use default
        $connectionString = getenv('MONGO_URI') ?: 'mongodb://localhost:27017';
        
        // Create client if it doesn't exist
        if (self::$mongoClient === null) {
            $apiVersion = new ServerApi(ServerApi::V1);


            self::$mongoClient = new Client($connectionString, [] , ['serverApi' => $apiVersion]);
        }
        
        // Get the database and verify connection by running a ping command
        $db = self::$mongoClient->selectDatabase($dbName);
        $db->command(['ping' => 1]);
        
        return $db;
    }

    /**
     * Get the MongoDB client instance
     * 
     * @return \MongoDB\Client
     */
    public static function getClient()
    {
        if (self::$mongoClient === null) {
            throw new \RuntimeException("MongoDB client not initialized");
        }
        return self::$mongoClient;
    }
}
