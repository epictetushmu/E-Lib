<?php
namespace App\Database;

use App\Integration\Database\JsonDbInteraction;
use Exception;

class JsonDatabase extends JsonDbInteraction implements DatabaseInterface {
    /**
     * @var string Storage directory path
     */
    protected $storageDir;
    
    /**
     * Constructor
     * 
     * @param string|null $storageDir Optional custom storage directory
     */
    public function __construct($storageDir = null)
    {
        $this->storageDir = self::initialize($storageDir);    
    }
    
    /**
     * Insert a document into a collection
     * 
     * @param string $collection Collection name
     * @param array $data Document data
     * @return array Result with insertedId
     */
    public function insert(string $collection, array $data): array
    {
        try {
            // Generate a unique ID if not provided
            if (!isset($data['_id'])) {
                $data['_id'] = uniqid('', true);
            }
            
            // Load existing collection data
            $collectionData = self::loadCollection($collection);
            
            // Add the new document
            $collectionData[] = $data;
            
            // Save the updated collection
            if (self::saveCollection($collection, $collectionData)) {
                return ['insertedId' => $data['_id']];
            } else {
                return ['error' => 'Failed to save collection data'];
            }
        } catch (Exception $e) {
            echo("JsonDatabase Insert Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Find documents in a collection
     * 
     * @param string $collection Collection name
     * @param array $filter Filter criteria
     * @return array Found documents
     */
    public function find(string $collection, array $filter = []): array
    {
        try {
            // Load collection data
            $collectionData = self::loadCollection($collection);
            
            // Filter documents
            if (empty($filter)) {
                return $collectionData;
            }
            
            // Apply filters
            $results = array_filter($collectionData, function($document) use ($filter) {
                return $this->matchesFilter($document, $filter);
            });
            
            return array_values($results); // Reset array keys
        } catch (Exception $e) {
            echo("JsonDatabase Find Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find a single document in a collection
     * 
     * @param string $collection Collection name
     * @param array $filter Filter criteria
     * @return array|null Found document or null
     */
    public function findOne(string $collection, array $filter = [])
    {
        try {
            // Load collection data
            $collectionData = self::loadCollection($collection);
            
            // Find first matching document
            foreach ($collectionData as $document) {
                if ($this->matchesFilter($document, $filter)) {
                    return $document;
                }
            }
            
            return null;
        } catch (Exception $e) {
            echo("JsonDatabase FindOne Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update documents in a collection
     * 
     * @param string $collection Collection name
     * @param array $filter Filter criteria
     * @param array $update Update data
     * @return array Result with modifiedCount
     */
    public function update(string $collection, array $filter, array $update): array
    {
        try {
            // Load collection data
            $collectionData = self::loadCollection($collection);
            
            // Track modified count
            $modifiedCount = 0;
            
            // Update matching documents
            foreach ($collectionData as &$document) {
                if ($this->matchesFilter($document, $filter)) {
                    // Apply updates
                    foreach ($update as $key => $value) {
                        $document[$key] = $value;
                    }
                    $modifiedCount++;
                }
            }
            
            // Save the updated collection if any documents were modified
            if ($modifiedCount > 0) {
                self::saveCollection($collection, $collectionData);
            }
            
            return ['modifiedCount' => $modifiedCount];
        } catch (Exception $e) {
            echo("JsonDatabase Update Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete documents from a collection
     * 
     * @param string $collection Collection name
     * @param array $filter Filter criteria
     * @return array Result with deletedCount
     */
    public function delete(string $collection, array $filter): array
    {
        try {
            // Load collection data
            $collectionData = self::loadCollection($collection);
            
            // Keep track of original count to calculate deleted count
            $originalCount = count($collectionData);
            
            // Filter out documents that match the criteria
            $newCollectionData = [];
            foreach ($collectionData as $document) {
                if (!$this->matchesFilter($document, $filter)) {
                    $newCollectionData[] = $document;
                }
            }
            
            // Calculate deleted count
            $deletedCount = $originalCount - count($newCollectionData);
            
            // Save the updated collection if any documents were deleted
            if ($deletedCount > 0) {
                self::saveCollection($collection, $newCollectionData);
            }
            
            return ['deletedCount' => $deletedCount];
        } catch (Exception $e) {
            echo("JsonDatabase Delete Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Check if a document matches a filter
     * 
     * @param array $document Document to check
     * @param array $filter Filter criteria
     * @return bool True if document matches filter
     */
    private function matchesFilter(array $document, array $filter)
    {
        if (empty($filter)) {
            return true;
        }
        
        foreach ($filter as $key => $value) {
            // Handle nested paths with dot notation
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $current = $document;
                
                // Navigate to the nested value
                foreach ($parts as $part) {
                    if (!isset($current[$part])) {
                        return false;
                    }
                    $current = $current[$part];
                }
                
                if ($current !== $value) {
                    return false;
                }
            } else {
                // Simple direct property comparison
                if (!isset($document[$key]) || $document[$key] !== $value) {
                    return false;
                }
            }
        }
        
        return true;
    }

    
    public function aggregate(string $collection, array $pipeline): array
    {
        // Not implemented for JSON database
        return [];
    }
}
