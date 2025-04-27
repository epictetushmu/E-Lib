<?php

namespace App\Includes;

class Environment {
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null): void {
        $path = $path ?? dirname(__DIR__, 2) . '/.env';
        
        // If .env file exists, load it
        if (file_exists($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse the line
                if (strpos($line, '=') === false) {
                    continue; // Skip lines without an equals sign
                }
                    
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                }
                
                // Only set if not already defined in environment
                if (!getenv($name)) {
                    putenv(sprintf('%s=%s', $name, $value));
                }
                    
                // Only set in $_ENV if not already set
                if (!isset($_ENV[$name])) {
                    $_ENV[$name] = $value;
                }
            }
        } else {
            // Log that we're using only system environment variables, but don't treat as an error
            error_log('INFO: No .env file found at: ' . $path . '. Using system environment variables only.');
        }
    }
    
    /**
     * Get environment variable
     */
    public static function get(string $key, $default = null) {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $_ENV[$key] ?? $default;
    }
}
