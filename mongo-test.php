<?php
/**
 * MongoDB Connection Test Script
 * 
 * This is a standalone script to test MongoDB connectivity 
 * without the complexity of the full application.
 */

// Load autoloader if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die("Composer autoloader not found. Run 'composer install' first.\n");
}

echo "MongoDB Connection Test\n";
echo "======================\n\n";

// Load environment variables
try {
    if (class_exists('App\Includes\Environment')) {
        App\Includes\Environment::load();
        echo "Environment variables loaded from .env file\n";
    }
} catch (Exception $e) {
    echo "Error loading environment variables: " . $e->getMessage() . "\n";
}

// Enhanced network diagnostics
function runNetworkDiagnostics($host) {
    echo "\n========= NETWORK DIAGNOSTICS =========\n";
    
    // Check PHP configuration
    echo "\nPHP Configuration:\n";
    echo "- PHP version: " . phpversion() . "\n";
    echo "- MongoDB extension: " . (extension_loaded('mongodb') ? phpversion('mongodb') : 'NOT LOADED') . "\n";
    echo "- OpenSSL extension: " . (extension_loaded('openssl') ? phpversion('openssl') : 'NOT LOADED') . "\n";
    echo "- allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled') . "\n";
    echo "- default_socket_timeout: " . ini_get('default_socket_timeout') . "\n";
    
    // DNS resolution tests with multiple methods
    echo "\nDNS Resolution Tests:\n";
    
    // Method 1: dns_get_record
    echo "Method 1 (dns_get_record):\n";
    $dns1 = @dns_get_record($host, DNS_A + DNS_AAAA);
    if ($dns1) {
        echo "- Success! Found " . count($dns1) . " records\n";
        foreach ($dns1 as $record) {
            echo "  - " . ($record['type'] ?? 'unknown') . ": " . 
                  ($record['ip'] ?? $record['ipv6'] ?? 'unknown') . "\n";
        }
    } else {
        echo "- Failed. Error: " . (error_get_last()['message'] ?? 'Unknown error') . "\n";
    }
    
    // Method 2: gethostbyname
    echo "\nMethod 2 (gethostbyname):\n";
    $ip = gethostbyname($host);
    if ($ip !== $host) {
        echo "- Success! Resolved to: $ip\n";
    } else {
        echo "- Failed. Could not resolve hostname.\n";
        
        // Try an alternative domain as a control test
        $control = 'google.com';
        $control_ip = gethostbyname($control);
        echo "- Control test with $control: " . 
             ($control_ip !== $control ? "SUCCESS ($control_ip)" : "FAILED") . "\n";
    }
    
    // Method 3: getaddrinfo
    if (function_exists('socket_addrinfo_lookup')) {
        echo "\nMethod 3 (socket_addrinfo_lookup):\n";
        try {
            $addrs = socket_addrinfo_lookup($host, '27017');
            if ($addrs) {
                echo "- Success! Found " . count($addrs) . " records\n";
            } else {
                echo "- Failed to resolve.\n";
            }
        } catch (Exception $e) {
            echo "- Failed with error: " . $e->getMessage() . "\n";
        }
    }
    
    // Test basic connectivity to common ports
    echo "\nConnectivity Tests:\n";
    $ports = [27017, 443, 80];
    
    foreach ($ports as $port) {
        echo "Testing $host:$port... ";
        $start = microtime(true);
        $conn = @fsockopen($host, $port, $errno, $errstr, 5);
        $time = round((microtime(true) - $start) * 1000);
        
        if ($conn) {
            echo "SUCCESS ($time ms)\n";
            fclose($conn);
        } else {
            echo "FAILED ($errstr)\n";
        }
    }
    
    // Try alternative subdomains for MongoDB Atlas
    echo "\nTrying alternative Atlas subdomains:\n";
    $alternative_hosts = [
        "ac-qiomsmd-shard-00-00.yskayrn.mongodb.net",
        "ac-qiomsmd-shard-00-01.yskayrn.mongodb.net", 
        "ac-qiomsmd-shard-00-02.yskayrn.mongodb.net"
    ];
    
    foreach ($alternative_hosts as $alt_host) {
        echo "Testing $alt_host... ";
        $ip = gethostbyname($alt_host);
        if ($ip !== $alt_host) {
            echo "Resolved to $ip.\n";
            echo "  Testing connection to $alt_host:27017... ";
            $conn = @fsockopen($alt_host, 27017, $errno, $errstr, 5);
            if ($conn) {
                echo "SUCCESS\n";
                fclose($conn);
            } else {
                echo "FAILED ($errstr)\n";
            }
        } else {
            echo "DNS resolution FAILED\n";
        }
    }
    
    // Check if cURL is available for alternative testing
    if (function_exists('curl_init')) {
        echo "\nTesting with cURL:\n";
        $ch = curl_init("https://$host");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        if ($curl_errno) {
            echo "- cURL Error ($curl_errno): $curl_error\n";
        } else {
            echo "- cURL connection successful\n";
        }
        curl_close($ch);
    }
    
    echo "\n======================================\n";
}

