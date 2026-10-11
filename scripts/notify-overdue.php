#!/usr/bin/env php
<?php

/**
 * CLI: notify readers (in-app + email) about physical loans that have gone overdue. Each loan is
 * notified once, so it is safe to run as often as you like — the `scheduler` compose service runs
 * it hourly.
 *
 * Usage: php scripts/notify-overdue.php
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Includes\Environment;
use App\Services\BookService;

Environment::load($root . '/.env');
ini_set('error_log', 'php://stderr');

$sent = (new BookService())->notifyOverdue();
fwrite(STDOUT, date('c') . " overdue notices sent: $sent\n");
