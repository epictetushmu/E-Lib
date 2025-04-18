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
    $connectionString = getenv('MONGO_URI') ?: 'mongodb://localhost:27017';
    
    // Replace placeholder with actual password
    $mongoPassword = getenv('MONGO_PASSWORD');
    if ($mongoPassword && strpos($connectionString, '<db_password>') !== false) {
        $connectionString = str_replace('<db_password>', $mongoPassword, $connectionString);
    }
    
    echo "Connecting to MongoDB at: " . preg_replace('/\/\/([^:]+):([^@]+)@/', '//\\1:***@', $connectionString) . "\n";
    
    // Try to connect
    $factory = new App\Integration\Database\MongoConnectionFactory();
    $db = $factory->create('mongo', ['fallback' => false]);
    echo "✓ MongoDB connection successful!\n";
} catch (Exception $e) {
    echo "✗ MongoDB connection failed: " . $e->getMessage() . "\n";
    // Continue anyway, as the application might use fallback
}

// Pass control to Apache
echo "Starting web server...\n";
exec("apache2-foreground");
