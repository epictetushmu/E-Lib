<?php

namespace App\Models;

use App\Database\DatabaseInterface;
use App\Factory\DatabaseFactory;
use App\Helpers\Database\MongoHelper;
use InvalidArgumentException;

/**
 * BaseModel provides common functionality for all models
 * and ensures consistent data access patterns
 */
abstract class BaseModel
{
    protected DatabaseInterface $db;
    protected string $collection = '';

    /**
     * Constructor
     */
    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? DatabaseFactory::getDatabase();
    }

    /**
     * Validate data according to model rules
     *
     * @param array<string, mixed> $data The data to validate
     * @return array<string, string> Any validation errors
     */
    abstract public function validate(array $data): array;

    /**
     * Ensure the ID is a valid MongoDB ObjectId
     *
     * @param string $id
     * @return \MongoDB\BSON\ObjectId
     * @throws InvalidArgumentException
     */
    protected function ensureId(string $id)
    {
        if (!preg_match('/^[0-9a-f]{24}$/', $id)) {
            throw new InvalidArgumentException('Invalid ID format');
        }
        return MongoHelper::createObjectId($id);
    }

    /**
     * Find all documents matching filter
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    public function findAll(array $filter = [], array $options = []): array
    {
        return $this->db->find($this->collection, $filter, $options);
    }

    /**
     * Find document by ID
     *
     * @param string $id
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function findById(string $id)
    {
        return $this->db->findOne($this->collection, ['_id' => $this->ensureId($id)]);
    }

    /**
     * Create a new document
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors));
        }
        return $this->db->insert($this->collection, $data);
    }

    /**
     * Update document by ID
     *
     * @param string $id
     * @param array<string, mixed> $data
     * @return array<string, mixed>|false
     * @throws InvalidArgumentException
     */
    public function updateById(string $id, array $data)
    {
        return $this->db->update(
            $this->collection,
            ['_id' => $this->ensureId($id)],
            ['$set' => $data]
        );
    }

    /**
     * Delete document by ID
     *
     * @param string $id
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function deleteById(string $id): array
    {
        return $this->db->delete($this->collection, ['_id' => $this->ensureId($id)]);
    }
}
