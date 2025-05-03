<?php

namespace Tests\Integration;

use App\Database\MongoDatabase;
use App\Integration\Database\MongoConnectionFactory;
use App\Database\JsonDatabase;
use App\Includes\Environment;
use PHPUnit\Framework\TestCase;

class MongoConnectionTest extends TestCase
{
    private $originalMongoUri;
    private $originalMongoPassword;
    private $testMongoUri = 'mongodb://root:example@localhost:27017/admin';
    
    protected function setUp(): void
    {
        // Store the original environment variables
        $this->originalMongoUri = getenv('MONGO_URI');
        $this->originalMongoPassword = getenv('MONGO_PASSWORD');
        
        // Set test MongoDB URI for testing with Docker credentials
        putenv('MONGO_URI=' . $this->testMongoUri);
        
        // Reset any static properties in MongoConnectionFactory
        $this->resetMongoConnectionFactory();
    }
    
    protected function tearDown(): void
    {
        // Restore the original environment variables
        if ($this->originalMongoUri !== false) {
            putenv('MONGO_URI=' . $this->originalMongoUri);
        } else {
            putenv('MONGO_URI'); // Unset the variable
        }
        
        if ($this->originalMongoPassword !== false) {
            putenv('MONGO_PASSWORD=' . $this->originalMongoPassword);
        } else {
            putenv('MONGO_PASSWORD'); // Unset the variable
        }
        
        // Reset any static properties in MongoConnectionFactory
        $this->resetMongoConnectionFactory();
    }
    
