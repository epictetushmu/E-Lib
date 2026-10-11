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
 *   - Collections: Books, Users, Annotations, BookChunks
 *   - Indexes:
 *       Users:  unique index on email
 *       Books:  index on title, author, categories (multikey), a
 *               compound index on {status, featured}, and index_status
 *       BookChunks: {book_id, embedding_model, page}
 *   - Atlas Search + Atlas Vector Search indexes on BookChunks for content search
 *     (skipped with a warning on a MongoDB without Atlas Search, e.g. plain mongo in CI)
 *   - No sample/seed data — this script only provisions structure.
 *
 * Usage: php scripts/db-setup.php
 *    or: composer db:setup
 */

require_once __DIR__ . '/cli-output.php';

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
$masked = preg_replace('#(mongodb(?:\+srv)?://[^:@/]+:)[^@]+@#', '$1***@', (string) $uri);
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

$collectionsToCreate = ['Books', 'Users', 'Annotations', 'BookChunks'];
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
        [
            // The content-search worker's claim query.
            'key' => ['index_status' => 1],
            'options' => ['name' => 'idx_index_status'],
        ],
    ],
    'BookChunks' => [
        [
            // Per-book deletes/status updates, and the worker's resume cleanup.
            'key' => ['book_id' => 1, 'embedding_model' => 1, 'page' => 1],
            'options' => ['name' => 'idx_book_model_page'],
        ],
    ],
    'Annotations' => [
        [
            // Every read is "this user's annotations for this book".
            'key' => ['user_id' => 1, 'book_id' => 1, 'page' => 1],
            'options' => ['name' => 'idx_user_book_page'],
        ],
    ],
    'Loans' => [
        [
            // "Does this reader hold a copy?" and per-book active counts (book_id prefix; a book has few slots).
            'key' => ['book_id' => 1, 'user_id' => 1, 'free_at' => 1],
            'options' => ['name' => 'idx_book_user_free'],
        ],
    ],
    'LoanHistory' => [
        [
            // The scheduler's every-minute overdue / due-soon scans: open physical loans by due date.
            'key' => ['kind' => 1, 'returned_at' => 1, 'due_at' => 1],
            'options' => ['name' => 'idx_kind_returned_due'],
        ],
        [
            // A reader's own loans, newest first (profile → Loans).
            'key' => ['user_id' => 1, '_id' => -1],
            'options' => ['name' => 'idx_user_newest'],
        ],
    ],
    'Waitlist' => [
        [
            // The queue for a book, in order; also serves the scheduler's distinct book_id.
            'key' => ['book_id' => 1, 'joined_at' => 1],
            'options' => ['name' => 'idx_book_joined'],
        ],
    ],
    'Notifications' => [
        [
            'key' => ['user_id' => 1, 'created_at' => -1],
            'options' => ['name' => 'idx_user_created'],
        ],
        [
            // The scheduler's queued-email pickup.
            'key' => ['email_status' => 1, 'created_at' => 1],
            'options' => ['name' => 'idx_email_status_created'],
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

// --- Step 4: Atlas Search / Vector Search indexes for content search ---
echo "Ensuring Atlas Search indexes...\n";

$searchIndexes = [
    [
        'name' => App\Models\BookChunks::TEXT_INDEX,
        'type' => 'search',
        'definition' => ['mappings' => ['dynamic' => false, 'fields' => [
            // One standard-analyzed field plus Greek/English stemmed variants: the library is mixed-language.
            'text' => [
                'type' => 'string',
                'analyzer' => 'lucene.standard',
                'multi' => [
                    'el' => ['type' => 'string', 'analyzer' => 'lucene.greek'],
                    'en' => ['type' => 'string', 'analyzer' => 'lucene.english'],
                ],
            ],
            'status' => ['type' => 'token'],
        ]]],
    ],
    [
        'name' => App\Models\BookChunks::VECTOR_INDEX,
        'type' => 'vectorSearch',
        'definition' => ['fields' => [
            [
                'type' => 'vector',
                'path' => 'embedding',
                // Must match the embedding model's output size (bge-m3: 1024).
                'numDimensions' => (int) App\Includes\Environment::get('EMBEDDING_DIMENSIONS', 1024),
                'similarity' => 'cosine',
            ],
            ['type' => 'filter', 'path' => 'status'],
            ['type' => 'filter', 'path' => 'embedding_model'],
        ]],
    ],
];

$chunks = $db->selectCollection('BookChunks');
foreach ($searchIndexes as $idx) {
    try {
        $exists = iterator_to_array($chunks->listSearchIndexes(['name' => $idx['name']])) !== [];
        if ($exists) {
            // Left as is rather than updated: updateSearchIndex() on a vectorSearch index is rejected
            // ("mappings" is required) at least by atlas-local. To change one (e.g. a model with a
            // different EMBEDDING_DIMENSIONS), drop it in Atlas and re-run this script.
            info("Search index \"{$idx['name']}\" already exists on \"BookChunks\" — skipping.");
        } else {
            $chunks->createSearchIndex($idx['definition'], ['name' => $idx['name'], 'type' => $idx['type']]);
            success("Search index \"{$idx['name']}\" created on \"BookChunks\" (Atlas builds it in the background).");
        }
    } catch (Throwable $e) {
        warn(
            "Could not ensure search index \"{$idx['name']}\": " . $e->getMessage() .
            " — content search needs Atlas (or the mongodb/mongodb-atlas-local image)."
        );
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
