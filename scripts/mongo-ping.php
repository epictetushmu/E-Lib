#!/usr/bin/env php
<?php

/**
 * CLI: same Mongo path as the web app (MongoConnectionFactory).
 * Usage: php scripts/mongo-ping.php
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

App\Includes\Environment::load($root . '/.env');

$dbName = App\Includes\Environment::get('DB_NAME', 'LibraryDb');

$uri = App\Includes\Environment::get('MONGO_URI', '');
$password = App\Includes\Environment::get('MONGO_PASSWORD', '');
if ($password !== '' && $password !== false) {
    $encoded = rawurlencode((string) $password);
    if (str_contains((string) $uri, '${MONGO_PASSWORD}')) {
        $uri = str_replace('${MONGO_PASSWORD}', $encoded, $uri);
    }
    if (str_contains((string) $uri, '<db_password>')) {
        $uri = str_replace('<db_password>', $encoded, $uri);
    }
}
$masked = preg_replace('#(mongodb(?:\+srv)?://[^:@/]+:)[^@]+@#', '$1***@', (string) $uri);
echo "URI (masked): {$masked}\n";

try {
    App\Integration\Database\MongoConnectionFactory::resetClient();
    App\Integration\Database\MongoConnectionFactory::create('mongo', ['dbName' => $dbName]);
    echo "OK: ping succeeded on database \"{$dbName}\" (same code path as the app).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
