<?php
/**
 * MongoDB Certificate Setup Script
 * 
 * This script downloads the MongoDB Atlas root certificate
 * and saves it in the proper location for your application.
 */

// Make it work both from CLI and web
function output($message) {
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
    } else {
        error_log($message);
    }
}

// Directory for certificates
$certDir = __DIR__ . '/certificates';
$certFile = $certDir . '/mongodb-ca.pem';

// Create directory if it doesn't exist
if (!is_dir($certDir)) {
    output("Creating certificate directory: {$certDir}");
    if (!mkdir($certDir, 0755, true)) {
        output("Failed to create certificate directory!");
        exit(1);
    }
}

// MongoDB Atlas root certificate URL
$certUrl = 'https://truststore.pki.mongodb.com/atlas-root-ca.pem';
output("Downloading certificate from: {$certUrl}");

// Download certificate
$certContent = @file_get_contents($certUrl);
if ($certContent === false) {
    // Try with context options to handle possible SSL verification issues
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    $certContent = @file_get_contents($certUrl, false, $context);
    
    if ($certContent === false) {
        output("Failed to download certificate! Checking if a local copy exists...");
        if (file_exists($certFile)) {
            output("Using existing certificate file.");
            exit(0); // Success using existing file
        } else {
            output("No local certificate found. Unable to proceed.");
            exit(1);
        }
    }
}

// Save certificate
if (file_put_contents($certFile, $certContent) === false) {
    output("Failed to save certificate to: {$certFile}");
    exit(1);
}

// Set proper permissions
chmod($certFile, 0644);

output("Certificate successfully downloaded and saved to: {$certFile}");
output("You can now use this certificate for MongoDB Atlas connections.");

// Update .env file if it exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile) && is_writable($envFile)) {
    $env = file_get_contents($envFile);
    if (strpos($env, 'MONGO_CERT_FILE=') === false) {
        output("Adding MONGO_CERT_FILE to .env");
        $env .= "\nMONGO_CERT_FILE=" . $certFile . "\n";
        file_put_contents($envFile, $env);
    }
}

// If we're in Docker, also update the global environment
if (getenv('DOCKER_ENV') === 'true' && !getenv('MONGO_CERT_FILE')) {
    putenv("MONGO_CERT_FILE={$certFile}");
}

output("Setup complete!");
