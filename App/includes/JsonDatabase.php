<?php
// filepath: /home/makis/Documents/GenUni/Coding/Web/E-Lib/app/includes/JsonDatabase.php
namespace App\Includes;

class JsonDatabase {
    private static $instance = null;
    private $dataPath;

    private function __construct() {
        $this->dataPath = __DIR__ . '/../../data/';
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function getFilePath(string $collection): string {
        return $this->dataPath . $collection . '.json';
    }

    private function readCollection(string $collection): array {
        $filePath = $this->getFilePath($collection);
        if (!file_exists($filePath)) {
            file_put_contents($filePath, json_encode([]));
            return [];
        }
        $json = file_get_contents($filePath);
        return json_decode($json, true) ?? [];
    }

    private function writeCollection(string $collection, array $data): bool {
        $filePath = $this->getFilePath($collection);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents($filePath, $json) !== false;
    }

    public function insert(string $collection, array $data): array {
        $documents = $this->readCollection($collection);
        $data['_id'] = uniqid();
        $documents[] = $data;
        if ($this->writeCollection($collection, $documents)) {
            return ['insertedId' => $data['_id']];
        }
        return ['error' => 'Failed to insert document'];
    }

    public function find(string $collection, array $filter = []): array {
        $documents = $this->readCollection($collection);
        if (empty($filter)) {
            return $documents;
        }
        
        return array_filter($documents, function($document) use ($filter) {
            foreach ($filter as $key => $value) {
                if (!isset($document[$key]) || $document[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    public function findOne(string $collection, array $filter = []) {
        $results = $this->find($collection, $filter);
        return !empty($results) ? reset($results) : null;
    }

    public function update(string $collection, array $filter, array $update): array {
        $documents = $this->readCollection($collection);
        $updated = 0;
        
        foreach ($documents as &$document) {
            $matches = true;
            foreach ($filter as $key => $value) {
                if (!isset($document[$key]) || $document[$key] !== $value) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                foreach ($update as $key => $value) {
                    $document[$key] = $value;
                }
                $updated++;
            }
        }
        
        if ($this->writeCollection($collection, $documents)) {
            return ['modifiedCount' => $updated];
        }
        return ['error' => 'Failed to update documents'];
    }

    public function delete(string $collection, array $filter): array {
        $documents = $this->readCollection($collection);
        $originalCount = count($documents);
        
        $documents = array_filter($documents, function($document) use ($filter) {
            foreach ($filter as $key => $value) {
                if (isset($document[$key]) && $document[$key] === $value) {
                    return false;
                }
            }
            return true;
        });
        
        $deletedCount = $originalCount - count($documents);
        
        if ($this->writeCollection($collection, array_values($documents))) {
            return ['deletedCount' => $deletedCount];
        }
        return ['error' => 'Failed to delete documents'];
    }
}