// Get connection parameters
$uri = getenv('MONGO_URI');
if (empty($uri)) {
    die("MONGO_URI not found in environment variables\n");
}

// Replace password placeholder if needed
$password = getenv('MONGO_PASSWORD');
if ($password && strpos($uri, '<db_password>') !== false) {
    $uri = str_replace('<db_password>', $password, $uri);
    echo "Password placeholder replaced in connection string\n";
}

// Redact password for display
$redactedUri = preg_replace('/\/\/([^:]+):([^@]+)@/', '//\\1:***@', $uri);
echo "Using connection string: $redactedUri\n\n";

// Extract hostname from URI for diagnostics
$parts = parse_url($uri);
$host = str_replace(['mongodb://', 'mongodb+srv://'], '', $parts['host'] ?? '');
if (empty($host)) {
    // Try to extract from path component (some parse_url implementations)
    $path = $parts['path'] ?? '';
    $atPos = strpos($path, '@');
    $slashPos = strpos($path, '/', $atPos);
    if ($atPos !== false) {
        $host = $slashPos !== false 
            ? substr($path, $atPos + 1, $slashPos - $atPos - 1) 
            : substr($path, $atPos + 1);
    }
}

// Run network diagnostics
runNetworkDiagnostics($host);

// Set up TLS options
$options = [
    'serverSelectionTimeoutMS' => 30000,  // Increased timeout
    'connectTimeoutMS' => 30000,          // Increased timeout
    'ssl' => true,
    'tls' => true
];

// Try direct connection options if DNS SRV fails
echo "\nAttempting MongoDB connection...\n";

// Certificate setup
$certFile = getenv('MONGO_CERT_FILE') ?: __DIR__ . '/certificates/mongodb-ca.pem';
if (file_exists($certFile)) {
    echo "Using certificate: $certFile\n";
    $options['tlsCAFile'] = $certFile;
    
    // For network issues, try allowing invalid certificates
    echo "Adding insecure connection options for testing...\n";
    $options['tlsAllowInvalidHostnames'] = true;
    $options['tlsAllowInvalidCertificates'] = true;
}

// Try different connection approaches
$approaches = [
    'standard' => $uri,
    'direct_connection' => preg_replace('/mongodb\+srv:/', 'mongodb:', $uri) . '&directConnection=true'
];

foreach ($approaches as $name => $connection_uri) {
    echo "\nTrying $name approach...\n";
    try {
        $mongo = new MongoDB\Client($connection_uri, $options);
        
        // Use a longer timeout for the ping
        $result = $mongo->selectDatabase('admin')->command(
            ['ping' => 1],
            ['maxTimeMS' => 10000]
        );
        
        echo "\n✅ Connection successful with $name approach!\n";
        echo "Server info: " . json_encode($result->toArray()) . "\n";
        
        // Try listing databases
        try {
            echo "\nListing databases...\n";
            $dbs = $mongo->listDatabases();
            foreach ($dbs as $db) {
                echo "- " . $db->getName() . "\n";
            }
            break; // Exit the loop if successful
        } catch (Exception $e) {
            echo "Could not list databases: " . $e->getMessage() . "\n";
        }
    } catch (Exception $e) {
        echo "\n❌ $name approach failed: " . $e->getMessage() . "\n";
    }
}

