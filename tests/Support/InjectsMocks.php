<?php

namespace Tests\Support;

use ReflectionProperty;

/**
 * Shared helper for tests that build an object via newInstanceWithoutConstructor()
 * and need to inject a mock collaborator directly into a declared property.
 */
trait InjectsMocks
{
    protected function inject(object $instance, string $property, object $value): void
    {
        $reflectionProperty = new ReflectionProperty($instance::class, $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($instance, $value);
    }
}
