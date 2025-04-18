<?php

namespace App\Integration\Database;

use App\Database\JsonDatabase;
use Exception;

/**
 * JSON Database Interaction Manager
 * 
 * Controls input/output and connections to JSON data files (secondary database)
 */
class JsonDbInteraction{
    /** @var JsonDatabase */
    private static $jsonDb = null;
    
    /** @var string Default storage path */
    private static $storagePath = null;
    
    /**
     * Initialize the JSON database system
     *
     * @param string|null $storagePath Custom storage path
     * @param bool $createIfNotExists Create directory if it doesn't exist
     * @return JsonDatabase
     */
    public static function initialize($storagePath = null, $createIfNotExists = true)
    {
        if (self::$jsonDb === null) {
            // Determine storage path
            if ($storagePath === null) {
                $storagePath = self::getDefaultStoragePath();
            }
            
            // Create directory if needed
            if ($createIfNotExists && !is_dir($storagePath)) {
                if (!mkdir($storagePath, 0755, true)) {
                    throw new Exception("Failed to create JSON database directory: $storagePath");
                }
            }
            
            self::$storagePath = $storagePath;
        }
        
    }
    
    /**
     * Get the default storage path based on environment
     *
     * @return string
     */
    public static function getDefaultStoragePath()
    {
        // Try environment variable first
        $path = getenv('JSON_DB_PATH');
        if ($path) {
            return $path;
        }
        
        // Use a consistent path regardless of environment
        return dirname(__DIR__, 2) . '/Storage/json_db';
    }
    
    /**
     * Get the JsonDatabase instance (initializes if needed)
     *
     * @return JsonDatabase
     */
    public static function getDatabase()
    {
        if (self::$jsonDb === null) {
            self::initialize();
        }
        return self::$jsonDb;
    }
    
    /**
     * Check if the JSON database storage is accessible and working
     *
     * @return bool|string True if working, error message if not
     */
    public static function checkStatus()
    {
        try {
            $db = self::getDatabase();
            
            // Try a simple write/read operation
            $testCollection = '_system_test_' . uniqid();
            $testData = ['test' => true, 'timestamp' => time()];
            
            // Insert test data
            $result = $db->insert($testCollection, $testData);
            if (!isset($result['insertedId'])) {
                return "Write test failed: " . ($result['error'] ?? 'Unknown error');
            }
            
            // Read test data
            $readResult = $db->findOne($testCollection, ['test' => true]);
            if (!$readResult) {
                return "Read test failed: Could not retrieve test document";
            }
            
            // Clean up test collection
            $db->delete($testCollection, []);
            
            return true;
        } catch (Exception $e) {
            return "JSON database error: " . $e->getMessage();
        }
    }
    
    /**
     * Get list of all collections
     *
     * @return array Collection names
     */
    public static function getCollections()
    {
        $storageDir = self::$storagePath ?: self::getDefaultStoragePath();
        $collections = [];
        
        foreach (glob($storageDir . '/*.json') as $file) {
            $collections[] = basename($file, '.json');
        }
        
        return $collections;
    }
    
    /**
     * Get a collection file path
     *
     * @param string $collection Collection name
     * @return string Full path to collection file
     */
    public static function getCollectionPath($collection)
    {
        // Ensure valid collection name (prevent path traversal)
        if (preg_match('/[^a-zA-Z0-9_-]/', $collection)) {
            throw new Exception("Invalid collection name: $collection");
        }
        
        $path = self::$storagePath ?: self::getDefaultStoragePath();
        return $path . '/' . $collection . '.json';
    }
    
    /**
     * Load collection data from file
     *
     * @param string $collection Collection name
     * @return array Collection data
     */
    public static function loadCollection($collection)
    {
        $path = self::getCollectionPath($collection);
        
        if (!file_exists($path)) {
            return [];
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("Failed to read collection file: $path");
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in collection file: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Save collection data to file
     *
     * @param string $collection Collection name
     * @param array $data Collection data
     * @return bool Success
     */
    public static function saveCollection($collection, array $data)
    {
        $path = self::getCollectionPath($collection);
        
        $content = json_encode($data, JSON_PRETTY_PRINT);
        if ($content === false) {
            throw new Exception("Failed to encode collection data: " . json_last_error_msg());
        }
        
        $result = file_put_contents($path, $content, LOCK_EX);
        return $result !== false;
    }
}

