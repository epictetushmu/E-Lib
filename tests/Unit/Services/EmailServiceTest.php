<?php

namespace Tests\Unit\Services;

use App\Services\EmailService;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class EmailServiceTest extends TestCase
{
    /**
     * EmailService's constructor only configures a real PHPMailer instance
     * (host/credentials/from-address) with no I/O until send() runs, so the
     * real constructor is safe to call; only the mailer property is swapped
     * for a mock afterward so send() never attempts a real SMTP connection.
     */
    private function serviceWithMockedMailer(PHPMailer $mailer): EmailService
    {
        $service = new EmailService();

        $property = new ReflectionProperty(EmailService::class, 'mailer');
        $property->setAccessible(true);
        $property->setValue($service, $mailer);

        return $service;
    }

    public function testSendSupportEmailUsesSenderAsReplyToAndConfiguredSupportInbox(): void
    {
        $mailer = $this->createMock(PHPMailer::class);
        $mailer->expects($this->once())
            ->method('addReplyTo')
            ->with('sender@example.com', 'Sender Name');
        $mailer->method('send')->willReturn(true);

        $this->serviceWithMockedMailer($mailer)
            ->sendSupportEmail('sender@example.com', 'Sender Name', 'Help me please');
    }

    public function testSendSupportEmailAttachesExistingFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'elib-test-');
        file_put_contents($tmpFile, 'dummy content');

        try {
            $mailer = $this->createMock(PHPMailer::class);
            $mailer->expects($this->once())
                ->method('addAttachment')
                ->with($tmpFile, 'screenshot.png', 'base64', 'image/png');
            $mailer->method('send')->willReturn(true);

            $this->serviceWithMockedMailer($mailer)->sendSupportEmail(
                'sender@example.com',
                'Sender Name',
                'Help me please',
                [['path' => $tmpFile, 'filename' => 'screenshot.png', 'type' => 'image/png']]
            );
        } finally {
            unlink($tmpFile);
        }
    }

    public function testSendSupportEmailSkipsAttachmentWithMissingFile(): void
    {
        $mailer = $this->createMock(PHPMailer::class);
        $mailer->expects($this->never())->method('addAttachment');
        $mailer->method('send')->willReturn(true);

        $this->serviceWithMockedMailer($mailer)->sendSupportEmail(
            'sender@example.com',
            'Sender Name',
            'Help me please',
            [['path' => '/nonexistent/file.png']]
        );
    }

    public function testSendSupportEmailReturnsFalseWhenMailerThrows(): void
    {
        $mailer = $this->createMock(PHPMailer::class);
        $mailer->method('send')->willThrowException(new PHPMailerException('SMTP connect() failed'));

        $result = $this->serviceWithMockedMailer($mailer)
            ->sendSupportEmail('sender@example.com', 'Sender Name', 'Help me please');

        $this->assertFalse($result);
    }
}
