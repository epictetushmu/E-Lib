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
        // Make sure environment variables are loaded
        self::loadEnvironmentVariables();
        
        // Set default options
        $defaults = [
            'dbName' => 'LibraryDb',
            'mongoOptions' => [
                'tls' => true,
                'serverSelectionTimeoutMS' => 10000, // 10 seconds timeout (increased)
                'connectTimeoutMS' => 10000,         // 10 seconds timeout (increased)
                'socketTimeoutMS' => 45000,          // 45 seconds for operations
                'retryWrites' => true,
                'retryReads' => true
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
                error_log("MongoDB connection failed (timeout): " . $e->getMessage());
                self::logConnectionDiagnostics();
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    error_log("Falling back to JsonDatabase");
                    return new JsonDatabase();
                }
                
                // Re-throw if no fallback requested
                throw $e;
            } catch (\Exception $e) {
                error_log("Database error: " . $e->getMessage());
                self::logConnectionDiagnostics();
                
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
     * Log connection diagnostics to help troubleshoot issues
     */
    private static function logConnectionDiagnostics() {
        // Check for basic connectivity
        error_log("Running MongoDB connection diagnostics...");
        
        // Test DNS resolution
        $host = 'e-lib.yskayrn.mongodb.net';
        error_log("Testing DNS resolution for {$host}...");
        $dnsRecords = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($dnsRecords) {
            error_log("DNS resolution successful: " . json_encode($dnsRecords));
        } else {
            error_log("DNS resolution failed for {$host}");
        }
        
        // Test outbound connection on common ports
        $ports = [27017, 443, 80];
        foreach ($ports as $port) {
            error_log("Testing outbound connection to {$host}:{$port}...");
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if ($socket) {
                error_log("Connection to {$host}:{$port} successful");
                fclose($socket);
            } else {
                error_log("Connection to {$host}:{$port} failed: {$errstr} ({$errno})");
            }
        }
        
        // Log environment variables and PHP settings
        error_log("PHP version: " . phpversion());
        error_log("MongoDB extension version: " . (extension_loaded('mongodb') ? phpversion('mongodb') : 'Not loaded'));
        error_log("Stream wrapper SSL support: " . (in_array('ssl', stream_get_transports()) ? 'Yes' : 'No'));
    }

    /**
     * Load environment variables if not already loaded
     */
    private static function loadEnvironmentVariables()
    {
        // Check if Environment class exists and MONGO_URI is not set
        if (class_exists('App\Includes\Environment') && empty(getenv('MONGO_URI'))) {
            try {
                Environment::load();
                error_log("Environment variables loaded from .env file");
            } catch (\Exception $e) {
                error_log("Error loading environment variables: " . $e->getMessage());
            }
        }
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
        // Get MongoDB connection string from environment variables with better fallbacks
        $connectionString = Environment::get('MONGO_URI', getenv('MONGO_URI'));
        
        if (empty($connectionString)) {
            error_log("MONGO_URI environment variable not found, checking for components...");
            
            $mongoUser = Environment::get('MONGO_USER', getenv('MONGO_USER'));
            $mongoPassword = Environment::get('MONGO_PASSWORD', getenv('MONGO_PASSWORD'));
            $mongoHost = Environment::get('MONGO_HOST', getenv('MONGO_HOST')) ?: 'e-lib.yskayrn.mongodb.net';
            
            if ($mongoPassword && $mongoHost) {
                $mongoUser = $mongoUser ?: 'nikitas';
                $connectionString = "mongodb+srv://{$mongoUser}:{$mongoPassword}@{$mongoHost}/?tls=true&retryWrites=true&w=majority&appName=e-lib";
                error_log("Built connection string from components: mongodb+srv://{$mongoUser}:***@{$mongoHost}");
            } else {
                error_log("WARNING: Using localhost MongoDB URI as last resort. This is likely not what you want in production.");
                $connectionString = 'mongodb://localhost:27017';
            }
        }
       
        // Handle password placeholder replacement
        $mongoPassword = Environment::get('MONGO_PASSWORD', getenv('MONGO_PASSWORD'));
        if ($mongoPassword) {
            if (strpos($connectionString, '<db_password>') !== false) {
                $connectionString = str_replace('<db_password>', $mongoPassword, $connectionString);
                error_log("Replaced <db_password> placeholder in connection string");
            } elseif (strpos($connectionString, '<PASSWORD>') !== false) {
                $connectionString = str_replace('<PASSWORD>', $mongoPassword, $connectionString);
                error_log("Replaced <PASSWORD> placeholder in connection string");
            }
        }
        
        $connectionString = preg_replace('#(mongodb(\+srv)?://)(/+)#', '$1', $connectionString);
        
        // Ensure there's no slash between auth info and hostname
        $connectionString = preg_replace('#@/#', '@', $connectionString);
        
        // Check if we need to allow insecure connections for development/testing
        $allowInsecureConnections = Environment::get('MONGO_ALLOW_INSECURE', false);
        
        // Redacted URI for logging
        $redactedUri = preg_replace('/\/\/([^:]+):([^@]+)@/', '//\\1:***@/', $connectionString);
        error_log("Using MongoDB connection string: {$redactedUri}");
        
        // Add critical URI parameters if missing
        if (strpos($connectionString, 'retryWrites=') === false) {
            $connectionString .= (strpos($connectionString, '?') === false ? '?' : '&') . 'retryWrites=true';
        }
        
        if (strpos($connectionString, 'w=') === false) {
            $connectionString .= (strpos($connectionString, '?') === false ? '?' : '&') . 'w=majority';
        }
        
        // TLS configuration for MongoDB Atlas
        if (extension_loaded('openssl')) {
            // Default directory for certificates
            $certDir = __DIR__ . '/../../../certificates';
            if (!is_dir($certDir)) {
                mkdir($certDir, 0755, true);
            }
            
            $certFile = Environment::get('MONGO_CERT_FILE', getenv('MONGO_CERT_FILE')) ?: $certDir . '/mongodb-ca.pem';
            
            // If certificate doesn't exist, try to download it
            if (!file_exists($certFile)) {
                self::downloadMongoCertificate($certFile);
            }
            
            // Configure TLS options for MongoDB Atlas
            if (file_exists($certFile)) {
                // MongoDB Atlas requires these specific TLS options
                $options['mongoOptions']['tls'] = true;
                $options['mongoOptions']['tlsCAFile'] = $certFile;
                
                // Only use strict TLS in production
                if ($allowInsecureConnections) {
                    $options['mongoOptions']['tlsAllowInvalidHostnames'] = true;
                    $options['mongoOptions']['tlsAllowInvalidCertificates'] = true;
                    error_log("WARNING: Using insecure TLS settings for development/testing");
                } else {
                    $options['mongoOptions']['tlsAllowInvalidHostnames'] = false;
                    $options['mongoOptions']['tlsAllowInvalidCertificates'] = false;
                }
                
                error_log("MongoDB SSL/TLS configured with certificate: $certFile");
            } else {
                // Try with system CA bundle if specific cert not found
                $options['mongoOptions']['tls'] = true;
                error_log("Using system CA bundle for MongoDB TLS connection");
            }
        } else {
            error_log("Warning: OpenSSL extension not loaded. SSL/TLS connections will not work properly.");
        }

        // Create client if it doesn't exist
        if (self::$mongoClient === null) {
            try {
                $apiVersion = new ServerApi(ServerApi::V1);
                
                // For debugging: log connection string (remove sensitive info)
                $redactedUri = preg_replace('/\/\/([^:]+):([^@]+)@/', '//\\1:***@/', $connectionString);
                error_log("Connecting to MongoDB with URI: {$redactedUri}");
                error_log("TLS options: " . json_encode($options['mongoOptions'] ?? []));
                
                self::$mongoClient = new Client($connectionString, [], ['serverApi' => $apiVersion]);
                error_log("MongoDB client initialized with secure connection");
            } catch (\Exception $e) {
                error_log("MongoDB connection error: " . $e->getMessage());
                throw $e;
            }
        }
        
        // Get the database and verify connection by running a ping command
        try {
            $db = self::$mongoClient->selectDatabase($dbName);
            $pingResult = $db->command(['ping' => 1], ['maxTimeMS' => 5000]);
            error_log("MongoDB ping successful: " . json_encode($pingResult->toArray()));
            return $db;
        } catch (\Exception $e) {
            error_log("MongoDB ping failed: " . $e->getMessage());
            throw $e;
        }
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
                error_log("Failed to download MongoDB certificate from {$certUrl}");
                return false;
            }
            
            if (file_put_contents($savePath, $certContent) === false) {
                error_log("Failed to save MongoDB certificate to {$savePath}");
                return false;
            }
            
            error_log("Successfully downloaded MongoDB certificate to {$savePath}");
            return true;
        } catch (\Exception $e) {
            error_log("Error downloading certificate: " . $e->getMessage());
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
