<?php

namespace Tests\Unit;

use App\Integration\Database\JsonDbInteraction;
use PHPUnit\Framework\TestCase;

class JsonDbInteractionTest extends TestCase
{
    private $testStoragePath;
    
    protected function setUp(): void
    {
        // Create a temporary test directory
        $this->testStoragePath = sys_get_temp_dir() . '/elib_tests_' . uniqid();
        mkdir($this->testStoragePath, 0755, true);
    }
    
    protected function tearDown(): void
    {
        // Remove test files and directory
        foreach (glob($this->testStoragePath . '/*.json') as $file) {
            unlink($file);
        }
        rmdir($this->testStoragePath);
    }
    
    public function testGetDefaultStoragePath()
    {
        $path = JsonDbInteraction::getDefaultStoragePath();
        $this->assertNotEmpty($path);
        $this->assertStringContainsString('Storage/json_db', $path);
    }
    
    public function testGetCollectionPath()
    {
        $collectionName = 'test_collection';
        
        // Test with a specific path
        $path = JsonDbInteraction::getCollectionPath($collectionName);
        $this->assertStringEndsWith($collectionName . '.json', $path);
        
        // Test that it prevents path traversal
        $this->expectException(\Exception::class);
        JsonDbInteraction::getCollectionPath('../dangerous_path');
    }
    
    public function testSaveAndLoadCollection()
    {
        // Initialize with test path
        JsonDbInteraction::initialize($this->testStoragePath);
        
        $collectionName = 'test_collection';
        $testData = [
            ['id' => '1', 'name' => 'Test Item 1'],
            ['id' => '2', 'name' => 'Test Item 2']
        ];
        
        // Test saving
        $result = JsonDbInteraction::saveCollection($collectionName, $testData);
        $this->assertTrue($result);
        
        // Test loading
        $loadedData = JsonDbInteraction::loadCollection($collectionName);
        $this->assertEquals($testData, $loadedData);
    }
}