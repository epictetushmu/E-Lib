<?php
/**
 * Docker Entrypoint Script
 * 
 * This script runs when the Docker container starts, ensuring proper setup.
 * - Sets up MongoDB certificates
 * - Verifies database connection
 * - Starts the web server
 */

// Run the certificate setup script
echo "Setting up MongoDB certificates...\n";
require_once __DIR__ . '/setup-mongodb-cert.php';

// Verify MongoDB connection
echo "Verifying MongoDB connection...\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Load environment variables from .env file
    if (class_exists('App\Includes\Environment')) {
        try {
            App\Includes\Environment::load();
            echo "Environment variables loaded from .env file\n";
        } catch (Exception $e) {
            echo "Warning: Failed to load environment variables: " . $e->getMessage() . "\n";
        }
    }
    
    $connectionString = App\Includes\Environment::get('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://localhost:27017');
    $mongoPassword = App\Includes\Environment::get('MONGO_PASSWORD', getenv('MONGO_PASSWORD'));
    
    // Replace password placeholders if needed
    if ($mongoPassword) {
        if (strpos($connectionString, '<db_password>') !== false) {
            $connectionString = str_replace('<db_password>', $mongoPassword, $connectionString);
            echo "Replaced <db_password> placeholder in connection string\n";
            
        } elseif (strpos($connectionString, '<PASSWORD>') !== false) {
            $connectionString = str_replace('<PASSWORD>', $mongoPassword, $connectionString);
            echo "Replaced <PASSWORD> placeholder in connection string\n";
        }
    }
    
    // Log the connection string (with redacted password)
    $redactedUri = preg_replace('/\/\/([^:]+):([^@]+)@/', '//\\1:***@', $connectionString);
    echo "Connecting to MongoDB at: " . $redactedUri . "\n";
    
    // Try to connect
    $factory = new App\Integration\Database\MongoConnectionFactory();
    $db = $factory->create('mongo', ['fallback' => false]);
    echo "✓ MongoDB connection successful!\n";
} catch (Exception $e) {
    echo "✗ MongoDB connection failed: " . $e->getMessage() . "\n";
    echo "Connection details: MONGO_URI=" . (getenv('MONGO_URI') ? 'set' : 'not set') . 
         ", MONGO_PASSWORD=" . (getenv('MONGO_PASSWORD') ? 'set' : 'not set') . "\n";
    // Continue anyway, as the application might use fallback
}

// Pass control to Apache
echo "Starting web server...\n";
exec("apache2-foreground");