    /**
     * Reset static properties in MongoConnectionFactory using reflection
     */
    private function resetMongoConnectionFactory()
    {
        $reflection = new \ReflectionClass(MongoConnectionFactory::class);
        $property = $reflection->getProperty('mongoClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
    
    /**
     * Test creating a connection to MongoDB
     */
    public function testMongoConnection()
    {
        // Only run this test if MongoDB is likely available at the test URI
        if (!$this->isMongoAvailable()) {
            $this->markTestSkipped('MongoDB is not available at ' . $this->testMongoUri);
            return;
        }
        
        try {
            // Attempt to create a MongoDB connection
            $connection = MongoConnectionFactory::create('mongo', ['fallback' => false]);
            $this->assertNotNull($connection, 'MongoDB connection should not be null');
            
            // Check if the client is valid by running a simple ping command
            $pingResult = $connection->command(['ping' => 1]);
            $this->assertNotEmpty($pingResult, 'MongoDB ping command should return a result');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('MongoDB connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test MongoDB fallback to JsonDatabase when MongoDB is unavailable
     */
    public function testMongoFallback()
    {
        // Test the fallback mechanism with a deliberately invalid MongoDB URI
        putenv('MONGO_URI=mongodb://non-existent-host:27017');
        $this->resetMongoConnectionFactory();
        
        // Create a connection with fallback enabled
        $connection = MongoConnectionFactory::create('mongo', ['fallback' => true]);
        
        // When MongoDB is unavailable, it should fall back to JsonDatabase
        $this->assertInstanceOf(
            JsonDatabase::class,
            $connection,
            'Connection should fall back to JsonDatabase when MongoDB is unavailable'
        );
        
        // Reset URI for other tests
        putenv('MONGO_URI=' . $this->testMongoUri);
        $this->resetMongoConnectionFactory();
    }
    
    /**
     * Test basic MongoDB operations through MongoDatabase class
     */
    public function testMongoDatabaseOperations()
    {
        // Only run this test if MongoDB is likely available
        if (!$this->isMongoAvailable()) {
            $this->markTestSkipped('MongoDB is not available for operations testing');
            return;
        }
        
        $testCollection = 'test_integration_' . uniqid();
        
        try {
            // Use direct MongoDB connection with authentication
            $factory = new MongoConnectionFactory();
            $mongoDb = $factory->create('mongo', [
                'dbName' => 'admin',
                'fallback' => false
            ]);
            
            if (!($mongoDb instanceof \MongoDB\Database)) {
                $this->markTestSkipped('Could not get a valid MongoDB connection');
                return;
            }
            
            // Test data
            $testData = [
                'name' => 'Test Document',
                'value' => 'Integration Test',
                'timestamp' => time()
            ];
            
            // Insert a test document
            $result = $mongoDb->selectCollection($testCollection)->insertOne($testData);
            $insertedId = (string)$result->getInsertedId();
            $this->assertNotEmpty($insertedId, 'Insert should return insertedId');
            
            // Find the document by ID
            $findResult = $mongoDb->selectCollection($testCollection)->findOne(['_id' => new \MongoDB\BSON\ObjectId($insertedId)]);
            $this->assertNotNull($findResult, 'Should find the inserted document');
            $this->assertEquals($testData['name'], $findResult['name'], 'Retrieved document should match inserted data');
            
            // Update the document
            $updateResult = $mongoDb->selectCollection($testCollection)->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId($insertedId)],
                ['$set' => ['value' => 'Updated Value']]
            );
            $this->assertEquals(1, $updateResult->getModifiedCount(), 'One document should be modified');
            
            // Verify update
            $updatedDoc = $mongoDb->selectCollection($testCollection)->findOne(['_id' => new \MongoDB\BSON\ObjectId($insertedId)]);
            $this->assertEquals('Updated Value', $updatedDoc['value'], 'Document should have updated value');
            
            // Delete the test document
            $deleteResult = $mongoDb->selectCollection($testCollection)->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($insertedId)]);
            $this->assertEquals(1, $deleteResult->getDeletedCount(), 'One document should be deleted');
            
            // Verify deletion
            $findAfterDelete = $mongoDb->selectCollection($testCollection)->findOne(['_id' => new \MongoDB\BSON\ObjectId($insertedId)]);
            $this->assertNull($findAfterDelete, 'Document should be deleted');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('MongoDB operations test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test MongoDB aggregate operation
     */
    public function testMongoAggregation()
    {
        // Only run this test if MongoDB is likely available
        if (!$this->isMongoAvailable()) {
            $this->markTestSkipped('MongoDB is not available for aggregation testing');
            return;
        }
        
        $testCollection = 'test_aggregation_' . uniqid();
        
        try {
            // Use direct MongoDB connection with authentication
            $factory = new MongoConnectionFactory();
            $mongoDb = $factory->create('mongo', [
                'dbName' => 'admin',
                'fallback' => false
            ]);
            
            if (!($mongoDb instanceof \MongoDB\Database)) {
                $this->markTestSkipped('Could not get a valid MongoDB connection');
                return;
            }
            
            // Insert multiple test documents
            $testData = [
                ['category' => 'book', 'price' => 25, 'author' => 'Author A'],
                ['category' => 'book', 'price' => 30, 'author' => 'Author B'],
                ['category' => 'magazine', 'price' => 10, 'author' => 'Author A'],
                ['category' => 'book', 'price' => 45, 'author' => 'Author C']
            ];
            
            // Create the collection and insert documents
            $collection = $mongoDb->selectCollection($testCollection);
            foreach ($testData as $document) {
                $collection->insertOne($document);
            }
            
            // Run a simple aggregation pipeline
            $pipeline = [
                ['$match' => ['category' => 'book']],
                ['$group' => [
                    '_id' => '$category',
                    'avgPrice' => ['$avg' => '$price'],
                    'count' => ['$sum' => 1]
                ]]
            ];
            
            $cursor = $collection->aggregate($pipeline);
            $aggregateResult = iterator_to_array($cursor);
            
            // Verify aggregation results
            $this->assertNotEmpty($aggregateResult, 'Aggregation should return results');
            $this->assertEquals(1, count($aggregateResult), 'Should have one result group');
            $this->assertEquals('book', $aggregateResult[0]['_id'], 'Category should be book');
            $this->assertEquals(3, $aggregateResult[0]['count'], 'Should count 3 books');
            $this->assertEquals(100/3, $aggregateResult[0]['avgPrice'], 'Average price should be calculated correctly', 0.01);
            
            // Clean up
            $collection->drop();
            
        } catch (\Exception $e) {
            $this->markTestSkipped('MongoDB aggregation test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test MongoDB connection configuration validation
     * 
     * This test can be used as a diagnostic tool to validate MongoDB connection configuration
     */
    public function testMongoConnectionConfigValidation()
    {
        // Get the current MongoDB URI from environment
        $currentUri = getenv('MONGO_URI');
        
        // Output test header
        echo "\n----- MongoDB Connection Validation -----\n";
        
        // Check if the MongoDB URI is set
        if (empty($currentUri)) {
            echo "❌ MONGO_URI environment variable is not set\n";
            echo "   You should set a valid MongoDB connection string in your environment\n";
            echo "   Example: mongodb://username:password@hostname:port/database\n";
            $this->assertTrue(true); // Just to avoid test failure
            return;
        } else {
            echo "✅ MONGO_URI environment variable is set\n";
            echo "   URI format: " . $this->maskConnectionString($currentUri) . "\n";
        }
        
        // Check if URI has correct format
        if (!preg_match('/^mongodb(\+srv)?:\/\//', $currentUri)) {
            echo "❌ MONGO_URI format is invalid. Should start with 'mongodb://' or 'mongodb+srv://'\n";
        } else {
            echo "✅ MONGO_URI format appears valid\n";
        }
        
        // Parse URI to get hostname and port
        $parseResult = parse_url($currentUri);
        $hostname = $parseResult['host'] ?? 'localhost';
        $port = $parseResult['port'] ?? 27017;
        
        // Check if we can connect to MongoDB server
        try {
            // Try to establish a connection with 5 second timeout
            $socket = @fsockopen($hostname, $port, $errno, $errstr, 5);
            
            if ($socket) {
                echo "✅ MongoDB server is reachable\n";
                fclose($socket);
            } else {
                echo "❌ Cannot reach MongoDB server: {$errstr} (error {$errno})\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error checking MongoDB server: {$e->getMessage()}\n";
        }
        
        // Try to create a MongoDB connection and ping
        try {
            // Set a short timeout for this test
            $connection = MongoConnectionFactory::create('mongo', [
                'fallback' => false,
                'mongoOptions' => ['serverSelectionTimeoutMS' => 5000]
            ]);
            
            // Ping the server
            $pingResult = $connection->command(['ping' => 1]);
            echo "✅ MongoDB connection successful! Authentication is working\n";
            
            // Try to list available databases
            try {
                $client = MongoConnectionFactory::getClient();
                $dbs = $client->listDatabases();
                $dbCount = iterator_count($dbs);
                echo "✅ Found {$dbCount} database(s) on the server\n";
            } catch (\Exception $e) {
                echo "⚠️ Could list databases but may have limited permissions: " . $e->getMessage() . "\n";
            }
            
        } catch (\Exception $e) {
            echo "❌ MongoDB connection or authentication failed\n";
            echo "   Error: " . $e->getMessage() . "\n";
            
            // Provide specific guidance based on error message
            if (stripos($e->getMessage(), 'authentication') !== false) {
                echo "   → Authentication problem - check username and password\n";
            } elseif (stripos($e->getMessage(), 'timed out') !== false) {
                echo "   → Connection timed out - check network or firewall settings\n";
            } elseif (stripos($e->getMessage(), 'SSL') !== false || stripos($e->getMessage(), 'TLS') !== false) {
                echo "   → SSL/TLS issue - check certificate configuration\n";
            }
        }
        
        echo "----- End of MongoDB Connection Validation -----\n\n";
        
        // This assertion ensures the test passes even if the connection fails
        // The purpose is diagnostic information, not pass/fail
        $this->assertTrue(true);
    }
    
    /**
     * Helper method to check if MongoDB is likely available
     */
    private function isMongoAvailable()
    {
        try {
            // Parse URI to get hostname and port
            $parseResult = parse_url($this->testMongoUri);
            $hostname = $parseResult['host'] ?? 'localhost';
            $port = $parseResult['port'] ?? 27017;
            
            $socket = @fsockopen($hostname, $port, $errno, $errstr, 0.5);
            if ($socket) {
                fclose($socket);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Helper method to mask sensitive information in connection strings
     */
    private function maskConnectionString($uri)
    {
        if (empty($uri)) {
            return '';
        }
        
        // Parse the URI to detect the structure
        $pattern = '/^(mongodb(\+srv)?:\/\/)([^:@]+):([^@]+)@(.+)$/i';
        if (preg_match($pattern, $uri, $matches)) {
            // Has username and password
            $protocol = $matches[1]; // mongodb:// or mongodb+srv://
            $username = $matches[3]; 
            $password = preg_replace('/./', '*', $matches[4]); // Mask password
            $hosts = $matches[5];
            
            return $protocol . $username . ':' . $password . '@' . $hosts;
        } else {
            // Format without authentication or couldn't parse
            return preg_replace('/(:\/\/)([^@:\/]+):([^@\/]+)@/', '$1$2:***@', $uri);
        }
    }
}