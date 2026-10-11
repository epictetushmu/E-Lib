<?php

namespace App\Database;

use MongoDB\Database;
use App\Integration\Database\MongoConnectionFactory;
use Exception;

class MongoDatabase extends MongoConnectionFactory implements DatabaseInterface
{
    /**
     * @var Database
     */
    protected $db;

    /**
     * Constructor
     *
     * Connects to the 'LibraryDb' database via MongoConnectionFactory.
     */
    public function __construct()
    {
        $this->db = self::create('mongo', ['dbName' => 'LibraryDb']);
    }

    public function insert(string $collection, array $data): array
    {
        try {
            $result = $this->db->selectCollection($collection)->insertOne($data);
            return ['insertedId' => (string)$result->getInsertedId()];
        } catch (Exception $e) {
            error_log("MongoDB Insert Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function insertMany(string $collection, array $documents): int
    {
        if ($documents === []) {
            return 0;
        }
        return $this->db->selectCollection($collection)->insertMany($documents)->getInsertedCount();
    }

    public function findOneAndUpdate(string $collection, array $filter, array $update, array $options = []): ?array
    {
        $document = $this->db->selectCollection($collection)->findOneAndUpdate($filter, $update, $options + [
            'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]);
        return is_array($document) ? $document : null;
    }

    public function find(string $collection, array $filter = [], array $options = []): array
    {
        try {
            // Default options for pagination
            $defaultOptions = [
                'limit' => 100  // Limit results to prevent memory issues
            ];

            // Merge with user provided options
            $options = array_merge($defaultOptions, $options);

            $cursor = $this->db->selectCollection($collection)->find($filter, $options);

            // Process results in batches to avoid memory issues
            $results = [];
            foreach ($cursor as $document) {
                $results[] = (array)$document;
            }

            return $results;
        } catch (Exception $e) {
            error_log("MongoDB Find Error: " . $e->getMessage());
            return [];
        }
    }

    public function findOne(string $collection, array $filter = [])
    {
        try {
            $document = $this->db->selectCollection($collection)->findOne($filter);
            return $document ? (array)$document : null;
        } catch (Exception $e) {
            error_log('MongoDB findOne [' . $collection . ']: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $collection, array $filter, array $update): array
    {
        try {
            $result = $this->db->selectCollection($collection)->updateMany(
                $filter,
                $update
            );
            return ['modifiedCount' => $result->getModifiedCount()];
        } catch (Exception $e) {
            error_log("MongoDB Update Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function delete(string $collection, array $filter): array
    {
        try {
            $result = $this->db->selectCollection($collection)->deleteMany($filter);
            return ['deletedCount' => $result->getDeletedCount()];
        } catch (Exception $e) {
            error_log("MongoDB Delete Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * New method to iterate through large result sets efficiently
     * Returns a generator that yields documents one at a time
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @return iterable<int, array<string, mixed>>
     */
    public function findIterate(string $collection, array $filter = [], array $options = []): iterable
    {
        try {
            $cursor = $this->db->selectCollection($collection)->find($filter, $options);

            foreach ($cursor as $document) {
                yield (array)$document;
            }
        } catch (Exception $e) {
            error_log("MongoDB Find Error: " . $e->getMessage());
            return;
        }
    }

    public function aggregate(string $collection, array $pipeline): array
    {
        try {
            $cursor = $this->db->selectCollection($collection)->aggregate($pipeline, [
                'typeMap' => ['root' => 'array', 'document' => 'array']
            ]);

            // Immediately convert cursor to array before any other operations
            $result = [];
            foreach ($cursor as $document) {
                $result[] = $document;
            }
            return $result;
        } catch (Exception $e) {
            // Rethrown, not swallowed: content search must tell "no matches" from "the search failed".
            error_log("MongoDB Aggregate Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function distinct(string $collection, string $field, array $filter = []): array
    {
        try {
            return $this->db->selectCollection($collection)->distinct($field, $filter);
        } catch (Exception $e) {
            error_log("MongoDB Distinct Error: " . $e->getMessage());
            return [];
        }
    }
}
