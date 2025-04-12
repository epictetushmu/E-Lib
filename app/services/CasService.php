<?php
// filepath: /Users/hub/Documents/Personal/GenCode/E-Lib/app/Services/CasService.php
namespace App\Services;

use GuzzleHttp\Client;

class CasService {
    private $casServerUrl;
    private $client;

    public function __construct() {
        $this->casServerUrl = $_ENV['CAS_SERVER_URL']; // Set this in your .env file
        $this->client = new Client();
    }

    public function authenticate($ticket, $serviceUrl) {
        $validateUrl = $this->casServerUrl . '/serviceValidate';
        $response = $this->client->get($validateUrl, [
            'query' => [
                'ticket' => $ticket,
                'service' => $serviceUrl,
            ],
        ]);

        if ($response->getStatusCode() === 200) {
            $body = $response->getBody()->getContents();
            if (strpos($body, '<cas:authenticationSuccess>') !== false) {
                return true; // Authentication successful
            }
        }

        return false; // Authentication failed
    }
}