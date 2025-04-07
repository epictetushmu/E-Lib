<?php
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = '';
    $baseDir = __DIR__ . '/../';
    
    // If the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }
    
    // Get the relative class name
    $relativeClass = $class;
    
    // Convert namespace to file path
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
