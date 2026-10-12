#!/usr/bin/env php
<?php

/**
 * CLI: content-search indexing worker. Runs forever: claims the next book that needs indexing
 * (new, crashed mid-way, due for a retry, or indexed by an older embedding model), extracts its
 * pages, embeds them via the embedder container and stores them in BookChunks.
 *
 * Safe to run several copies (`docker compose up -d --scale worker=4`): books are claimed
 * atomically with a lease. Existing books need no backfill step — "never indexed" counts as new.
 *
 * Usage: php scripts/index-worker.php
 * Env:   EMBEDDING_URL, EMBEDDING_MODEL, WORKER_BATCH_SIZE, WORKER_POLL_SECONDS (see .env.example)
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Includes\Environment;
use App\Services\ContentSearchService;

Environment::load($root . '/.env');
// Log to stderr so `docker compose logs worker` shows it (bootstrap.php points error_log at a file).
ini_set('error_log', 'php://stderr');

$pollSeconds = max(1, (int) Environment::get('WORKER_POLL_SECONDS', 5));

// Finish the current book on SIGTERM/SIGINT instead of dying mid-batch. If the container is killed
// anyway, the lease lapses and the next worker resumes from the last stored batch.
$stop = false;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $handler = function () use (&$stop): void {
        $stop = true;
    };
    pcntl_signal(SIGTERM, $handler);
    pcntl_signal(SIGINT, $handler);
}

$service = new ContentSearchService();
error_log('Indexing worker started (model ' . ContentSearchService::model() . ')');

while (!$stop) {
    try {
        $worked = $service->indexNextBook();
    } catch (Throwable $e) {
        // Embedder or database unreachable — nothing was failed or lost, just wait and retry.
        error_log('Indexing worker: ' . $e->getMessage() . " — retrying in {$pollSeconds}s");
        $worked = false;
    }
    if (!$worked) {
        sleep($pollSeconds);
    }
}

error_log('Indexing worker stopped');
