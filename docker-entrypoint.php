<?php
/**
 * Docker Entrypoint Script
 * 
 * This script runs when the Docker container starts,
 * handling any necessary setup/configuration before
 * starting the Apache server.
 */

// Output function that works in CLI or browser
function output($message) {
    echo $message . PHP_EOL;
}

output('Starting E-Lib Docker container setup...');

// Check for environment variables
output('Checking environment configuration...');
if (file_exists(__DIR__ . '/.env')) {
    output('Found .env file');
} else {
    output('No .env file found, checking if environment variables are set directly');
}

// Ensure storage directories exist and are writable
$directories = [
    __DIR__ . '/storage/logs',
    __DIR__ . '/public/uploads',
    __DIR__ . '/public/assets/uploads/documents',
    __DIR__ . '/public/assets/uploads/thumbnails',
    __DIR__ . '/certificates',
    __DIR__ . '/cache'
];

output('Checking required directories...');
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        output("Creating directory: $dir");
        mkdir($dir, 0777, true);
    }
    chmod($dir, 0777);
    output("Directory $dir is ready");
}

// Ensure MongoDB certificate exists and is valid
output('Checking MongoDB certificate...');
$certFile = __DIR__ . '/certificates/mongodb-ca.pem';

// Verify certificate and try to fix if needed
if (!file_exists($certFile) || filesize($certFile) < 100) {
    output('MongoDB certificate is missing or invalid, attempting to fix...');
    
    // Try to run the cert setup script
    output('Running MongoDB certificate setup script...');
    include_once __DIR__ . '/setup-mongodb-cert.php';
    
    // Double-check that the certificate now exists
    if (!file_exists($certFile) || filesize($certFile) < 100) {
        output('WARNING: MongoDB certificate still unavailable after setup attempts');
        output('The application will attempt to use system CA certificates for MongoDB connections');
    } else {
        output('MongoDB certificate is now ready');
    }
} else {
    output('MongoDB certificate is already available');
}

// Set environment variables for the certificate
putenv("MONGO_CERT_FILE=$certFile");
$_ENV['MONGO_CERT_FILE'] = $certFile;

output('Setting Docker environment flag...');
putenv("DOCKER_ENV=true");
$_ENV['DOCKER_ENV'] = 'true';

// Set proper permissions for the web server user
output('Setting proper file permissions...');
exec('chown -R www-data:www-data /var/www/html');

output('E-Lib container setup complete!');
output('Starting Apache server...');

// Start Apache in foreground
exec('apache2-foreground');
