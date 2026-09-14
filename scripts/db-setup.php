#!/usr/bin/env php
<?php

/**
 * CLI: provisions the MongoDB database/collections/indexes E-Lib needs.
 *
 * Idempotent — safe to re-run. Same connection path as the app
 * (App\Integration\Database\MongoConnectionFactory), matching the pattern
 * used by scripts/mongo-ping.php.
 *
 * Targets the database name "LibraryDb" unconditionally. App\Database\MongoDatabase
 * (the class the running app actually uses) hardcodes 'LibraryDb' and ignores
 * whatever dbName is passed to it, so DB_NAME/DATABASE_NAME/DB_DATABASE in .env
 * are effectively dead — nothing in App/ wires them through to the real connection.
 * This script targets "LibraryDb" to match real app behavior, and warns (below) if
 * .env suggests a different name so that discrepancy isn't silently invisible.
 *
 * What it creates:
 *   - Collections: Books, Users
 *   - Indexes:
 *       Users:  unique index on email
 *       Books:  index on title, author, categories (multikey), and a
 *               compound index on {status, featured}
 *   - No sample/seed data — this script only provisions structure.
 *
 * Usage: php scripts/db-setup.php
 *    or: composer db:setup
 */

define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('RESET', "\033[0m");

function info(string $msg): void
{
    echo "  - {$msg}\n";
}

function success(string $msg): void
{
    echo GREEN . "  ✓ {$msg}\n" . RESET;
}

function warn(string $msg): void
{
    echo YELLOW . "  ⚠ {$msg}\n" . RESET;
}

function fail(string $msg): void
{
    fwrite(STDERR, RED . "  ✗ {$msg}\n" . RESET);
}

const TARGET_DB = 'LibraryDb';

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

App\Includes\Environment::load($root . '/.env');

echo YELLOW . "E-Lib Database Setup\n" . RESET;
echo "=====================\n\n";

// --- Step 0: flag DB_NAME/DB_DATABASE discrepancies (informational only) ---
$envDbName = App\Includes\Environment::get('DB_NAME', App\Includes\Environment::get('DB_DATABASE', ''));
if ($envDbName !== '' && $envDbName !== false && $envDbName !== TARGET_DB) {
    warn(
        ".env sets DB_NAME/DB_DATABASE=\"{$envDbName}\", but the app always connects to \"" .
        TARGET_DB . "\" (App\\Database\\MongoDatabase hardcodes this). " .
        "This script targets \"" . TARGET_DB . "\" to match real app behavior."
    );
}

// --- Step 1: connect ---
echo "Connecting...\n";

$uri = App\Includes\Environment::get('MONGO_URI', '');
$masked = preg_replace('#(mongodb\+?srv?://[^:]+:)[^@]+@#', '$1***@', (string) $uri);
info("URI (masked): {$masked}");

try {
    App\Integration\Database\MongoConnectionFactory::resetClient();
    $db = App\Integration\Database\MongoConnectionFactory::create('mongo', ['dbName' => TARGET_DB]);
    success("Connected to database \"" . TARGET_DB . "\"");
} catch (Throwable $e) {
    fail("Connection failed: " . $e->getMessage());
    exit(1);
}

echo "\n";

// --- Step 2: ensure collections exist ---
echo "Ensuring collections...\n";

$collectionsToCreate = ['Books', 'Users'];
try {
    $existing = iterator_to_array($db->listCollectionNames());
} catch (Throwable $e) {
    fail("Could not list existing collections: " . $e->getMessage());
    exit(1);
}

foreach ($collectionsToCreate as $name) {
    if (in_array($name, $existing, true)) {
        info("Collection \"{$name}\" already exists — skipping.");
        continue;
    }

    try {
        $db->createCollection($name);
        success("Created collection \"{$name}\".");
    } catch (\MongoDB\Driver\Exception\Exception $e) {
        // Code 48 = NamespaceExists (e.g. created concurrently between the list and create calls).
        if ($e->getCode() === 48 || str_contains($e->getMessage(), 'NamespaceExists')) {
            info("Collection \"{$name}\" already exists (race) — continuing.");
        } else {
            fail("Failed creating collection \"{$name}\": " . $e->getMessage());
            exit(1);
        }
    }
}

echo "\n";

// --- Step 3: ensure indexes exist ---
echo "Ensuring indexes...\n";

$indexPlan = [
    'Users' => [
        [
            'key' => ['email' => 1],
            'options' => ['unique' => true, 'name' => 'uniq_email'],
        ],
    ],
    'Books' => [
        [
            'key' => ['title' => 1],
            'options' => ['name' => 'idx_title'],
        ],
        [
            'key' => ['author' => 1],
            'options' => ['name' => 'idx_author'],
        ],
        [
            'key' => ['categories' => 1],
            'options' => ['name' => 'idx_categories'],
        ],
        [
            'key' => ['status' => 1, 'featured' => 1],
            'options' => ['name' => 'idx_status_featured'],
        ],
    ],
];

foreach ($indexPlan as $collectionName => $indexes) {
    $collection = $db->selectCollection($collectionName);

    foreach ($indexes as $idx) {
        try {
            $resultName = $collection->createIndex($idx['key'], $idx['options']);
            success("Index \"{$resultName}\" ensured on \"{$collectionName}\".");
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            // E11000-style duplicate key error during unique index build — this is the
            // realistic failure mode when running against a DB that already has
            // duplicate emails, so give an actionable message instead of a raw one.
            if (
                $collectionName === 'Users' && ($idx['options']['name'] ?? '') === 'uniq_email'
                && ($e->getCode() === 11000 || str_contains($e->getMessage(), 'duplicate key'))
            ) {
                fail(
                    "Cannot create unique index on Users.email: duplicate emails already exist. " .
                    "De-duplicate the Users collection, then re-run this script."
                );
            } else {
                fail("Failed creating index on \"{$collectionName}\": " . $e->getMessage());
            }
            exit(1);
        }
    }
}

echo "\n";
info(
    "Note: title/author indexes are plain (non-text) indexes — they don't accelerate the " .
    "unanchored case-insensitive regex search used by BookService::searchBooks(); a text " .
    "index or Atlas Search would be needed for that, and is out of scope here."
);

echo "\n" . GREEN . "Done. Database \"" . TARGET_DB . "\" is provisioned.\n" . RESET;
exit(0);
