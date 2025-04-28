<?php

namespace App\Integration\Database;

use App\Database\JsonDatabase;
use App\Includes\Environment;
use MongoDB\Driver\ServerApi;
use MongoDB\Client;

class MongoConnectionFactory{
    
    private static $mongoClient = null;

    /**
     * Create a database connection with fallback options
     * 
     * @param string $type The type of database to connect to (mongo, json)
     * @param array $options Connection options
     * @return mixed connection with mongo object or json object
     */
    public static function create($type = 'mongo', $options = [])
    {
        // Set default options
        $defaults = [
            'dbName' => 'LibraryDb'
        ];
        
        $config = array_merge($defaults, $options);
        
        if ($type === 'mongo') {
            try {
                // Get MongoDB connection
                $mongoDb = self::getMongoConnection($config['dbName'], []);
                return $mongoDb;
            } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
                echo("MongoDB connection failed: " . $e->getMessage());
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    echo("Falling back to JsonDatabase");
                    return new JsonDatabase();
                }
                
                // Re-throw if no fallback requested
                throw $e;
            } catch (\Exception $e) {
                echo("Database error: " . $e->getMessage());
                
                // Fall back to JsonDatabase if requested
                if (!empty($options['fallback']) && $options['fallback'] === true) {
                    echo("Falling back to JsonDatabase due to error");
                    return new JsonDatabase();
                }
                
                // Re-throw if no fallback requested
                throw $e;
            }
        } elseif ($type === 'json') {
            return new JsonDatabase();
        }
        
        throw new \InvalidArgumentException("Unsupported database type: $type");
    }

    /**
     * Get a MongoDB database connection
     * 
     * @param string $dbName Database name
     * @param array $options Connection options
     * @return \MongoDB\Database
     */
    private static function getMongoConnection($dbName, $options = [])
    {
        // Get MongoDB connection string from environment variables or use default
        $connectionString = Environment::get('MONGO_URI', getenv('MONGO_URI'));
       
        $mongoPassword = Environment::get('MONGO_PASSWORD', getenv('MONGO_PASSWORD'));
        if ($mongoPassword && strpos($connectionString, '<db_password>') !== false) {
            $connectionString = str_replace('<db_password>', $mongoPassword, $connectionString);
        }
        
        // TLS configuration for MongoDB Atlas
        if (extension_loaded('openssl')) {
            // Default directory for certificates
            $certDir = __DIR__ . '/../../../certificates';
            if (!is_dir($certDir)) {
                mkdir($certDir, 0755, true);
            }
            
            $certFile = getenv('MONGO_CERT_FILE') ?: $certDir . '/mongodb-ca.pem';
            
            // If certificate doesn't exist, try to download it
            if (!file_exists($certFile)) {
                self::downloadMongoCertificate($certFile);
            }
            
            // Configure TLS options for MongoDB Atlas
            if (file_exists($certFile)) {
                // MongoDB Atlas requires these specific TLS options
                $options['mongoOptions']['tls'] = true;
                $options['mongoOptions']['tlsCAFile'] = $certFile;
                $options['mongoOptions']['tlsAllowInvalidHostnames'] = false;
                $options['mongoOptions']['tlsAllowInvalidCertificates'] = false;
            } else {
                // Try with system CA bundle if specific cert not found
                $options['mongoOptions']['tls'] = true;
                echo("Using system CA bundle for MongoDB TLS connection");
            }
        } else {
            echo("Warning: OpenSSL extension not loaded. SSL/TLS connections will not work properly.");
        }

        // Create client if it doesn't exist
        if (self::$mongoClient === null) {
            try {
                $apiVersion = new ServerApi(ServerApi::V1);
                
           
                self::$mongoClient = new Client($connectionString, ["authSource" => 'admin'], ['serverApi' => $apiVersion]);
          
                // Get the database and verify connection by running a ping command
                $db = self::$mongoClient->selectDatabase($dbName);
                $db->command(['ping' => 1]);
                return $db;
            } catch (\Exception $e) {
                echo("MongoDB connection error: " . $e->getMessage());
                throw $e;
            }
        }
        // If client already exists, just return the database
        return self::$mongoClient->selectDatabase($dbName);
    }        
    
    /**
     * Download MongoDB CA certificate
     * 
     * @param string $savePath Path where to save the certificate
     * @return bool True if successful, false otherwise
     */
    private static function downloadMongoCertificate($savePath)
    {
        try {
            // First attempt - standard file_get_contents
            $certUrl = 'https://truststore.pki.mongodb.com/atlas-root-ca.pem';
            echo("Downloading MongoDB certificate from {$certUrl}...");
            $certContent = @file_get_contents($certUrl);
            
            // Second attempt - with stream context if first attempt fails
            if ($certContent === false) {
                echo("First download attempt failed, trying with modified SSL context...");
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                    'http' => [
                        'timeout' => 15,  // Increase timeout
                        'user_agent' => 'Mozilla/5.0 (E-Lib Certificate Downloader)',
                    ]
                ]);
                
                $certContent = @file_get_contents($certUrl, false, $context);
            }
            
            // Third attempt - using cURL as a last resort
            if ($certContent === false && function_exists('curl_init')) {
                echo("Stream attempts failed, trying with cURL...");
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $certUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $certContent = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    echo("cURL error: " . curl_error($ch));
                }
                
                curl_close($ch);
            }
            
            // Last resort - use hardcoded certificate content
            if ($certContent === false || empty($certContent)) {
                echo("All download attempts failed, using bundled certificate...");
                $certContent = self::getBundledCertificate();
                
                if (empty($certContent)) {
                    echo("Failed to get bundled certificate!");
                    return false;
                }
            }
            
            // Save the certificate to disk
            if (file_put_contents($savePath, $certContent) === false) {
                echo("Failed to save MongoDB certificate to {$savePath}");
                return false;
            }
            
            // Verify the file exists and has content
            if (!file_exists($savePath) || filesize($savePath) < 100) {
                echo("Certificate file is invalid or too small");
                return false;
            }
            
            echo("Successfully saved MongoDB certificate to {$savePath}");
            return true;
        } catch (\Exception $e) {
            echo("Error downloading certificate: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get MongoDB Atlas bundled certificate content
     * This serves as a last resort if certificate download fails
     * 
     * @return string Certificate content
     */
    private static function getBundledCertificate() {
        // This is the MongoDB Atlas CA certificate content (as of 2023)
        // Hardcoding this is a last resort fallback for environments with restricted outbound connections
        return "-----BEGIN CERTIFICATE-----
MIIDrzCCApegAwIBAgIQCDvgVpBCRrGhdWrJWZHHSjANBgkqhkiG9w0BAQUFADBh
MQswCQYDVQQGEwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3
d3cuZGlnaWNlcnQuY29tMSAwHgYDVQQDExdEaWdpQ2VydCBHbG9iYWwgUm9vdCBD
QTAeFw0wNjExMTAwMDAwMDBaFw0zMTExMTAwMDAwMDBaMGExCzAJBgNVBAYTAlVT
MRUwEwYDVQQKEwxEaWdpQ2VydCBJbmMxGTAXBgNVBAsTEHd3dy5kaWdpY2VydC5j
b20xIDAeBgNVBAMTF0RpZ2lDZXJ0IEdsb2JhbCBSb290IENBMIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4jvhEXLeqKTTo1eqUKKPC3eQyaKl7hLOllsB
CSDMAZOnTjC3U/dDxGkAV53ijSLdhwZAAIEJzs4bg7/fzTtxRuLWZscFs3YnFo97
nh6Vfe63SKMI2tavegw5BmV/Sl0fvBf4q77uKNd0f3p4mVmFaG5cIzJLv07A6Fpt
43C/dxC//AH2hdmoRBBYMql1GNXRor5H4idq9Joz+EkIYIvUX7Q6hL+hqkpMfT7P
T19sdl6gSzeRntwi5m3OFBqOasv+zbMUZBfHWymeMr/y7vrTC0LUq7dBMtoM1O/4
gdW7jVg/tRvoSSiicNoxBN33shbyTApOB6jtSj1etX+jkMOvJwIDAQABo2MwYTAO
BgNVHQ8BAf8EBAMCAYYwDwYDVR0TAQH/BAUwAwEB/zAdBgNVHQ4EFgQUA95QNVbR
TLtm8KPiGxvDl7I90VUwHwYDVR0jBBgwFoAUA95QNVbRTLtm8KPiGxvDl7I90VUw
DQYJKoZIhvcNAQEFBQADggEBAMucN6pIExIK+t1EnE9SsPTfrgT1eXkIoyQY/Esr
hMAtudXH/vTBH1jLuG2cenTnmCmrEbXjcKChzUyImZOMkXDiqw8cvpOp/2PV5Adg
06O/nVsJ8dWO41P0jmP6P6fbtGbfYmbW0W5BjfIttep3Sp+dWOIrWcBAI+0tKIJF
PnlUkiaY4IBIqDfv8NZ5YBberOgOzW6sRBc4L0na4UU+Krk2U886UAb3LujEV0ls
YSEY1QSteDwsOoBrp+uvFRTp2InBuThs4pFsiv9kuXclVzDAGySj4dzp30d8tbQk
CAUw7C29C79Fv1C5qfPrmAESrciIxpg0X40KPMbp1ZWVbd4=
-----END CERTIFICATE-----";
    }

    /**
     * Get the MongoDB client instance
     * 
     * @return \MongoDB\Client
     */
    public static function getClient()
    {
        if (self::$mongoClient === null) {
            throw new \RuntimeException("MongoDB client not initialized");
        }
        return self::$mongoClient;
    }
}
