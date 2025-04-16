<?php

namespace App\Integration\Database;

use App\Includes\DatabaseInterface;
use App\Includes\JsonDatabase;
use App\Includes\MongoDatabase;

class DatabaseConnectionFactory
{
    /**
     * Create a database connection with fallback options
     * 
     * @param string $type The type of database to connect to (mongo, json)
     * @param array $options Connection options
     * @return DatabaseInterface
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
                $db = new MongoDatabase($config['dbName'], $config['mongoOptions']);
                error_log("Connected to MongoDB successfully");
                return $db;
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
}
