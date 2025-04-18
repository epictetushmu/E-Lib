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
       
        $mongoPassword = getenv('MONGO_PASSWORD');
        if ($mongoPassword && strpos($connectionString, '<db_password>') !== false) {
            $connectionString = str_replace('<db_password>', $mongoPassword, $connectionString);
        }
        
        // Simplified certificate handling - using just one certificate file
        if (extension_loaded('openssl')) {
            $certFile = getenv('MONGO_CERT_FILE');
            
            if ($certFile && file_exists($certFile)) {
                // Configure TLS with the single certificate file
                $options['mongoOptions']['tls'] = true;
                $options['mongoOptions']['tlsCAFile'] = $certFile;
                error_log("MongoDB SSL/TLS configured with certificate: $certFile");
            } else {
                // No certificate file found, but we have SSL support
                error_log("No MongoDB certificate file found at: " . ($certFile ?? 'Not set'));
            }
        } else {
            error_log("Warning: OpenSSL extension not loaded. SSL/TLS connections will not work properly.");
        }

        // Create client if it doesn't exist
        if (self::$mongoClient === null) {
            try {
                $apiVersion = new ServerApi(ServerApi::V1);
                self::$mongoClient = new Client($connectionString, $options['mongoOptions'] ?? [], ['serverApi' => $apiVersion]);
           
                error_log("MongoDB client initialized with secure connection");
           
            } catch (\Exception $e) {
                error_log("MongoDB connection error: " . $e->getMessage());
                throw $e;
            }

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
