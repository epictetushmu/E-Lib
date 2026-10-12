#!/usr/bin/env php
<?php

/**
 * CLI: creates a user with isAdmin=true, or promotes an existing one.
 *
 * There is no self-service way to become an admin through the app's UI/API
 * (by design — App\Includes\AuthenticatedUser::isAdmin() only trusts the
 * session flag set at login from Users.isAdmin). Provisioning an admin is
 * therefore an ops/dev-tooling concern, not something the app or its test
 * suite should do for itself — hence this script, run directly against the
 * database, same as scripts/db-setup.php.
 *
 * Usage:
 *   php scripts/create-admin.php <email> <password> [username]
 *   composer admin:create -- <email> <password> [username]
 *
 * Safe to re-run: an existing user is promoted (isAdmin set to true) rather
 * than duplicated; the password is left untouched in that case.
 */

require_once __DIR__ . '/cli-output.php';

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$username = $argv[3] ?? null;

if (!$email || !$password) {
    fail('Usage: php scripts/create-admin.php <email> <password> [username]');
    exit(1);
}

if (strlen($password) < 8) {
    fail('Password must be at least 8 characters.');
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

App\Includes\Environment::load($root . '/.env');

echo YELLOW . "E-Lib Admin Provisioning\n" . RESET;
echo "=========================\n\n";

try {
    App\Repository\DatabaseRepository::getInstance();
} catch (Throwable $e) {
    fail('Connection failed: ' . $e->getMessage());
    fail("Run 'php scripts/mongo-ping.php' to diagnose.");
    exit(1);
}

$users = new App\Models\Users();

$existing = $users->getUserByEmail($email);
if ($existing) {
    if (($existing['isAdmin'] ?? false) === true) {
        success("\"{$email}\" is already an admin — nothing to do.");
        exit(0);
    }

    $users->updateById((string) $existing['_id'], ['isAdmin' => true]);
    success("Promoted existing user \"{$email}\" to admin.");
    exit(0);
}

$user = [
    'username' => $username ?: strstr($email, '@', true),
    'email' => $email,
    'password' => password_hash($password, PASSWORD_BCRYPT),
    'isAdmin' => true,
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
];

try {
    $result = $users->registerUser($user);
} catch (InvalidArgumentException $e) {
    fail('Validation failed: ' . $e->getMessage());
    exit(1);
}

if (empty($result['insertedId'])) {
    fail('Insert did not return an id: ' . ($result['error'] ?? 'unknown error'));
    exit(1);
}

success("Created admin user \"{$email}\" (id {$result['insertedId']}).");
exit(0);
