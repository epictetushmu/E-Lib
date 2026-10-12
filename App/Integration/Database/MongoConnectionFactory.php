<?php

namespace App\Integration\Database;

use App\Includes\Environment;
use MongoDB\Client;
use MongoDB\Driver\ServerApi;

class MongoConnectionFactory
{
    private static ?Client $mongoClient = null;

    /**
     * @param array<string, mixed> $options
     * @return \MongoDB\Database
     */
    public static function create(string $type = 'mongo', array $options = [])
    {
        $defaults = ['dbName' => 'LibraryDb'];
        $config = array_merge($defaults, $options);

        if ($type !== 'mongo') {
            throw new \InvalidArgumentException("Unsupported database type: $type");
        }

        return self::getMongoConnection($config['dbName']);
    }

    /**
     * URI options. Atlas works with the OS trust store — do not set tlsCAFile unless you must
     * (e.g. corporate proxy). MONGO_CERT_FILE is ignored here on purpose; use MONGO_TLS_CA_FILE only if needed.
     *
     * @return array<string, int|string>
     */
    private static function uriOptionsFromEnv(): array
    {
        $projectRoot = dirname(__DIR__, 3);
        $uriOptions = [
            'authSource' => 'admin',
            'serverSelectionTimeoutMS' => 45000,
            'connectTimeoutMS' => 20000,
        ];

        $ca = Environment::get('MONGO_TLS_CA_FILE', '');
        if ($ca !== '' && $ca !== false) {
            $path = (string) $ca;
            if (!str_starts_with($path, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
                $path = $projectRoot . '/' . ltrim(str_replace('\\', '/', $path), '/');
            }
            if (is_file($path)) {
                $uriOptions['tlsCAFile'] = $path;
            }
        }

        return $uriOptions;
    }

    /**
     * @return \MongoDB\Database
     */
    private static function getMongoConnection(string $dbName)
    {
        $uri = Environment::get('MONGO_URI', '');
        if ($uri === false || $uri === null) {
            $uri = '';
        }
        $uri = trim((string) $uri);

        $password = Environment::get('MONGO_PASSWORD', '');
        if ($password !== '' && $password !== false) {
            $encoded = rawurlencode((string) $password);
            if (str_contains($uri, '${MONGO_PASSWORD}')) {
                $uri = str_replace('${MONGO_PASSWORD}', $encoded, $uri);
            }
            if (str_contains($uri, '<db_password>')) {
                $uri = str_replace('<db_password>', $encoded, $uri);
            }
        }

        if ($uri === '' || !preg_match('#^mongodb(\+srv)?://#i', $uri)) {
            throw new \InvalidArgumentException(
                'MONGO_URI must be set to a mongodb:// or mongodb+srv:// connection string.'
            );
        }

        $uriOptions = self::uriOptionsFromEnv();

        if (self::$mongoClient === null) {
            $driverOpts = [];
            // Stable API is optional; some driver/Atlas combos mis-handshake with hello — opt-in only
            if (Environment::get('MONGO_SERVER_API', '') === '1') {
                $driverOpts['serverApi'] = new ServerApi(ServerApi::V1);
            }

            // Do NOT assign self::$mongoClient until ping succeeds. Otherwise php -S reuses the
            // process and a failed first ping leaves a broken client for every later request.
            $client = new Client($uri, $uriOptions, $driverOpts);
            $db = $client->selectDatabase($dbName);
            $db->command(['ping' => 1]);
            self::$mongoClient = $client;

            return $db;
        }

        return self::$mongoClient->selectDatabase($dbName);
    }

    /**
     * Clear cached client (e.g. after connection drops). Next create() will reconnect.
     */
    public static function resetClient(): void
    {
        self::$mongoClient = null;
    }

    public static function getClient(): Client
    {
        if (self::$mongoClient === null) {
            throw new \RuntimeException('MongoDB client not initialized');
        }
        return self::$mongoClient;
    }
}
