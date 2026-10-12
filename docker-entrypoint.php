<?php

/**
 * Docker Entrypoint Script
 *
 * This script runs when the Docker container starts,
 * handling any necessary setup/configuration before
 * starting the Apache server.
 */

// Output function that works in CLI or browser
function output($message)
{
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

output('Setting Docker environment flag...');
putenv("DOCKER_ENV=true");
$_ENV['DOCKER_ENV'] = 'true';

output('E-Lib container setup complete!');
output('Starting Apache server...');

// Start Apache in foreground
exec('apache2-foreground');
