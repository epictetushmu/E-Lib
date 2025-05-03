<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Controllers\PageController;
use App\Middleware\MiddlewareManager;

class PageControllerTest extends TestCase
{
    protected $pageController;
    
    protected function setUp(): void
    {
        $this->pageController = new PageController();
        
        // Mock session for testing if needed
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }
    
    public function testHomePageRenders()
    {
        // Create a mock request
        $request = [
            'uri' => '/',
            'method' => 'GET'
        ];
        
        // Capture output instead of printing it
        ob_start();
        $response = $this->pageController->home($request);
        $output = ob_get_clean();
        
        // Check if response was generated
        $this->assertNotEmpty($output);
        $this->assertStringContainsString('E-Lib', $output);
    }
    
    public function testErrorPageRenders()
    {
        // Create a mock request
        $request = [
            'uri' => '/error',
            'method' => 'GET',
            'params' => ['code' => 404, 'message' => 'Page not found']
        ];
        
        // Capture output
        ob_start();
        $response = $this->pageController->error($request);
        $output = ob_get_clean();
        
        // Check if the error page was rendered
        $this->assertStringContainsString('404', $output);
        $this->assertStringContainsString('Page not found', $output);
    }
}