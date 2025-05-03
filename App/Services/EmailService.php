<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Includes\Environment;

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Configure default settings
        $this->mailer->isSMTP();
        $this->mailer->Host = Environment::get('MAIL_HOST') ?: 'smtp.example.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = Environment::get('MAIL_USERNAME') ?: '';
        $this->mailer->Password = Environment::get('MAIL_PASSWORD') ?: '';
        $this->mailer->SMTPSecure = Environment::get('MAIL_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = Environment::get('MAIL_PORT') ?: 587;
        
        // Set default sender
        $fromEmail = Environment::get('MAIL_FROM_ADDRESS') ?: 'support@epictetuslibrary.org';
        $fromName = Environment::get('MAIL_FROM_NAME') ?: 'Epictetus Library';
        $this->mailer->setFrom($fromEmail, $fromName);
    }
    
    /**
     * Send a support email
     * 
     * @param string $fromEmail Sender email
     * @param string $fromName Sender name
     * @param string $message Email message content
     * @param string $subject Email subject
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendSupportEmail($fromEmail, $fromName, $message, $subject = 'Support Request') {
        try {
            // Set reply-to as the sender's email
            $this->mailer->addReplyTo($fromEmail, $fromName);
            
            // Set recipient (support team email)
            $supportEmail = Environment::get('SUPPORT_EMAIL') ?: 'support@epictetuslibrary.org';
            $this->mailer->addAddress($supportEmail);
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $this->formatSupportEmail($fromName, $fromEmail, $message);
            $this->mailer->AltBody = "Support request from $fromName ($fromEmail): $message";
            
            // Send email
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a general email
     * 
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlBody HTML content
     * @param string $textBody Plain text content
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlBody, $textBody = '') {
        try {
            // Reset all recipients
            $this->mailer->clearAllRecipients();
            
            // Add recipient
            $this->mailer->addAddress($toEmail, $toName);
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody ?: strip_tags($htmlBody);
            
            // Send email
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format support email HTML
     * 
     * @param string $name Sender name
     * @param string $email Sender email
     * @param string $message Support message
     * @return string Formatted HTML email
     */
    private function formatSupportEmail($name, $email, $message) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { padding: 20px; }
                .header { background-color: #4A5568; color: white; padding: 10px 20px; }
                .content { padding: 20px; border: 1px solid #E2E8F0; }
                .footer { font-size: 12px; color: #718096; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>New Support Request</h2>
                </div>
                <div class="content">
                    <p><strong>From:</strong> ' . htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ')</p>
                    <p><strong>Message:</strong></p>
                    <p>' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                <div class="footer">
                    <p>This email was sent from the Epictetus Library support form.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}