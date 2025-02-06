<?php

require_once '../vendor/autoload.php';

class CasAuth {
    private $cas_host = 'cas.youruniversity.edu'; // Change this to your CAS server domain
    private $cas_context = '/cas'; // Default CAS path
    private $cas_port = 443; // Default HTTPS port

    public function __construct() {
        \phpCAS::client(CAS_VERSION_2_0, $this->cas_host, $this->cas_port, $this->cas_context);
        \phpCAS::setNoCasServerValidation(); // Disable SSL validation (for testing only)
    }

    // Force CAS Login
    public function login() {
        \phpCAS::forceAuthentication();
    }

    // Get authenticated user
    public function getUser() {
        return \phpCAS::getUser();
    }

    // Logout from CAS
    public function logout() {
        \phpCAS::logout(['service' => 'http://yourwebsite.com']);
    }
}
