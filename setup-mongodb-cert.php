<?php
/**
 * MongoDB Certificate Setup Script
 * 
 * This script downloads the MongoDB Atlas root certificate
 * and saves it in the proper location for your application.
 */

// Make it work both from CLI and web
function output($message) {
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
    } else {
        echo($message);
    }
}

// Directory for certificates
$certDir = __DIR__ . '/certificates';
$certFile = $certDir . '/mongodb-ca.pem';

// Create directory if it doesn't exist
if (!is_dir($certDir)) {
    output("Creating certificate directory: {$certDir}");
    if (!mkdir($certDir, 0755, true)) {
        output("Failed to create certificate directory!");
        exit(1);
    }
}

// MongoDB Atlas root certificate URL
$certUrl = 'https://truststore.pki.mongodb.com/atlas-root-ca.pem';
output("Attempting to download certificate from: {$certUrl}");

// Multiple download attempts with different approaches
$certContent = null;

// Attempt 1: Standard file_get_contents
$certContent = @file_get_contents($certUrl);

// Attempt 2: With context options to handle SSL verification issues
if ($certContent === false) {
    output("First download attempt failed, trying with modified SSL context...");
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (E-Lib Certificate Downloader)'
        ]
    ]);
    $certContent = @file_get_contents($certUrl, false, $context);
}

// Attempt 3: Using cURL
if ($certContent === false && function_exists('curl_init')) {
    output("Stream attempts failed, trying with cURL...");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $certUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $certContent = curl_exec($ch);
    
    if (curl_errno($ch)) {
        output("cURL error: " . curl_error($ch));
    }
    
    curl_close($ch);
}

// Fallback: Use hardcoded certificate (last resort)
if ($certContent === false || empty($certContent)) {
    output("All download attempts failed, using bundled certificate...");
    $certContent = getBundledCertificate();
}

// Save certificate
if ($certContent) {
    output("Saving certificate to: {$certFile}");
    
    if (file_put_contents($certFile, $certContent) === false) {
        output("Failed to save certificate to: {$certFile}");
        exit(1);
    }
    
    // Set proper permissions
    chmod($certFile, 0644);
    output("Certificate successfully saved!");
} else {
    output("Failed to obtain certificate content from any source!");
    exit(1);
}

// Verify certificate content
if (file_exists($certFile) && filesize($certFile) < 100) {
    output("Warning: Certificate file exists but seems too small. Content might be invalid.");
    if (filesize($certFile) === 0) {
        output("Error: Certificate file is empty!");
        exit(1);
    }
}

// Update .env file if it exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile) && is_writable($envFile)) {
    $env = file_get_contents($envFile);
    if (strpos($env, 'MONGO_CERT_FILE=') === false) {
        output("Adding MONGO_CERT_FILE to .env");
        $env .= "\nMONGO_CERT_FILE=" . $certFile . "\n";
        file_put_contents($envFile, $env);
    }
}

// If we're in Docker, also update the global environment
if (getenv('DOCKER_ENV') === 'true' && !getenv('MONGO_CERT_FILE')) {
    putenv("MONGO_CERT_FILE={$certFile}");
}

output("Setup complete!");

/**
 * Get MongoDB Atlas bundled certificate content as a last resort fallback
 */
function getBundledCertificate() {
    // This is the MongoDB Atlas CA certificate content (as of 2023)
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
