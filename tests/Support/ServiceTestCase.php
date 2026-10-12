<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Base class for Service unit tests.
 *
 * Services construct their real Model collaborator in the constructor
 * (`new Books()`, `new Users()`, ...) with no dependency injection seam.
 * Instances are created via newInstanceWithoutConstructor() and a mock
 * collaborator is injected directly into the declaring property.
 */
abstract class ServiceTestCase extends TestCase
{
    use InjectsMocks;

    /**
     * @template T of object
     * @param class-string<T> $serviceClass
     * @return T
     */
    protected function makeService(string $serviceClass, string $propertyName, object $collaborator): object
    {
        $reflection = new ReflectionClass($serviceClass);
        $service = $reflection->newInstanceWithoutConstructor();

        $this->inject($service, $propertyName, $collaborator);

        return $service;
    }
}
