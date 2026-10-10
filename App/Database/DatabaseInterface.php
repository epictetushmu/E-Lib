<?php

namespace App\Database;

interface DatabaseInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function insert(string $collection, array $data): array;

    /**
     * Insert several documents in one round trip. Throws on failure (unlike insert()).
     *
     * @param list<array<string, mixed>> $documents
     */
    public function insertMany(string $collection, array $documents): int;

    /**
     * Atomically update the first document matching $filter and return it as it is after the update
     * (null when nothing matched) — the primitive that lets several workers claim jobs without collisions.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options e.g. ['sort' => [...]]
     * @return array<string, mixed>|null
     */
    public function findOneAndUpdate(string $collection, array $filter, array $update, array $options = []): ?array;

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    public function find(string $collection, array $filter = [], array $options = []): array;

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>|null
     */
    public function findOne(string $collection, array $filter = []);

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @return array<string, mixed>
     */
    public function update(string $collection, array $filter, array $update): array;

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    public function delete(string $collection, array $filter): array;

    /**
     * @param array<int, array<string, mixed>> $pipeline
     * @return array<int, mixed>
     */
    public function aggregate(string $collection, array $pipeline): array;

    /**
     * @param array<string, mixed> $filter
     * @return list<mixed>
     */
    public function distinct(string $collection, string $field, array $filter = []): array;
}
