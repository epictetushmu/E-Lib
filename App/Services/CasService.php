<?php
// filepath: /Users/hub/Documents/Personal/GenCode/app/Services/CasService.php
namespace App\Services;

use App\Includes\Environment;

class CasService {
    private $casServerUrl;
    private $httpClient;

    public function __construct() {
        $this->casServerUrl = Environment::get('CAS_SERVER_URL', 'https://cas-server.example.org/cas');
        
        // Try to use Guzzle if available, otherwise use a simple fallback
        if (class_exists('\GuzzleHttp\Client')) {
            $this->httpClient = new \GuzzleHttp\Client();
        }
    }

    public function authenticate($ticket, $serviceUrl) {
        try {
            if ($this->httpClient) {
                // Use Guzzle client
                $response = $this->httpClient->get("{$this->casServerUrl}/validate", [
                    'query' => [
                        'ticket' => $ticket,
                        'service' => $serviceUrl
                    ]
                ]);
                $body = $response->getBody()->getContents();
            } else {
                // Fallback to native PHP if Guzzle is not available
                $validationUrl = "{$this->casServerUrl}/validate?ticket=" . urlencode($ticket) . "&service=" . urlencode($serviceUrl);
                $body = @file_get_contents($validationUrl);
                if ($body === false) {
                    echo("CAS validation request failed: Unable to connect to CAS server");
                    return false;
                }
            }
            
            // Process the CAS server response
            $lines = explode("\n", trim($body));
            if (count($lines) >= 1 && strtolower(trim($lines[0])) === 'yes') {
                return true; 
            }
            
            return false;
        } catch (\Exception $e) {
            echo("CAS authentication error: " . $e->getMessage());
            return false;
        }
    }
}