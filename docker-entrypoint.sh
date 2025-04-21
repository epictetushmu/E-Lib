#!/bin/bash
set -e

# Process environment variables and create .env file if needed
if [ ! -f .env ]; then
    echo "Creating .env file from environment variables"
    touch .env
    
    # Add environment variables to .env file
    env | grep -E '^(MONGO_|APP_|DB_)' > .env
    cat .env
fi

# Setup MongoDB certificates
echo "Setting up MongoDB certificates"
php setup-mongodb-cert.php

# Wait for MongoDB to be available
echo "Checking MongoDB connection..."
php -r '
require "vendor/autoload.php";
$attempts = 0;
$max_attempts = 10;
$connected = false;

while ($attempts < $max_attempts && !$connected) {
    try {
        $mongo_uri = getenv("MONGO_URI");
        echo "Trying to connect to: " . preg_replace("/\\/\\/([^:]+):([^@]+)@/", "//\\1:***@", $mongo_uri) . "\n";
        
        $client = new MongoDB\Client($mongo_uri, [], ["serverApi" => new MongoDB\Driver\ServerApi("1")]);
        $client->selectDatabase("admin")->command(["ping" => 1]);
        $connected = true;
        echo "MongoDB connection successful!\n";
    } catch (Exception $e) {
        $attempts++;
        echo "MongoDB connection attempt $attempts failed: " . $e->getMessage() . "\n";
        if ($attempts < $max_attempts) {
            echo "Retrying in 5 seconds...\n";
            sleep(5);
        }
    }
}

if (!$connected) {
    echo "Could not connect to MongoDB after $max_attempts attempts. Will continue with fallback.\n";
}
'

# Execute the CMD
exec "$@"
