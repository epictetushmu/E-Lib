<?php

namespace App\Includes;

class Environment {
    /**
     * Load environment variables from .env file
     */
    public static function load(string $path = null): void {
        $path = $path ?? dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Environment file not found: %s', $path));
        }
        
        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('Environment file is not readable: %s', $path));
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse the line
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable if not already set
            if (!array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
            }
        }
    }
    
    /**
     * Get environment variable
     */
    public static function get(string $key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
