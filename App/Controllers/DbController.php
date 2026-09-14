<?php

namespace App\Controllers;

use App\Factory\DatabaseFactory;
use Exception;

/**
 * @deprecated Use App\Factory\DatabaseFactory instead
 * This class is maintained for backward compatibility and delegates all calls
 * to the DatabaseInterface obtained from DatabaseFactory.
 */
class DbController
{
    private static $instance = null;
    private $repository;

    private function __construct($dbName = null)
    {
        $this->repository = DatabaseFactory::getDatabase();

        // Display a deprecation warning in development environments
        if (getenv('APP_ENV') !== 'production') {
            trigger_error(
                'DbController is deprecated. Use App\Repository\DatabaseRepository instead.',
                E_USER_DEPRECATED
            );
        }
    }

    /**
     * Get singleton instance
     *
     * @param string|null $dbName Optional database name
     * @return DbController
     */
    public static function getInstance($dbName = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($dbName);
        }
        return self::$instance;
    }

    /**
     * @deprecated Use DatabaseRepository::insert instead
     */
    public function insert(string $collection, array $data): array
    {
        return $this->repository->insert($collection, $data);
    }

    /**
     * @deprecated Use DatabaseRepository::find instead
     */
    public function find(string $collection, array $filter = []): array
    {
        return $this->repository->find($collection, $filter);
    }

    /**
     * @deprecated Use DatabaseRepository::findOne instead
     */
    public function findOne(string $collection, array $filter = [])
    {
        return $this->repository->findOne($collection, $filter);
    }

    /**
     * @deprecated Use DatabaseRepository::update instead
     */
    public function update(string $collection, array $filter, array $update): array
    {
        return $this->repository->update($collection, $filter, $update);
    }

    /**
     * @deprecated Use DatabaseRepository::delete instead
     */
    public function delete(string $collection, array $filter): array
    {
        return $this->repository->delete($collection, $filter);
    }

    /**
     * @deprecated Use DatabaseRepository::aggregate instead
     */
    public function getFeatured(string $collection, $pipeline): array
    {
        return $this->repository->aggregate($collection, $pipeline);
    }
}