// Create alternative testing script with Python for comparison
$pythonScript = __DIR__ . '/test-mongo-python.py';
if (!file_exists($pythonScript)) {
    echo "\nCreating Python test script for alternative testing: test-mongo-python.py\n";
    $pythonCode = <<<'EOD'
#!/usr/bin/env python3
"""
MongoDB Connection Test (Python version)
This script tests connection to MongoDB Atlas using a different language/driver.
"""
import os
import sys
import socket
import dns.resolver
import pymongo
from pymongo.errors import ConnectionFailure

print("MongoDB Python Connection Test")
print("=============================\n")

# Get MongoDB URI from environment variable or .env file
mongo_uri = None

# Try to read from .env file if exists
if os.path.exists('.env'):
    print("Loading from .env file...")
    with open('.env', 'r') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#'):
                key, value = line.split('=', 1)
                if key == 'MONGO_URI':
                    mongo_uri = value
                    print("Found MONGO_URI in .env")
                elif key == 'MONGO_PASSWORD' and '<db_password>' in mongo_uri:
                    mongo_uri = mongo_uri.replace('<db_password>', value)
                    print("Replaced password placeholder")

if not mongo_uri:
    mongo_uri = os.environ.get('MONGO_URI')
    if not mongo_uri:
        print("ERROR: No MongoDB connection string found!")
        sys.exit(1)

# Mask password for display
masked_uri = mongo_uri
if '@' in mongo_uri:
    parts = mongo_uri.split('@')
    prefix = parts[0]
    if ':' in prefix:
        user_pass = prefix.split(':')
        masked_uri = f"{user_pass[0]}:***@{'@'.join(parts[1:])}"

print(f"Using connection string: {masked_uri}")

# Extract hostname
if 'mongodb+srv://' in mongo_uri:
    hostname = mongo_uri.split('@')[1].split('/')[0]
else:
    hostname = mongo_uri.split('@')[1].split('/')[0].split(':')[0]

print(f"\nHostname: {hostname}")

# Perform DNS checks
print("\n--- DNS Tests ---")
try:
    print(f"Resolving {hostname}...")
    ip_address = socket.gethostbyname(hostname)
    print(f"Resolved to {ip_address}")
except socket.gaierror as e:
    print(f"DNS resolution failed: {str(e)}")

# Try with dnspython if SRV record
if 'mongodb+srv' in mongo_uri:
    print("\nTrying SRV lookup...")
    try:
        answers = dns.resolver.resolve('_mongodb._tcp.' + hostname, 'SRV')
        for rdata in answers:
            print(f"SRV record: {rdata.target} (Priority: {rdata.priority}, Weight: {rdata.weight}, Port: {rdata.port})")
            try {
                ip = socket.gethostbyname(str(rdata.target))
                print(f" - Resolves to: {ip}")
            } catch (socket.gaierror) {
                print(f" - Could not resolve IP")
            }
        }
    } catch (Exception as e) {
        print(f"SRV lookup failed: {str(e)}")
    }

# Test connectivity
print("\n--- Connection Tests ---")
connection_options = {
    'serverSelectionTimeoutMS': 10000,
    'connectTimeoutMS': 20000,
    'tlsAllowInvalidCertificates': True,  # Only for testing
    'tlsAllowInvalidHostnames': True,     # Only for testing
    'retryWrites': True
}

print("Connecting to MongoDB...")
try:
    client = pymongo.MongoClient(mongo_uri, **connection_options)
    print("Client instance created, testing with ping...")
    
    # Force connection
    client.admin.command('ping')
    print("✅ Connection successful!")
    
    # List databases
    print("\nDatabases available:")
    for db_info in client.list_databases():
        print(f" - {db_info['name']}")
        
except ConnectionFailure as e:
    print(f"❌ Connection failed: {str(e)}")
    print("\nError analysis:")
    if "timed out" in str(e):
        print("- Connection timed out: Network connectivity issue or firewall blocking")
    elif "SSL" in str(e) or "certificate" in str(e):
        print("- SSL/TLS issue: Certificate validation problem")
    elif "auth" in str(e).lower():
        print("- Authentication failed: Check username and password")
except Exception as e:
    print(f"❌ Error: {str(e)}")

print("\nTest complete")
EOD;

    file_put_contents($pythonScript, $pythonCode);
    chmod($pythonScript, 0755);
    
    echo "Python test script created. To run it:\n";
    echo "1. Install required packages: pip install pymongo dnspython\n";
    echo "2. Run the script: python3 test-mongo-python.py\n";
}

echo "\nTest complete. If connection failed, please check network settings and MongoDB Atlas whitelist.\n";
