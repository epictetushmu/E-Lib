<?php

namespace App\Integration\Database;

use App\Database\JsonDatabase;
use App\Includes\Environment;
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
            'dbName' => 'LibraryDb'
        ];
        
        $config = array_merge($defaults, $options);
        
        if ($type === 'mongo') {
            try {
                // Get MongoDB connection
                $mongoDb = self::getMongoConnection($config['dbName'], []);
                return $mongoDb;
            } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
                echo("MongoDB connection failed: " . $e->getMessage());
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    echo("Falling back to JsonDatabase");
                    return new JsonDatabase();
                }
                
                // Re-throw if no fallback requested
                throw $e;
            } catch (\Exception $e) {
                echo("Database error: " . $e->getMessage());
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    echo("Falling back to JsonDatabase due to error");
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
        $connectionString = Environment::get('MONGO_URI', getenv('MONGO_URI'));
       
        $mongoPassword = Environment::get('MONGO_PASSWORD', getenv('MONGO_PASSWORD'));
        if ($mongoPassword && strpos($connectionString, '<db_password>') !== false) {
            $connectionString = str_replace('<db_password>', $mongoPassword, $connectionString);
        }
        
        // TLS configuration for MongoDB Atlas
        if (extension_loaded('openssl')) {
            // Default directory for certificates
            $certDir = __DIR__ . '/../../../certificates';
            if (!is_dir($certDir)) {
                mkdir($certDir, 0755, true);
            }
            
            $certFile = getenv('MONGO_CERT_FILE') ?: $certDir . '/mongodb-ca.pem';
            
            // If certificate doesn't exist, try to download it
            if (!file_exists($certFile)) {
                self::downloadMongoCertificate($certFile);
            }
            
            // Configure TLS options for MongoDB Atlas
            if (file_exists($certFile)) {
                // MongoDB Atlas requires these specific TLS options
                $options['mongoOptions']['tls'] = true;
                $options['mongoOptions']['tlsCAFile'] = $certFile;
                $options['mongoOptions']['tlsAllowInvalidHostnames'] = false;
                $options['mongoOptions']['tlsAllowInvalidCertificates'] = false;
            } else {
                // Try with system CA bundle if specific cert not found
                $options['mongoOptions']['tls'] = true;
                echo("Using system CA bundle for MongoDB TLS connection");
            }
        } else {
            echo("Warning: OpenSSL extension not loaded. SSL/TLS connections will not work properly.");
        }

        // Create client if it doesn't exist
        if (self::$mongoClient === null) {
            try {
                $apiVersion = new ServerApi(ServerApi::V1);
                
           
                self::$mongoClient = new Client($connectionString, ["authSource" => 'admin'], ['serverApi' => $apiVersion]);
          
                // Get the database and verify connection by running a ping command
                $db = self::$mongoClient->selectDatabase($dbName);
                $db->command(['ping' => 1]);
                return $db;
            } catch (\Exception $e) {
                echo("MongoDB connection error: " . $e->getMessage());
                throw $e;
            }
        }
        // If client already exists, just return the database
        return self::$mongoClient->selectDatabase($dbName);
    }        
    
    /**
     * Download MongoDB CA certificate
     * 
     * @param string $savePath Path where to save the certificate
     * @return bool True if successful, false otherwise
     */
    private static function downloadMongoCertificate($savePath)
    {
        try {
            $certUrl = 'https://truststore.pki.mongodb.com/atlas-root-ca.pem';
            $certContent = @file_get_contents($certUrl);
            
            if ($certContent === false) {
                echo("Failed to download MongoDB certificate from {$certUrl}");
                return false;
            }
            
            if (file_put_contents($savePath, $certContent) === false) {
                echo("Failed to save MongoDB certificate to {$savePath}");
                return false;
            }
            
            echo("Successfully downloaded MongoDB certificate to {$savePath}");
            return true;
        } catch (\Exception $e) {
            echo("Error downloading certificate: " . $e->getMessage());
            return false;
        }
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
