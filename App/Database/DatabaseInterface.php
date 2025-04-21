<?php
namespace App\Database;

interface DatabaseInterface {
    public function insert(string $collection, array $data): array;
    public function find(string $collection, array $filter = []): array;
    public function findOne(string $collection, array $filter = []);
    public function update(string $collection, array $filter, array $update): array;
    public function delete(string $collection, array $filter): array;
    public function aggregate(string $collection, array $pipeline): array;
}