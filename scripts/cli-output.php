<?php

/**
 * Shared ANSI output helpers for the CLI scripts in scripts/ and check-system.php.
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
