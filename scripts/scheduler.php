#!/usr/bin/env php
<?php

/**
 * CLI: the library's time-driven jobs — hand freed copies to the next reader on the waitlist, send
 * "due soon" and "overdue" notices (once per loan), and email queued notifications. Safe to run as
 * often as you like; the `scheduler` compose service runs it every minute.
 *
 * Usage: php scripts/scheduler.php
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Includes\Environment;
use App\Services\BookService;

Environment::load($root . '/.env');
ini_set('error_log', 'php://stderr');

$done = (new BookService())->runScheduledTasks();
if (array_sum($done) > 0) {
    fwrite(STDOUT, date('c') . ' ' . json_encode($done) . "\n");
}
