<?php

namespace Tests\Integration;

use App\Database\JsonDatabase;
use App\Integration\Database\JsonDbInteraction;
use PHPUnit\Framework\TestCase;

class JsonDatabaseTest extends TestCase
{
    private $testStoragePath;
    private $jsonDatabase;
    
    protected function setUp(): void
    {
        // Create a temporary test directory
        $this->testStoragePath = sys_get_temp_dir() . '/elib_tests_' . uniqid();
        mkdir($this->testStoragePath, 0755, true);
        
        // Initialize JsonDbInteraction with test path
        JsonDbInteraction::initialize($this->testStoragePath, true);
        
        // Create a database instance
        $this->jsonDatabase = new JsonDatabase();
    }
    
    protected function tearDown(): void
    {
        // Clean up test files and directory
        foreach (glob($this->testStoragePath . '/*.json') as $file) {
            unlink($file);
        }
        rmdir($this->testStoragePath);
    }
    
    public function testInsertAndFind()
    {
        $collection = 'test_books';
        $bookData = [
            'title' => 'Test Book',
            'author' => 'Test Author',
            'pages' => 100,
            'published' => true
        ];
        
        // Insert a test document
        $result = $this->jsonDatabase->insert($collection, $bookData);
        
        // Verify insertion
        $this->assertArrayHasKey('insertedId', $result);
        $this->assertNotEmpty($result['insertedId']);
        
        // Find the inserted document
        $insertedId = $result['insertedId'];
        $foundItems = $this->jsonDatabase->find($collection, ['_id' => $insertedId]);
        
        // Verify the document was found and has correct data
        $this->assertCount(1, $foundItems);
        $this->assertEquals($insertedId, $foundItems[0]['_id']);
        $this->assertEquals('Test Book', $foundItems[0]['title']);
        $this->assertEquals('Test Author', $foundItems[0]['author']);
    }
    
    public function testUpdate()
    {
        $collection = 'test_books';
        
        // Insert test document first
        $bookData = [
            'title' => 'Original Title',
            'author' => 'Original Author',
            'published' => false
        ];
        
        $result = $this->jsonDatabase->insert($collection, $bookData);
        $insertedId = $result['insertedId'];
        
        // Update the document
        $updateResult = $this->jsonDatabase->update(
            $collection, 
            ['_id' => $insertedId], 
            ['title' => 'Updated Title', 'published' => true]
        );
        
        // Check update result
        $this->assertArrayHasKey('matchedCount', $updateResult);
        $this->assertArrayHasKey('modifiedCount', $updateResult);
        $this->assertEquals(1, $updateResult['matchedCount']);
        $this->assertEquals(1, $updateResult['modifiedCount']);
        
        // Verify the document was updated correctly
        $foundItems = $this->jsonDatabase->find($collection, ['_id' => $insertedId]);
        $this->assertEquals('Updated Title', $foundItems[0]['title']);
        $this->assertEquals('Original Author', $foundItems[0]['author']); // Unchanged field
        $this->assertTrue($foundItems[0]['published']); // Updated field
    }
    
    public function testDelete()
    {
        $collection = 'test_books';
        
        // Insert two test documents
        $book1 = ['title' => 'Book 1', 'author' => 'Author 1'];
        $book2 = ['title' => 'Book 2', 'author' => 'Author 2'];
        
        $this->jsonDatabase->insert($collection, $book1);
        $result2 = $this->jsonDatabase->insert($collection, $book2);
        $id2 = $result2['insertedId'];
        
        // Make sure we have 2 documents
        $initialCount = count($this->jsonDatabase->find($collection, []));
        $this->assertEquals(2, $initialCount);
        
        // Delete one document
        $deleteResult = $this->jsonDatabase->delete($collection, ['_id' => $id2]);
        
        // Check delete result
        $this->assertArrayHasKey('deletedCount', $deleteResult);
        $this->assertEquals(1, $deleteResult['deletedCount']);
        
        // Verify only one document remains
        $remainingItems = $this->jsonDatabase->find($collection, []);
        $this->assertCount(1, $remainingItems);
        $this->assertEquals('Book 1', $remainingItems[0]['title']);
    }
}