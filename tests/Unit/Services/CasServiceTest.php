<?php

namespace Tests\Unit\Services;

use App\Models\Users;
use App\Services\CasService;
use App\Services\UserService;
use GuzzleHttp\Client;
use Tests\Support\ServiceTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionProperty;

class CasServiceTest extends ServiceTestCase
{
    /**
     * CasService's constructor always builds a real Guzzle Client (no network
     * call happens at construction time) but never performs I/O until
     * authenticate() runs, so the real constructor is safe to call directly;
     * only the httpClient property is swapped for a mock afterward.
     */
    private function serviceWithMockedClient(Client $client): CasService
    {
        $service = new CasService();

        $property = new ReflectionProperty(CasService::class, 'httpClient');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        return $service;
    }

    /**
     * Injects a UserService backed by a mocked Users model (no real MongoDB
     * connection) into a CasService instance's private userService property.
     */
    private function withMockedUserService(CasService $service, Users $usersModel): CasService
    {
        $userService = $this->makeService(UserService::class, 'user', $usersModel);

        $property = new ReflectionProperty(CasService::class, 'userService');
        $property->setAccessible(true);
        $property->setValue($service, $userService);

        return $service;
    }

    private function mockResponseWithBody(string $body): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }

    public function testAuthenticateReturnsCasUsernameWhenCasServerRespondsYes(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("yes\nnikitas\n"));

        $this->assertSame('nikitas', $this->serviceWithMockedClient($client)->authenticate('ST-123', 'http://app.test'));
    }

    public function testAuthenticateReturnsNullWhenCasServerRespondsNo(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("no\n"));

        $this->assertNull($this->serviceWithMockedClient($client)->authenticate('ST-bad', 'http://app.test'));
    }

    public function testAuthenticateReturnsNullWhenClientThrows(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willThrowException(new \RuntimeException('connection refused'));

        $this->assertNull($this->serviceWithMockedClient($client)->authenticate('ST-123', 'http://app.test'));
    }

    public function testAuthenticateIsCaseInsensitiveOnYes(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("YES\r\nnikitas\r\n"));

        $this->assertSame('nikitas', $this->serviceWithMockedClient($client)->authenticate('ST-123', 'http://app.test'));
    }

    public function testAuthenticateReturnsNullWhenNoUsernameLinePresent(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("yes\n"));

        $this->assertNull($this->serviceWithMockedClient($client)->authenticate('ST-123', 'http://app.test'));
    }

    public function testAuthenticateAndIssueTokenReturnsNullWhenTicketInvalid(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("no\n"));

        $service = $this->serviceWithMockedClient($client);

        $this->assertNull($service->authenticateAndIssueToken('ST-bad', 'http://app.test'));
    }

    public function testAuthenticateAndIssueTokenReturnsNullWhenCasUsernameDoesNotResolveToLocalUser(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("yes\nnikitas\n"));
        $service = $this->serviceWithMockedClient($client);

        // A bare netid with no CAS_EMAIL_DOMAIN configured and no matching email in the DB.
        $usersModel = $this->createMock(Users::class);
        $usersModel->method('getUserByEmail')->willReturn(null);
        $this->withMockedUserService($service, $usersModel);

        $this->assertNull($service->authenticateAndIssueToken('ST-123', 'http://app.test'));
    }

    public function testAuthenticateAndIssueTokenIssuesJwtWhenCasUsernameIsAKnownEmail(): void
    {
        if (!defined('JWT_SECRET_KEY')) {
            define('JWT_SECRET_KEY', 'test-secret-key');
        }

        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($this->mockResponseWithBody("yes\nnikitas@hmu.gr\n"));
        $service = $this->serviceWithMockedClient($client);

        $usersModel = $this->createMock(Users::class);
        $usersModel->method('getUserByEmail')
            ->with('nikitas@hmu.gr')
            ->willReturn(['_id' => 'abc123', 'email' => 'nikitas@hmu.gr', 'isAdmin' => false]);
        $this->withMockedUserService($service, $usersModel);

        $token = $service->authenticateAndIssueToken('ST-123', 'http://app.test');

        $this->assertIsString($token);
        $this->assertSame(3, count(explode('.', (string) $token)), 'JWT should have header.payload.signature');
    }
}
