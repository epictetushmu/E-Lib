<?php

namespace Tests\Support;

use App\Database\DatabaseInterface;
use App\Models\BaseModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Base class for Model unit tests.
 *
 * BaseModel's constructor eagerly resolves a real MongoDB connection via
 * DatabaseFactory::getDatabase(), which is unsuitable for unit tests (network
 * dependent, requires Atlas credentials). Instead, instances are created via
 * ReflectionClass::newInstanceWithoutConstructor() and a mock DatabaseInterface
 * is injected directly into the protected $db property.
 */
abstract class ModelTestCase extends TestCase
{
    use InjectsMocks;

    /**
     * @template T of BaseModel
     * @param class-string<T> $modelClass
     * @return T
     */
    protected function makeModel(string $modelClass, DatabaseInterface $db): BaseModel
    {
        $reflection = new ReflectionClass($modelClass);
        /** @var T $model */
        $model = $reflection->newInstanceWithoutConstructor();

        $this->inject($model, 'db', $db);

        return $model;
    }
}
