<?php

namespace App\Integration\Database;

use App\Database\DatabaseInterface;
use MongoDB\Client;

class DatabaseConnectionFactory
{
    private static $mongoClient = null;

    /**
     * Create a database connection with fallback options
     * 
     * @param string $type The type of database to connect to (mongo, json)
     * @param array $options Connection options
     * @return DatabaseInterface
     */
    public static function create($type = 'mongo', $options = [])
    {
        if ($type === 'mongo') {
            // Use MongoConnectionFactory for MongoDB connections
            return MongoConnectionFactory::create($type, $options);
        } elseif ($type === 'json') {
            // Initialize JSON database
            return JsonDbInteraction::initialize($options['storagePath'] ?? null);
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
        $connectionString = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
        
        // Create client if it doesn't exist
        if (self::$mongoClient === null) {
            self::$mongoClient = new Client($connectionString, $options);
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
